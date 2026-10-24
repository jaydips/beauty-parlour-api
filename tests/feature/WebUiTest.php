<?php

declare(strict_types=1);

use App\Database\Seeds\SettingsSeeder;
use App\Libraries\OwnerAuth;
use App\Models\CustomerModel;
use App\Models\OwnerModel;
use App\Models\ReminderModel;
use App\Models\ServiceModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Browser UI (session + CSRF) against the in-memory SQLite "tests" group:
 * login redirect, login form, dashboard render, reminder buttons, CSRF, manifest, HTML 404.
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

    private const PHONE    = '9876543210';
    private const PASSWORD = 'secret123';

    public function testAppRedirectsToLoginWithoutSession(): void
    {
        $this->createOwner();

        $res = $this->get('app');
        $res->assertRedirect();
        $this->assertStringEndsWith('/login', $res->getRedirectUrl());

        // a stale session (wrong key) is thrown away too
        $this->withSession([OwnerAuth::SESSION_OWNER_ID => 1, OwnerAuth::SESSION_OWNER_KEY => 'stale'])->get('app')->assertRedirect();
    }

    public function testLoginPageRendersInGujarati(): void
    {
        $this->createOwner();

        $res = $this->get('login');
        $res->assertStatus(200);
        $res->assertSee('લોગિન કરો');
        $res->assertSee('ફોન નંબર');
        $res->assertSee('name="' . csrf_token() . '"');
    }

    public function testLoginFailsWithWrongPasswordAndSucceedsWithRightOne(): void
    {
        $this->createOwner();

        $bad = $this->post('login', ['phone' => self::PHONE, 'password' => 'nope', csrf_token() => csrf_hash()]);
        $bad->assertStatus(401);
        $bad->assertSee('ખોટો');
        $this->assertArrayNotHasKey(OwnerAuth::SESSION_OWNER_ID, $_SESSION);

        $ok = $this->post('login', ['phone' => '98765 43210', 'password' => self::PASSWORD, csrf_token() => csrf_hash()]);
        $ok->assertRedirectTo('app');
        $ok->assertSessionHas(OwnerAuth::SESSION_OWNER_ID, 1);
        $this->assertNotEmpty($_SESSION[OwnerAuth::SESSION_OWNER_KEY]);

        $session = $_SESSION;

        $dash = $this->withSession($session)->get('app');
        $dash->assertStatus(200);
        $dash->assertSee('ડેશબોર્ડ');
        $dash->assertSee('આજની અપોઇન્ટમેન્ટ');
        $dash->assertSee('WhatsApp રિમાઇન્ડર');

        // the login page sends a logged-in owner straight to the app
        $this->withSession($session)->get('login')->assertRedirectTo('app');

        // logout clears the session and the dashboard is protected again
        $out = $this->withSession($session)->post('logout', [csrf_token() => csrf_hash()]);
        $out->assertRedirect();
        $this->assertArrayNotHasKey(OwnerAuth::SESSION_OWNER_ID, $_SESSION);
    }

    public function testDashboardShowsDueReminderWithWhatsAppLinkAndMarksItSent(): void
    {
        $this->createOwner();
        $customerId = (new CustomerModel())->insert(['name' => 'Asha', 'phone' => '9876543211', 'whatsapp_opt_in' => 1]);
        $reminderId = (new ReminderModel())->insert([
            'customer_id' => $customerId,
            'type'        => 'custom',
            'due_at'      => Time::now()->toDateTimeString(),
            'channel'     => 'whatsapp',
            'message'     => 'Hello Asha, see you soon!',
            'status'      => 'pending',
        ]);

        $dash = $this->withSession($this->session())->get('app');
        $dash->assertStatus(200);
        $dash->assertSee('Asha');
        $dash->assertSee('Hello Asha, see you soon!');
        $this->assertStringContainsString('https://wa.me/919876543211?text=Hello', $dash->response()->getBody());
        $dash->assertSee('મોકલાઈ ગયું');

        $sent = $this->withSession($this->session())->post("app/reminders/{$reminderId}/status", [
            'status'     => 'sent',
            'return'     => '/app',
            csrf_token() => csrf_hash(),
        ]);
        $sent->assertRedirectTo('app');
        $this->seeInDatabase('reminders', ['id' => $reminderId, 'status' => 'sent']);

        $this->withSession($this->session())->get('app')->assertDontSee('Hello Asha, see you soon!');
    }

    public function testWebPostsWithoutCsrfTokenAreRejected(): void
    {
        $this->createOwner();

        $this->expectException(SecurityException::class);
        $this->withSession($this->session())->post('app/reminders/1/status', ['status' => 'sent']);
    }

    public function testManifestAndHtml404(): void
    {
        $this->createOwner();

        $manifest = $this->get('manifest.webmanifest');
        $manifest->assertStatus(200);
        $this->assertStringContainsString('application/manifest+json', $manifest->response()->getHeaderLine('Content-Type'));
        $json = json_decode($manifest->response()->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertStringEndsWith('/app', $json['start_url']);
        $this->assertSame('standalone', $json['display']);

        $html = $this->withSession($this->session())->get('app/does-not-exist');
        $html->assertStatus(404);
        $this->assertStringContainsString('text/html', $html->response()->getHeaderLine('Content-Type'));
        $html->assertSee('પેજ મળ્યું નહીં');

        $json404 = $this->get('nope/nothing');
        $json404->assertStatus(404);
        $this->assertStringContainsString('application/json', $json404->response()->getHeaderLine('Content-Type'));
    }

    public function testApiKeepsWorkingNextToTheWebUi(): void
    {
        $this->createOwner();
        (new ServiceModel())->insert(['name' => 'Haircut', 'duration_minutes' => 30, 'price' => 300]);

        $login = $this->withBodyFormat('json')->post('auth/login', ['phone' => self::PHONE, 'password' => self::PASSWORD]);
        $login->assertStatus(200);
        $token = json_decode($login->getJSON(), true, 512, JSON_THROW_ON_ERROR)['token'];

        $dash = $this->withHeaders(['Authorization' => 'Bearer ' . $token])->get('dashboard');
        $dash->assertStatus(200);
        $this->assertSame(1, json_decode($dash->getJSON(), true, 512, JSON_THROW_ON_ERROR)['counts']['active_services']);
    }

    private function createOwner(): void
    {
        (new OwnerModel())->saveOwner([
            'name'          => 'Jalpa',
            'phone'         => self::PHONE,
            'password_hash' => OwnerAuth::hashPassword(self::PASSWORD),
        ]);
    }

    /**
     * A valid browser session for the owner created by createOwner().
     *
     * @return array<string, mixed>
     */
    private function session(): array
    {
        $owner = (new OwnerModel())->getOwner();

        return [
            OwnerAuth::SESSION_OWNER_ID  => (int) $owner['id'],
            OwnerAuth::SESSION_OWNER_KEY => substr(hash('sha256', 'owner-key:' . $owner['password_hash']), 0, 32),
        ];
    }
}
