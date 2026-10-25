<?php

declare(strict_types=1);

use App\Database\Seeds\SettingsSeeder;
use App\Libraries\OwnerAuth;
use App\Models\CustomerModel;
use App\Models\OwnerModel;
use App\Models\ReminderModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Browser (session) side of the application: login page, CSRF, dashboard, reminder buttons.
 *
 * @internal
 */
final class WebUiTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = SettingsSeeder::class;

    public function testAppRedirectsToLoginWhenSignedOut(): void
    {
        $res = $this->get('app');
        $res->assertStatus(302);
        $this->assertStringContainsString('/login', (string) $res->getRedirectUrl());
    }

    public function testSiteRootOpensTheLoginPageOrTheDashboard(): void
    {
        $res = $this->get('/');
        $res->assertStatus(302);
        $this->assertStringEndsWith('/login', (string) $res->getRedirectUrl());

        $signedIn = $this->withSession(['owner_id' => 1, 'owner_name' => 'Jalpa Patel'])->get('/');
        $signedIn->assertStatus(302);
        $this->assertStringEndsWith('/app', rtrim((string) $signedIn->getRedirectUrl(), '/'));

        // API clients still get the JSON root
        $json = $this->withHeaders(['Accept' => 'application/json'])->get('/');
        $json->assertStatus(200);
        $this->assertSame('beauty-parlour-api', json_decode($json->getJSON(), true, 512, JSON_THROW_ON_ERROR)['app']);
    }

    public function testLoginPageRenders(): void
    {
        $res = $this->get('login');
        $res->assertStatus(200);
        $res->assertSee('માલિક લોગિન');
        $res->assertSee('name="password"', null);
        $res->assertSee('csrf_token', null);
    }

    public function testLoginRejectsWrongPasswordAndAcceptsRightOne(): void
    {
        $this->createOwner('secret123');

        $wrong = $this->post('login', ['phone' => '9033319266', 'password' => 'nope', csrf_token() => csrf_hash()]);
        $wrong->assertStatus(302);
        $this->assertStringNotContainsString('/app', (string) $wrong->getRedirectUrl());

        $ok = $this->post('login', ['phone' => '9033319266', 'password' => 'secret123', csrf_token() => csrf_hash()]);
        $ok->assertStatus(302);
        $this->assertStringEndsWith('/app', rtrim((string) $ok->getRedirectUrl(), '/'));
    }

    public function testLoginWithoutCsrfTokenIsRejected(): void
    {
        $this->createOwner('secret123');

        // Outside production the CSRF filter throws instead of redirecting (Config\Security::$redirect).
        $this->expectException(\CodeIgniter\Security\Exceptions\SecurityException::class);
        $this->post('login', ['phone' => '9033319266', 'password' => 'secret123']);
    }

    public function testDashboardRendersForSignedInOwner(): void
    {
        $this->createOwner('secret123');

        $res = $this->withSession(['owner_id' => 1, 'owner_name' => 'Jalpa Patel'])->get('app');
        $res->assertStatus(200);
        $res->assertSee('હોમ');
        $res->assertSee('મારું બ્યુટી પાર્લર');
        $res->assertSee('Jalpa Patel');
        $res->assertSee('WhatsApp રિમાઇન્ડર');
    }

    public function testReminderSentButtonMarksReminder(): void
    {
        $this->createOwner('secret123');
        $customerId = (new CustomerModel())->insert(['name' => 'Asha', 'phone' => '9876543211', 'whatsapp_opt_in' => 1]);
        $reminderId = (new ReminderModel())->insert([
            'customer_id' => $customerId, 'type' => 'custom', 'due_at' => Time::now()->toDateTimeString(),
            'channel' => 'whatsapp', 'message' => 'Hello Asha', 'status' => 'pending',
        ]);

        $page = $this->withSession(['owner_id' => 1])->get('app');
        $page->assertStatus(200);
        $page->assertSee('Hello Asha');
        $page->assertSee('https://wa.me/919876543211', null);

        $res = $this->withSession(['owner_id' => 1])->post("app/reminders/{$reminderId}/sent", [csrf_token() => csrf_hash()]);
        $res->assertStatus(302);
        $this->seeInDatabase('reminders', ['id' => $reminderId, 'status' => 'sent']);

        $after = $this->withSession(['owner_id' => 1])->get('app');
        $after->assertDontSee('Hello Asha');
    }

    private function createOwner(string $password): void
    {
        (new OwnerModel())->saveOwner([
            'name'          => 'Jalpa Patel',
            'phone'         => '9033319266',
            'password_hash' => OwnerAuth::hashPassword($password),
        ]);
    }
}
