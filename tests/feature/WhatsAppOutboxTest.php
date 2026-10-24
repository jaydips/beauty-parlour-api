<?php

declare(strict_types=1);

use App\Database\Seeds\SettingsSeeder;
use App\Libraries\OwnerAuth;
use App\Libraries\WhatsAppBridge;
use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Models\MessageModel;
use App\Models\OwnerModel;
use App\Models\ReminderModel;
use App\Models\ServiceModel;
use App\Models\SettingModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\StreamFilterTrait;
use Config\Services;

/**
 * Scripted stand-in for the Node bridge: records what would be sent.
 */
final class FakeWhatsAppBridge extends WhatsAppBridge
{
    public array $sent        = [];
    public array $statusReply = ['ok' => true, 'registered' => true, 'connection' => 'open', 'me' => '919033319266', 'sent_total' => 0];
    public array $sendReply   = ['ok' => true, 'id' => 'ABC123'];

    public function configured(): bool
    {
        return true;
    }

    protected function call(string $method, string $path, array $body = []): array
    {
        if ($path === '/status') {
            return $this->statusReply + ['http' => 200];
        }
        if ($path === '/send') {
            $this->sent[] = $body;

            return $this->sendReply + ['http' => 200];
        }
        if ($path === '/pair') {
            return ['ok' => true, 'pairing_code' => 'ABCD1234', 'http' => 200];
        }

        return ['ok' => true, 'http' => 200];
    }
}

/**
 * Automatic WhatsApp: outbox queueing, cron delivery through the (fake) bridge, campaigns, web + API.
 *
 * @internal
 */
