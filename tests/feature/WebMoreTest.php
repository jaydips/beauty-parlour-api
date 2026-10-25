<?php

declare(strict_types=1);

use App\Database\Seeds\SettingsSeeder;
use App\Libraries\OwnerAuth;
use App\Models\CustomerModel;
use App\Models\OwnerModel;
use App\Models\PaymentModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Web UI secondary screens: services, payments, reminders, settings, password, logout.
 *
 * @internal
 */
final class WebMoreTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = SettingsSeeder::class;

    private array $ownerSession = ['owner_id' => 1, 'owner_name' => 'Jalpa Patel'];

    protected function setUp(): void
    {
        parent::setUp();
        (new OwnerModel())->saveOwner(['name' => 'Jalpa Patel', 'phone' => '9033319266', 'password_hash' => OwnerAuth::hashPassword('secret123')]);
    }

    public function testServicesCrud(): void
    {
        $this->withSession($this->ownerSession)->get('app/services')->assertSee('હજુ કોઈ સેવા નથી');

        $this->withSession($this->ownerSession)->post('app/services', $this->csrf(['name' => 'Haircut', 'price' => '500', 'duration_minutes' => '45', 'is_active' => '1']))->assertStatus(302);
        $this->seeInDatabase('services', ['name' => 'Haircut', 'price' => 500, 'is_active' => 1]);

        $this->withSession($this->ownerSession)->post('app/services/1', $this->csrf(['name' => 'Haircut Deluxe', 'price' => '700', 'duration_minutes' => '60']))->assertStatus(302);
        $this->seeInDatabase('services', ['id' => 1, 'name' => 'Haircut Deluxe', 'price' => 700, 'is_active' => 0]);

        $list = $this->withSession($this->ownerSession)->get('app/services');
        $list->assertSee('Haircut Deluxe');
        $list->assertSee('બંધ');

        $this->withSession($this->ownerSession)->post('app/services/1/delete', $this->csrf([]))->assertStatus(302);
        $this->dontSeeInDatabase('services', ['id' => 1, 'deleted_at' => null]);
    }

    public function testPaymentsListAndTotals(): void
    {
        $cid = (new CustomerModel())->insert(['name' => 'Asha', 'phone' => '9876543211']);
        $now = Time::now()->toDateTimeString();
        (new PaymentModel())->insert(['customer_id' => $cid, 'type' => 'payment', 'method' => 'cash', 'amount' => 800, 'paid_at' => $now]);
        (new PaymentModel())->insert(['customer_id' => $cid, 'type' => 'payment', 'method' => 'upi', 'amount' => 500, 'paid_at' => $now]);
        (new PaymentModel())->insert(['customer_id' => $cid, 'type' => 'refund', 'method' => 'cash', 'amount' => 100, 'paid_at' => $now]);

        $page = $this->withSession($this->ownerSession)->get('app/payments');
        $page->assertStatus(200);
        $page->assertSee('1,200');  // net
        $page->assertSee('UPI');
        $page->assertSee('Asha');
    }

    public function testRemindersPageAndCustomReminder(): void
    {
        $cid = (new CustomerModel())->insert(['name' => 'Asha', 'phone' => '9876543211']);

        $this->withSession($this->ownerSession)->post('app/reminders', $this->csrf([
            'customer_id' => (string) $cid, 'message' => 'Namaste Asha', 'date' => Time::now()->toDateString(), 'time' => '09:00',
        ]))->assertStatus(302);
        $this->seeInDatabase('reminders', ['customer_id' => $cid, 'type' => 'custom', 'status' => 'pending']);

        $page = $this->withSession($this->ownerSession)->get('app/reminders?due=all');
        $page->assertStatus(200);
        $page->assertSee('Namaste Asha');
        $page->assertSee('https://wa.me/919876543211', null);

        $this->withSession($this->ownerSession)->post('app/reminders/1/skip', $this->csrf([]))->assertStatus(302);
        $this->seeInDatabase('reminders', ['id' => 1, 'status' => 'skipped']);
    }

    public function testSettingsFormValidatesAndSaves(): void
    {
        $bad = $this->withSession($this->ownerSession)->post('app/settings', $this->csrf(['parlour_name' => 'Delicate', 'country_code' => 'abc']));
        $bad->assertStatus(302);
        $this->dontSeeInDatabase('settings', ['key' => 'parlour_name', 'value' => 'Delicate']);

        $ok = $this->withSession($this->ownerSession)->post('app/settings', $this->csrf(['parlour_name' => 'Delicate', 'reminder_lead_hours' => '12', 'parlour_phone' => '90333 19266']));
        $ok->assertStatus(302);
        $this->seeInDatabase('settings', ['key' => 'parlour_name', 'value' => 'Delicate']);
        $this->seeInDatabase('settings', ['key' => 'reminder_lead_hours', 'value' => '12']);
        $this->seeInDatabase('settings', ['key' => 'parlour_phone', 'value' => '9033319266']);

        $page = $this->withSession($this->ownerSession)->get('app/settings');
        $page->assertStatus(200);
        $page->assertSee('Delicate');
        $page->assertSee('નવી બુકિંગ પર ઑટો WhatsApp');
        $page->assertSee('✅ *નમસ્તે {customer_name}!*'); // raw WhatsApp markup and emoji in the textarea
        $page->assertSee('📞 {parlour_phone}');

        // review / social links: blank by default, validated, saved
        $page->assertSee('name="google_review_url"', null);
        $page->assertSee('name="instagram_url"', null);
        $this->withSession($this->ownerSession)->post('app/settings', $this->csrf(['google_review_url' => 'not a url']))->assertStatus(302);
        $this->seeInDatabase('settings', ['key' => 'google_review_url', 'value' => '']);
        $this->withSession($this->ownerSession)->post('app/settings', $this->csrf(['google_review_url' => 'https://g.page/r/abc/review', 'instagram_url' => 'https://instagram.com/delicate']))->assertStatus(302);
        $this->seeInDatabase('settings', ['key' => 'google_review_url', 'value' => 'https://g.page/r/abc/review']);
        $this->seeInDatabase('settings', ['key' => 'instagram_url', 'value' => 'https://instagram.com/delicate']);

        // emoji and markup round-trip through the form unchanged
        $this->withSession($this->ownerSession)->post('app/settings', $this->csrf(['tpl_whatsapp_offer' => "🎁 *ઑફર* _STOP_ {customer_name}"]))->assertStatus(302);
        $this->seeInDatabase('settings', ['key' => 'tpl_whatsapp_offer', 'value' => "🎁 *ઑફર* _STOP_ {customer_name}"]);

        // the auto-WhatsApp switch on the settings page (checkbox + hidden 0 fallback)
        $this->withSession($this->ownerSession)->post('app/settings', $this->csrf(['wa_auto_send' => '1', 'wa_send_from' => '08:30', 'wa_send_to' => '20:00']))->assertStatus(302);
        $this->seeInDatabase('settings', ['key' => 'wa_auto_send', 'value' => '1']);
        $this->seeInDatabase('settings', ['key' => 'wa_send_from', 'value' => '08:30']);
        $this->withSession($this->ownerSession)->post('app/settings', $this->csrf(['wa_auto_send' => '0']))->assertStatus(302);
        $this->seeInDatabase('settings', ['key' => 'wa_auto_send', 'value' => '0']);
        $this->withSession($this->ownerSession)->post('app/settings', $this->csrf(['wa_send_to' => '25:00']))->assertStatus(302);
        $this->seeInDatabase('settings', ['key' => 'wa_send_to', 'value' => '20:00']);
    }

    public function testPasswordChangeAndLogout(): void
    {
        $this->withSession($this->ownerSession)->post('app/password', $this->csrf(['current_password' => 'wrong', 'new_password' => 'newsecret1', 'confirm_password' => 'newsecret1']))->assertStatus(302);
        $this->assertTrue(password_verify('secret123', (new OwnerModel())->getOwner()['password_hash']));

        $this->withSession($this->ownerSession)->post('app/password', $this->csrf(['current_password' => 'secret123', 'new_password' => 'newsecret1', 'confirm_password' => 'newsecret1']))->assertStatus(302);
        $this->assertTrue(password_verify('newsecret1', (new OwnerModel())->getOwner()['password_hash']));

        $this->withSession($this->ownerSession)->get('app/more')->assertSee('લોગઆઉટ');
        $out = $this->withSession($this->ownerSession)->post('logout', $this->csrf([]));
        $out->assertStatus(302);
        $this->assertStringContainsString('/login', (string) $out->getRedirectUrl());
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function csrf(array $data): array
    {
        return $data + [csrf_token() => csrf_hash()];
    }
}
