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

        // no address / phone configured: the signature keeps only the parlour name
        $booking = $wa->bookingMessage($customer, $appt, $items);
        $this->assertSame(
            "✅ *નમસ્તે આશા પટેલ!* તમારી અપોઇન્ટમેન્ટ બુક થઈ ગઈ છે.\n\n📅 *તારીખ:* 07/11/2026\n⏰ *સમય:* સવારે 10:30\n💅 *સર્વિસ:* Haircut, Facial\n\n*મારું બ્યુટી પાર્લર*\n\nકોઈ ફેરફાર કરવો હોય તો અમને જણાવશો. આભાર! 🌸",
            $booking,
        );

        // with address and phone the signature has three lines
        $settings = new SettingModel();
        $settings->setValue('parlour_address', 'સ્ટેશન રોડ, સુરત');
        $settings->setValue('parlour_phone', '9033319266');
        $booking = $wa->bookingMessage($customer, $appt, $items);
        $this->assertStringContainsString("*મારું બ્યુટી પાર્લર*\n📍 સ્ટેશન રોડ, સુરત\n📞 9033319266\n\nકોઈ ફેરફાર", $booking);
        $this->assertStringNotContainsString('{', $booking);

        $reminder = $wa->appointmentMessage($customer, $appt, []);
        $this->assertStringStartsWith('⏰ *નમસ્તે આશા પટેલ!* તમારી અપોઇન્ટમેન્ટનું રિમાઇન્ડર.', $reminder);
        $this->assertStringContainsString('💅 *સર્વિસ:* તમારી અપોઇન્ટમેન્ટ', $reminder, 'services fallback is Gujarati');

        $this->assertStringStartsWith('🎂 *નમસ્તે આશા પટેલ!* જન્મદિવસની હાર્દિક શુભેચ્છાઓ! 🎉', $wa->birthdayMessage($customer));
        $this->assertStringStartsWith('🌸 *નમસ્તે આશા પટેલ!* તમારી છેલ્લી મુલાકાતને ઘણો સમય થયો.', $wa->followUpMessage($customer));
        $this->assertStringContainsString('📞 9033319266', $wa->followUpMessage($customer));
        $this->assertStringContainsString('આ અઠવાડિયે ખાસ ઑફર!', $wa->template('offer'));
        $this->assertStringEndsWith("આભાર! 🌸\n_(ઑફર મેસેજ બંધ કરવા STOP લખો.)_", $wa->template('offer'));
        $this->assertSame('મારું બ્યુટી પાર્લર તરફથી ટેસ્ટ મેસેજ: ઑટો WhatsApp બરાબર ચાલે છે.', $wa->testMessage());

        // the owner's own text wins; an emptied template falls back to the default
        $settings->setValue('tpl_whatsapp_booking', 'Hi {customer_name}, booked on {date} at {time}.');
        $this->assertSame('Hi આશા પટેલ, booked on 07/11/2026 at સવારે 10:30.', $wa->bookingMessage($customer, $appt, $items));
        $settings->setValue('tpl_whatsapp_booking', '');
        $this->assertStringStartsWith('✅ *નમસ્તે આશા પટેલ!*', $wa->bookingMessage($customer, $appt, $items));
    }

    public function testReviewMessageCarriesTheLinksOrGujaratiHints(): void
    {
        $wa       = new WhatsApp();
        $settings = new SettingModel();
        $customer = ['name' => 'આશા પટેલ', 'phone' => '9876543211'];
        $appt     = ['starts_at' => '2026-11-07 10:30:00'];
        $items    = [['service_name' => 'Hair Spa']];

        // links not configured yet: hints instead, so the owner notices before sending
        $message = $wa->reviewMessage($customer, $appt, $items);
        $this->assertStringStartsWith('નમસ્તે *આશા પટેલ*, મારું બ્યુટી પાર્લર ની મુલાકાત બદલ આભાર 🙏', $message);
        $this->assertStringContainsString("💅 *સર્વિસ:* Hair Spa\n⭐ આપનો અનુભવ કેવો રહ્યો? Google પર review આપો:\n👉 (સેટિંગ્સમાં Google review લિંક ઉમેરો)\n📸 Instagram પર અમને follow કરો:\n👉 (સેટિંગ્સમાં Instagram લિંક ઉમેરો)", $message);
        $this->assertStringEndsWith('આપનો અભિપ્રાય અમારા માટે મહત્વનો છે. આભાર! 🌸', $message);

        $settings->setValue('google_review_url', 'https://g.page/r/abc123/review');
        $settings->setValue('instagram_url', 'https://instagram.com/delicate_parlour');
        $settings->setValue('parlour_phone', '9033319266');
        $message = $wa->reviewMessage($customer, $appt, $items);
        $this->assertStringContainsString("👉 https://g.page/r/abc123/review\n📸 Instagram પર અમને follow કરો:\n👉 https://instagram.com/delicate_parlour", $message);
        $this->assertStringContainsString("*મારું બ્યુટી પાર્લર*\n📞 9033319266", $message, 'empty address line dropped, phone kept');
        $this->assertDoesNotMatchRegularExpression('/\*https?:/', $message, 'URLs are never inside bold');
        $this->assertStringNotContainsString('{', $message);

        // the URL settings are validated (empty allowed, otherwise http(s) without spaces)
        $this->assertNull(SettingModel::validateValue('google_review_url', ''));
        $this->assertNull(SettingModel::validateValue('instagram_url', 'https://instagram.com/x'));
        $this->assertNotNull(SettingModel::validateValue('google_review_url', 'g.page/r/abc'));
        $this->assertNotNull(SettingModel::validateValue('instagram_url', 'https://insta gram.com'));
    }

    public function testRenderDropsOnlyDecorationLinesLeftByEmptyPlaceholders(): void
    {
        $wa   = new WhatsApp();
        $vars = ['customer_name' => 'આશા', 'parlour_address' => '', 'parlour_phone' => ' ', 'services' => ''];

        $out = $wa->render("નમસ્તે {customer_name}\n📍 {parlour_address}\n📞 {parlour_phone}\n💅 *સર્વિસ:* {services}\n\nઆભાર", $vars);
        $this->assertSame("નમસ્તે આશા\n💅 *સર્વિસ:*\n\nઆભાર", $out, 'emoji-only lines go, lines with words stay, blank lines stay');
        $this->assertSame('📍 રોડ', $wa->render('📍 {parlour_address}', ['parlour_address' => 'રોડ']));
    }

    public function testEmojiAndBoldMarkupSurviveStorageAndRendering(): void
    {
        $settings = new SettingModel();
        $template = "🎁 *નમસ્તે {customer_name}!* _slot_ 💇‍♀️";
        $settings->setValue('tpl_whatsapp_offer', $template);

        // stored and read back byte for byte (SQLite here; MySQL needs utf8mb4, see Config\Database)
        $this->assertSame($template, $settings->value('tpl_whatsapp_offer'));
        $this->assertSame('🎁 *નમસ્તે આશા!* _slot_ 💇‍♀️', (new WhatsApp())->render($template, ['customer_name' => 'આશા']));

        // the settings form shows the raw markup (esc() touches only < > & " ')
        $this->assertSame($template, esc($template));
    }

    public function testWaMeLinkCarriesGujaratiTextIntact(): void
    {
        $wa      = new WhatsApp();
        $message = "💖 *નમસ્તે આશા!*\n📅 *તારીખ:* કાલે, સવારે 10:30 💇‍♀️\n_આભાર_";
        $link    = $wa->link('98765 43211', $message);

        $this->assertStringStartsWith('https://wa.me/919876543211?text=', $link);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9%._~:\/?=-]+$/', $link, 'only ASCII in the URL');
        $this->assertSame($message, rawurldecode(substr($link, strlen('https://wa.me/919876543211?text='))));
    }
}