final class WhatsAppOutboxTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use StreamFilterTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = SettingsSeeder::class;

    private FakeWhatsAppBridge $bridge;
    private array $ownerSession = ['owner_id' => 1, 'owner_name' => 'Jalpa Patel'];

    protected function setUp(): void
    {
        parent::setUp();
        (new OwnerModel())->saveOwner(['name' => 'Jalpa Patel', 'phone' => '9033319266', 'password_hash' => OwnerAuth::hashPassword('secret123')]);

        $this->bridge = new FakeWhatsAppBridge();
        Services::injectMock('whatsappBridge', $this->bridge);

        $config                     = config('WhatsApp');
        $config->gapSeconds         = 0;
        $config->campaignGapSeconds = 0;

        $settings = new SettingModel();
        $settings->setValue('wa_auto_send', '1');
        $settings->setValue('wa_send_from', '00:00');
        $settings->setValue('wa_send_to', '00:00'); // equal = any time
    }

    public function testBookingConfirmationIsQueuedOnlyWhenAutoSendIsOn(): void
    {
        $customers = new CustomerModel();
        $optedIn   = $customers->insert(['name' => 'Asha', 'phone' => '9876543211', 'whatsapp_opt_in' => 1]);
        $optedOut  = $customers->insert(['name' => 'Bina', 'phone' => '9876543212', 'whatsapp_opt_in' => 0]);
        $serviceId = (new ServiceModel())->insert(['name' => 'Haircut', 'price' => 500, 'duration_minutes' => 45, 'is_active' => 1]);
        $tomorrow  = Time::now()->addDays(1)->setTime(11, 0, 0)->toDateTimeString();

        $a = service('appointments')->create(['customer_id' => $optedIn, 'starts_at' => $tomorrow], [['service_id' => $serviceId]]);
        $this->seeInDatabase('messages', ['appointment_id' => $a['id'], 'type' => 'booking', 'status' => 'queued', 'phone' => '9876543211']);
        $row = (new MessageModel())->where('appointment_id', $a['id'])->first();
        $this->assertStringContainsString('Asha', $row['body']);
        $this->assertStringContainsString('Haircut', $row['body']);

        $b = service('appointments')->create(['customer_id' => $optedOut, 'starts_at' => Time::now()->addDays(2)->setTime(11, 0, 0)->toDateTimeString()], [['service_id' => $serviceId]]);
        $this->dontSeeInDatabase('messages', ['appointment_id' => $b['id']]);

        (new SettingModel())->setValue('wa_auto_send', '0');
        $c = service('appointments')->create(['customer_id' => $optedIn, 'starts_at' => Time::now()->addDays(3)->setTime(11, 0, 0)->toDateTimeString()], [['service_id' => $serviceId]]);
        $this->dontSeeInDatabase('messages', ['appointment_id' => $c['id']]);
    }

    public function testCronSendsDueRemindersThroughTheBridgeOnce(): void
    {
        $cid = (new CustomerModel())->insert(['name' => 'Falguni', 'phone' => '9876543213', 'whatsapp_opt_in' => 1]);
        $rid = (new ReminderModel())->insert(['customer_id' => $cid, 'type' => 'follow_up', 'due_at' => Time::now()->subMinutes(5)->toDateTimeString(), 'channel' => 'whatsapp', 'message' => 'Hello Falguni, time for your next visit!', 'status' => 'pending']);
        (new ReminderModel())->insert(['customer_id' => $cid, 'type' => 'custom', 'due_at' => Time::now()->addDays(1)->toDateTimeString(), 'channel' => 'whatsapp', 'message' => 'Not due yet', 'status' => 'pending']);

        $out = $this->runCommand('whatsapp:send');
        $this->assertStringContainsString('reminders queued 1, sent 1, failed 0, deferred 0', $out);
        $this->assertCount(1, $this->bridge->sent);
        $this->assertSame(['to' => '9876543213', 'text' => 'Hello Falguni, time for your next visit!'], $this->bridge->sent[0]);
        $this->seeInDatabase('messages', ['reminder_id' => $rid, 'status' => 'sent', 'wa_id' => 'ABC123', 'attempts' => 1]);
        $this->seeInDatabase('reminders', ['id' => $rid, 'status' => 'sent']);
        $this->assertSame(1, (new MessageModel())->countAllResults(), 'the future reminder is not queued yet');

        $again = $this->runCommand('whatsapp:send');
        $this->assertStringContainsString('reminders queued 0, sent 0', $again);
        $this->assertCount(1, $this->bridge->sent, 'nothing is sent twice');
    }

    public function testMessagesWaitWhileTheBridgeIsNotLinkedAndFailOnFatalErrors(): void
    {
        $cid = (new CustomerModel())->insert(['name' => 'Asha', 'phone' => '9876543211', 'whatsapp_opt_in' => 1]);
        $customer = (new CustomerModel())->find($cid);
        $mid      = service('outbox')->enqueue($customer, 'custom', 'Hi Asha');

        $this->bridge->statusReply = ['ok' => true, 'registered' => false, 'connection' => 'waiting_for_link'];
        $out = $this->runCommand('whatsapp:send');
        $this->assertStringContainsString('deferred 1 (bridge_not_linked)', $out);
        $this->seeInDatabase('messages', ['id' => $mid, 'status' => 'queued', 'attempts' => 0]);
        $this->assertCount(0, $this->bridge->sent);

        $this->bridge->statusReply = ['ok' => true, 'registered' => true, 'connection' => 'open'];
        $this->bridge->sendReply   = ['ok' => false, 'error' => 'not_on_whatsapp'];
        $out = $this->runCommand('whatsapp:send');
        $this->assertStringContainsString('failed 1', $out);
        $this->seeInDatabase('messages', ['id' => $mid, 'status' => 'failed', 'last_error' => 'not_on_whatsapp', 'attempts' => 1]);

        // outside the send window nothing is attempted
        (new MessageModel())->requeue($mid);
        $now = Time::now();
        (new SettingModel())->setValue('wa_send_from', $now->addHours(1)->format('H:i'));
        (new SettingModel())->setValue('wa_send_to', $now->addHours(2)->format('H:i'));
        $this->bridge->sendReply = ['ok' => true, 'id' => 'X'];
        $out = $this->runCommand('whatsapp:send');
        $this->assertStringContainsString('(outside_send_window)', $out);
        $this->seeInDatabase('messages', ['id' => $mid, 'status' => 'queued']);
    }

    public function testCampaignQueuesOneMessagePerOptedInCustomerAndCanBeCancelled(): void
    {
        $customers = new CustomerModel();
        $customers->insert(['name' => 'Asha', 'phone' => '9876543211', 'whatsapp_opt_in' => 1]);
        $customers->insert(['name' => 'Bina', 'phone' => '9876543212', 'whatsapp_opt_in' => 1]);
        $customers->insert(['name' => 'No WhatsApp', 'phone' => '9876543213', 'whatsapp_opt_in' => 0]);

        $this->withSession($this->ownerSession)->get('app/campaigns/new')->assertSee('(2)');

        $res = $this->withSession($this->ownerSession)->post('app/campaigns', $this->csrf(['title' => 'Diwali offer', 'body' => 'Hi {customer_name}, 20% off at {parlour_name} this week!', 'audience' => 'all']));
        $res->assertStatus(302);
        $this->seeInDatabase('campaigns', ['title' => 'Diwali offer', 'recipients' => 2, 'status' => 'queued']);
        $this->assertSame(2, (new MessageModel())->where('type', 'offer')->countAllResults());
        $this->seeInDatabase('messages', ['phone' => '9876543211', 'type' => 'offer', 'status' => 'queued']);
        $row = (new MessageModel())->where('phone', '9876543211')->first();
        $this->assertSame('Hi Asha, 20% off at My Beauty Parlour this week!', $row['body']);

        $page = $this->withSession($this->ownerSession)->get('app/campaigns/1');
        $page->assertStatus(200);
        $page->assertSee('Diwali offer');

        $out = $this->runCommand('whatsapp:send --limit 1');
        $this->assertStringContainsString('sent 1', $out);
        $this->seeInDatabase('campaigns', ['id' => 1, 'sent' => 1, 'status' => 'queued']);

        $this->withSession($this->ownerSession)->post('app/campaigns/1/cancel', $this->csrf([]))->assertStatus(302);
        $this->seeInDatabase('campaigns', ['id' => 1, 'status' => 'cancelled']);
        $this->assertSame(1, (new MessageModel())->where('status', 'cancelled')->countAllResults());
    }

    public function testWebWhatsAppPageAndApiEndpoints(): void
    {
        $page = $this->withSession($this->ownerSession)->get('app/whatsapp');
        $page->assertStatus(200);
        $page->assertSee('9033319266');
        $page->assertSee('Auto-send');

        $this->withSession($this->ownerSession)->post('app/whatsapp/auto-send', $this->csrf(['enabled' => '0']))->assertStatus(302);
        $this->seeInDatabase('settings', ['key' => 'wa_auto_send', 'value' => '0']);

        $login = $this->withBodyFormat('json')->post('auth/login', ['phone' => '9033319266', 'password' => 'secret123']);
        $login->assertStatus(200);
        $token = json_decode($login->getJSON(), true, 512, JSON_THROW_ON_ERROR)['token'];
        $auth  = ['Authorization' => 'Bearer ' . $token];

        $status = $this->withHeaders($auth)->get('whatsapp/status');
        $status->assertStatus(200);
        $json = json_decode($status->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($json['bridge']['registered']);
        $this->assertSame('919033319266', $json['bridge']['me']);
        $this->assertFalse($json['auto_send']);

        $cid = (new CustomerModel())->insert(['name' => 'Asha', 'phone' => '9876543211', 'whatsapp_opt_in' => 1]);

        $created = $this->withHeaders($auth)->withBodyFormat('json')->post('messages', ['customer_id' => $cid, 'body' => 'Custom hello']);
        $created->assertStatus(201);
        $this->seeInDatabase('messages', ['customer_id' => $cid, 'type' => 'custom', 'status' => 'queued']);

        $preview = $this->withHeaders($auth)->get('campaigns/preview?audience=all');
        $preview->assertStatus(200);
        $this->assertSame(1, json_decode($preview->getJSON(), true, 512, JSON_THROW_ON_ERROR)['recipients']);

        $campaign = $this->withHeaders($auth)->withBodyFormat('json')->post('campaigns', ['title' => 'API offer', 'body' => 'Hello {customer_name}', 'audience' => 'inactive', 'days' => 30]);
        $campaign->assertStatus(201);
        $json = json_decode($campaign->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, (int) $json['campaign']['recipients']);
        $this->assertSame(1, $json['counts']['queued']);

        $list = $this->withHeaders($auth)->get('messages?status=queued');
        $list->assertStatus(200);
        $this->assertCount(2, json_decode($list->getJSON(), true, 512, JSON_THROW_ON_ERROR)['messages']);

        $pair = $this->withHeaders($auth)->withBodyFormat('json')->post('whatsapp/pair', ['phone' => '9033319266']);
        $pair->assertStatus(200);
        $this->assertSame('ABCD1234', json_decode($pair->getJSON(), true, 512, JSON_THROW_ON_ERROR)['pairing_code']);
    }

    /** CLI::write() goes to STDOUT, so collect it through the test stream filter. */
    private function runCommand(string $command): string
    {
        $this->resetStreamFilterBuffer();
        command($command);

        return $this->getStreamFilterBuffer();
    }

    private function csrf(array $data): array
    {
        return $data + [csrf_token() => csrf_hash()];
    }
}
