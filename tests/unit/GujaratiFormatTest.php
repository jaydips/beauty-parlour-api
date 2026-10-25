<?php

declare(strict_types=1);

use App\Database\Seeds\SettingsSeeder;
use App\Libraries\WhatsApp;
use App\Models\SettingModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Gujarati dates/times in the UI helpers and in the WhatsApp templates, and UTF-8 safety of the wa.me links.
 *
 * @internal
 */
final class GujaratiFormatTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = SettingsSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        helper('format');
    }

    protected function tearDown(): void
    {
        Time::setTestNow();
        parent::tearDown();
    }

    public function testDatesAndTimesAreGujaratiWithAsciiDigits(): void
    {
        $this->assertSame('25 ઑક્ટો 2026', fmt_date('2026-10-25 10:30:00'));
        $this->assertSame('સવારે 10:30', fmt_time('2026-10-25 10:30:00'));
        $this->assertSame('બપોરે 12:00', fmt_time('2026-10-25 12:00:00'));
        $this->assertSame('બપોરે 3:45', fmt_time('2026-10-25 15:45:00'));
        $this->assertSame('સાંજે 6:30', fmt_time('2026-10-25 18:30:00'));
        $this->assertSame('રાત્રે 9:00', fmt_time('2026-10-25 21:00:00'));
        $this->assertSame('રાત્રે 12:15', fmt_time('2026-10-26 00:15:00'));
        $this->assertSame('1 જાન્યુ 2027, સવારે 9:05', fmt_datetime('2027-01-01 09:05:00'));
        $this->assertSame('રવિવાર, 25 ઑક્ટોબર 2026', gu_date(Time::parse('2026-10-25'), true, true));
        $this->assertSame('શનિવાર, 7 નવેમ્બર 2026', gu_date(Time::parse('2026-11-07'), true, true));
        $this->assertSame('', fmt_date(null));
    }

    public function testDayHeadersUseTodayTomorrowYesterday(): void
    {
        Time::setTestNow('2026-10-25 12:00:00');

        $this->assertSame('આજે, 25 ઑક્ટો', fmt_day_title('2026-10-25'));
        $this->assertSame('કાલે, 26 ઑક્ટો', fmt_day_title('2026-10-26'));
        $this->assertSame('ગઈકાલે, 24 ઑક્ટો', fmt_day_title('2026-10-24'));
        $this->assertSame('શુક્ર, 30 ઑક્ટો 2026', fmt_day_title('2026-10-30'));
    }

    public function testEnumLabelsAreGujaratiAndUnknownValuesStayReadable(): void
    {
        $this->assertSame('નક્કી', status_label('scheduled'));
        $this->assertSame('ન આવ્યા', status_label('no_show'));
        $this->assertSame('રોકડ', method_label('cash'));
        $this->assertSame('UPI', method_label('upi'));
        $this->assertSame('સીધા આવ્યા', source_label('walk_in'));
        $this->assertSame('બુકિંગ કન્ફર્મેશન', message_type_label('booking'));
        $this->assertSame('કતારમાં', message_status_label('queued'));
        $this->assertSame('something_else', status_label('something_else'));
        $this->assertSame('', status_label(null));
    }

    public function testTemplatesRenderGujaratiDefaultsAndRespectOwnerEdits(): void
    {
        $wa       = new WhatsApp();
        $customer = ['name' => 'આશા પટેલ', 'phone' => '9876543211'];
        $appt     = ['starts_at' => '2026-11-07 10:30:00'];
        $items    = [['service_name' => 'Haircut'], ['service_name' => 'Facial']];

        $booking = $wa->bookingMessage($customer, $appt, $items);
        $this->assertStringStartsWith('નમસ્તે આશા પટેલ, મારું બ્યુટી પાર્લર માં', $booking);
        $this->assertStringContainsString("તારીખ: શનિવાર, 7 નવેમ્બર 2026\nસમય: સવારે 10:30\nસેવા: Haircut, Facial", $booking);

        $reminder = $wa->appointmentMessage($customer, $appt, []);
        $this->assertStringContainsString('યાદ અપાવવા માટે', $reminder);
        $this->assertStringContainsString('સેવા: તમારી અપોઇન્ટમેન્ટ', $reminder, 'services fallback is Gujarati');

        $this->assertStringContainsString('જન્મદિવસની હાર્દિક શુભેચ્છાઓ આશા પટેલ!', $wa->birthdayMessage($customer));
        $this->assertStringContainsString('છેલ્લી મુલાકાતને ઘણો સમય થયો', $wa->followUpMessage($customer));
        $this->assertStringContainsString('ખાસ ઑફર', $wa->template('offer'));
        $this->assertSame('મારું બ્યુટી પાર્લર તરફથી ટેસ્ટ મેસેજ: ઑટો WhatsApp બરાબર ચાલે છે.', $wa->testMessage());

        // the owner's own text wins; an emptied template falls back to the Gujarati default
        (new SettingModel())->setValue('tpl_whatsapp_booking', 'Hi {customer_name}, booked on {date} at {time}.');
        $this->assertSame('Hi આશા પટેલ, booked on શનિવાર, 7 નવેમ્બર 2026 at સવારે 10:30.', $wa->bookingMessage($customer, $appt, $items));
        (new SettingModel())->setValue('tpl_whatsapp_booking', '');
        $this->assertStringStartsWith('નમસ્તે આશા પટેલ', $wa->bookingMessage($customer, $appt, $items));
    }

    public function testWaMeLinkCarriesGujaratiTextIntact(): void
    {
        $wa      = new WhatsApp();
        $message = "નમસ્તે આશા, કાલે સવારે 10:30 એ મળીએ!\nઆભાર";
        $link    = $wa->link('98765 43211', $message);

        $this->assertStringStartsWith('https://wa.me/919876543211?text=', $link);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9%._~:\/?=-]+$/', $link, 'only ASCII in the URL');
        $this->assertSame($message, rawurldecode(substr($link, strlen('https://wa.me/919876543211?text='))));
    }
}
