<?php

declare(strict_types=1);

use App\Database\Seeds\SettingsSeeder;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;

/**
 * End-to-end API flow against the in-memory SQLite "tests" group:
 * owner bootstrap -> login -> price list -> customer -> appointment (+auto reminder, overlap 409)
 * -> status completed (+follow-up reminder) -> payment -> dashboard -> reports -> logout.
 *
 * @internal
 */
final class ApiFlowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = SettingsSeeder::class;

    private const INSTALL_KEY = 'test-install-key-0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['install.key']    = self::INSTALL_KEY;
        $_SERVER['install.key'] = self::INSTALL_KEY;
    }

    protected function tearDown(): void
    {
        unset($_ENV['install.key'], $_SERVER['install.key']);
        parent::tearDown();
    }

    public function testProtectedRoutesRequireBearerToken(): void
    {
        $this->get('dashboard')->assertStatus(401);
        $this->withHeaders(['Authorization' => 'Bearer ' . str_repeat('0', 64)])->get('dashboard')->assertStatus(401);
        $this->get('install/owner')->assertStatus(404);
    }

    public function testOwnerBootstrapAndLogin(): void
    {
        $this->withBodyFormat('json')->post('install/owner?key=wrong', ['name' => 'Owner', 'phone' => '9876543210', 'password' => 'secret123'])->assertStatus(404);

        $res = $this->withBodyFormat('json')->post('install/owner?key=' . self::INSTALL_KEY, ['name' => 'Owner', 'phone' => '9876543210', 'password' => 'secret123', 'email' => 'owner@example.com']);
        $res->assertStatus(201);
        $this->assertArrayNotHasKey('password_hash', $this->json($res)['owner']);

        // second bootstrap is refused
        $this->withBodyFormat('json')->post('install/owner?key=' . self::INSTALL_KEY, ['name' => 'X', 'phone' => '9000000000', 'password' => 'secret123'])->assertStatus(409);

        $this->withBodyFormat('json')->post('auth/login', ['phone' => '9876543210', 'password' => 'wrong-pass'])->assertStatus(401);

        $login = $this->withBodyFormat('json')->post('auth/login', ['phone' => '9876543210', 'password' => 'secret123', 'device' => 'phpunit']);
        $login->assertStatus(200);
        $token = $this->json($login)['token'];
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);

        $me = $this->withHeaders(['Authorization' => 'Bearer ' . $token])->get('auth/me');
        $me->assertStatus(200);
        $this->assertSame('Owner', $this->json($me)['owner']['name']);

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])->post('auth/logout')->assertStatus(200);
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])->get('auth/me')->assertStatus(401);
    }

    public function testBookingPaymentDashboardFlow(): void
    {
        $token = $this->bootstrapAndLogin();
        $auth  = ['Authorization' => 'Bearer ' . $token];

        // price list
        $svc = $this->withHeaders($auth)->withBodyFormat('json')->post('services', ['name' => 'Haircut', 'duration_minutes' => 45, 'price' => 500, 'category' => 'Hair']);
        $svc->assertStatus(201);
        $serviceId = $this->json($svc)['service']['id'];
        $this->withHeaders($auth)->withBodyFormat('json')->post('services', ['name' => 'Facial', 'duration_minutes' => 60, 'price' => 1200])->assertStatus(201);
        $this->withHeaders($auth)->withBodyFormat('json')->post('services', ['name' => '', 'duration_minutes' => 0, 'price' => -1])->assertStatus(400);

        // customer (phone unique, 10 digits)
        $cust = $this->withHeaders($auth)->withBodyFormat('json')->post('customers', ['name' => 'Asha', 'phone' => '98765 43211', 'whatsapp_opt_in' => true]);
        $cust->assertStatus(201);
        $customerId = $this->json($cust)['customer']['id'];
        $this->assertSame('9876543211', $this->json($cust)['customer']['phone']);
        $this->withHeaders($auth)->withBodyFormat('json')->post('customers', ['name' => 'Dup', 'phone' => '9876543211'])->assertStatus(400);

        // appointment tomorrow 10:00 with two items -> ends 11:45, totals 1700 - 100
        $day = Time::now()->addDays(1)->toDateString();
        $apt = $this->withHeaders($auth)->withBodyFormat('json')->post('appointments', [
            'customer_id' => $customerId,
            'starts_at'   => $day . ' 10:00:00',
            'items'       => [['service_id' => $serviceId], ['service_name' => 'Facial', 'price' => 1200, 'duration_minutes' => 60]],
            'discount'    => 100,
            'source'      => 'walk_in',
        ]);
        $apt->assertStatus(201);
        $a = $this->json($apt)['appointment'];
        $this->assertSame($day . ' 11:45:00', $a['ends_at']);
        $this->assertSame(1700, (int) $a['subtotal']);
        $this->assertSame(1600, (int) $a['total']);
        $this->assertCount(2, $a['items']);
        $this->assertSame('Haircut', $a['items'][0]['service_name']);
        $this->assertCount(1, $a['reminders'], 'an appointment reminder is scheduled for opted-in customers');
        $this->assertSame('pending', $a['reminders'][0]['status']);
        $appointmentId = $a['id'];

        // overlapping slot -> 409 with conflicts; force books anyway
        $overlap = ['customer_id' => $customerId, 'starts_at' => $day . ' 11:00:00', 'items' => [['service_id' => $serviceId]]];
        $res = $this->withHeaders($auth)->withBodyFormat('json')->post('appointments', $overlap);
        $res->assertStatus(409);
        $this->assertCount(1, $this->json($res)['messages']['conflicts']);
        $forced = $this->withHeaders($auth)->withBodyFormat('json')->post('appointments', $overlap + ['force' => true]);
        $forced->assertStatus(201);
        $forcedId = $this->json($forced)['appointment']['id'];

        // reminders list carries wa.me links with country code 91
        $rem = $this->withHeaders($auth)->get('reminders?status=pending&due=all');
        $rem->assertStatus(200);
        $this->assertStringStartsWith('https://wa.me/919876543211?text=', $this->json($rem)['reminders'][0]['wa_link']);
        $this->assertStringContainsString('Asha', $this->json($rem)['reminders'][0]['message']);

        // complete -> appointment reminder skipped, follow-up reminder created
        $done = $this->withHeaders($auth)->withBodyFormat('json')->patch("appointments/{$appointmentId}/status", ['status' => 'completed']);
        $done->assertStatus(200);
        $types = array_column($this->json($done)['appointment']['reminders'], 'status', 'type');
        $this->assertSame('skipped', $types['appointment']);
        $this->assertSame('pending', $types['follow_up']);
        $this->assertSame('pending', $types['review'], 'review request created on completion');

        // cancel the forced one with a reason
        $this->withHeaders($auth)->withBodyFormat('json')->patch("appointments/{$forcedId}/status", ['status' => 'cancelled', 'reason' => 'customer called'])->assertStatus(200);

        // payment: advance 1000 by UPI now -> balance 600
        $pay = $this->withHeaders($auth)->withBodyFormat('json')->post('payments', ['appointment_id' => $appointmentId, 'amount' => 1000, 'method' => 'upi', 'reference' => 'UPI123']);
        $pay->assertStatus(201);
        $this->assertSame(600, $this->json($pay)['appointment']['balance']);
        $this->withHeaders($auth)->withBodyFormat('json')->post('payments', ['amount' => 0, 'method' => 'cash'])->assertStatus(400);

        // dashboard: today's revenue 1000, one due with balance 600, tomorrow's list shows the appointments
        $dash = $this->withHeaders($auth)->get('dashboard');
        $dash->assertStatus(200);
        $d = $this->json($dash);
        $this->assertSame(1000, $d['revenue']['today']['net']);
        $this->assertSame(['upi' => 1000], $d['revenue']['today']['by_method']);
        $this->assertSame(1, $d['dues']['count']);
        $this->assertSame(600, $d['dues']['total']);
        $this->assertSame(1, $d['counts']['customers']);
        $this->assertSame(2, $d['counts']['active_services']);
        // the appointment reminder was skipped on completion and the forced booking's reminder was cancelled;
        // the review request of the completed appointment is due right away
        $this->assertSame(1, $d['reminders_due']['count']);
        $this->assertSame('review', $d['reminders_due']['list'][0]['type']);
        $this->assertStringContainsString('Google', $d['reminders_due']['list'][0]['message']);
        $reviewId = $d['reminders_due']['list'][0]['id'];
        $this->withHeaders($auth)->post("reminders/{$reviewId}/sent")->assertStatus(200);
        $this->assertSame(0, $this->json($this->withHeaders($auth)->get('dashboard'))['reminders_due']['count']);

        // a custom reminder due now shows up on the dashboard with a click-to-chat link
        $custom = $this->withHeaders($auth)->withBodyFormat('json')->post('reminders', ['customer_id' => $customerId, 'message' => 'Hello Asha, see you soon!', 'due_at' => Time::now()->toDateTimeString()]);
        $custom->assertStatus(201);
        $customId = $this->json($custom)['reminder']['id']; // read before the next request reuses the response object
        $d2 = $this->json($this->withHeaders($auth)->get('dashboard'));
        $this->assertSame(1, $d2['reminders_due']['count']);
        $this->assertStringStartsWith('https://wa.me/919876543211?text=Hello', $d2['reminders_due']['list'][0]['wa_link']);
        $this->withHeaders($auth)->post("reminders/{$customId}/sent")->assertStatus(200);
        $this->assertSame(0, $this->json($this->withHeaders($auth)->get('dashboard'))['reminders_due']['count']);

        $tomorrow = $this->json($this->withHeaders($auth)->get('dashboard?date=' . $day));
        $this->assertSame(2, $tomorrow['appointments']['counts']['total']);
        $this->assertSame(1, $tomorrow['appointments']['counts']['completed']);
        $this->assertSame(1, $tomorrow['appointments']['counts']['cancelled']);
        $this->assertSame('Asha', $tomorrow['appointments']['list'][0]['customer']['name']);

        // reports
        $rev = $this->withHeaders($auth)->get('reports/revenue?group=day');
        $rev->assertStatus(200);
        $this->assertSame(1000, $this->json($rev)['totals']['net']);

        $top = $this->withHeaders($auth)->get('reports/services?from=' . $day . '&to=' . $day);
        $top->assertStatus(200);
        $this->assertSame('Facial', $this->json($top)['services'][0]['service_name']);
        $this->assertSame(1200, $this->json($top)['services'][0]['amount']);

        $dues = $this->withHeaders($auth)->get('reports/dues');
        $dues->assertStatus(200);
        $this->assertSame(600, $this->json($dues)['dues'][0]['balance']);

        // appointment with payments cannot be deleted
        $this->withHeaders($auth)->delete("appointments/{$appointmentId}")->assertStatus(409);

        // customer detail statistics
        $detail = $this->withHeaders($auth)->get("customers/{$customerId}");
        $detail->assertStatus(200);
        $this->assertSame(1, $this->json($detail)['customer']['stats']['visits']);
        $this->assertSame(1000, $this->json($detail)['customer']['stats']['total_spent']);

        // settings update with validation
        $this->withHeaders($auth)->withBodyFormat('json')->put('settings', ['parlour_name' => 'Delicate Beauty', 'country_code' => 'abc'])->assertStatus(400);
        $set = $this->withHeaders($auth)->withBodyFormat('json')->put('settings', ['parlour_name' => 'Delicate Beauty', 'reminder_lead_hours' => '12']);
        $set->assertStatus(200);
        $this->assertSame('Delicate Beauty', $this->json($set)['settings']['parlour_name']);

        // review / social links are plain settings keys (blank by default, validated as URLs)
        $this->assertSame('', $this->json($set)['settings']['google_review_url']);
        $this->assertSame('', $this->json($set)['settings']['instagram_url']);
        $this->assertContains('tpl_whatsapp_review', $this->json($set)['editable_keys']);
        $this->withHeaders($auth)->withBodyFormat('json')->put('settings', ['google_review_url' => 'g.page/no-scheme'])->assertStatus(400);
        $links = $this->withHeaders($auth)->withBodyFormat('json')->put('settings', ['google_review_url' => 'https://g.page/r/abc/review', 'instagram_url' => 'https://instagram.com/delicate']);
        $links->assertStatus(200);
        $this->assertSame('https://g.page/r/abc/review', $this->json($links)['settings']['google_review_url']);
        $this->assertSame('https://instagram.com/delicate', $this->json($this->withHeaders($auth)->get('settings'))['settings']['instagram_url']);
    }

    /**
     * Decoded JSON body of a feature-test response.
     *
     * @return array<string, mixed>
     */
    private function json(TestResponse $response): array
    {
        return json_decode($response->getJSON(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function bootstrapAndLogin(): string
    {
        $this->withBodyFormat('json')->post('install/owner?key=' . self::INSTALL_KEY, ['name' => 'Owner', 'phone' => '9876543210', 'password' => 'secret123'])->assertStatus(201);
        $login = $this->withBodyFormat('json')->post('auth/login', ['phone' => '9876543210', 'password' => 'secret123']);
        $login->assertStatus(200);

        return $this->json($login)['token'];
    }
}
