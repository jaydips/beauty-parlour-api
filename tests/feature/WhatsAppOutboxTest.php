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
    private string $uploadsDir;
    private array $ownerSession = ['owner_id' => 1, 'owner_name' => 'Jalpa Patel'];

    protected function setUp(): void
    {
        parent::setUp();
        (new OwnerModel())->saveOwner(['name' => 'Jalpa Patel', 'phone' => '9033319266', 'password_hash' => OwnerAuth::hashPassword('secret123')]);

        // CIUnitTestCase resets config objects between tests but not shared services: build the outbox and
        // its helpers afresh so they see this test's config and the fake bridge (never a stale instance)
        Services::resetSingle('outbox', 'whatsapp', 'campaignImage', 'appointments', 'dashboard', 'superglobals');
        $this->bridge = new FakeWhatsAppBridge();
        Services::injectMock('whatsappBridge', $this->bridge);

        $config                        = config('WhatsApp');
        $config->gapSeconds            = 0;
        $config->gapSecondsMax         = 0;
        $config->campaignGapSeconds    = 0;
        $config->campaignGapSecondsMax = 0;
        $config->campaignWarmupHours   = 0;
        $config->uploadsDir            = $this->uploadsDir = WRITEPATH . 'uploads-test-' . bin2hex(random_bytes(3));

        // services that read Config\WhatsApp get exactly this instance (a request may otherwise resolve
        // an older cached config object from a previous test)
        Services::injectMock('campaignImage', new \App\Libraries\CampaignImage($config));
        Services::injectMock('outbox', new \App\Libraries\Outbox(config: $config));

        $settings = new SettingModel();
        $settings->setValue('wa_auto_send', '1');
        $settings->setValue('wa_send_from', '00:00');
        $settings->setValue('wa_send_to', '00:00'); // equal = any time
    }

    protected function tearDown(): void
    {
        if (is_dir($this->uploadsDir)) {
            array_map('unlink', glob($this->uploadsDir . '/*') ?: []);
            rmdir($this->uploadsDir);
        }
        $_FILES = [];
        Services::resetSingle('superglobals');
        parent::tearDown();
    }

    public function testCompletingAnAppointmentFromTheApiSendsTheReviewMessageThroughTheOutbox(): void
    {
        $cid       = (new CustomerModel())->insert(['name' => 'Asha', 'phone' => '9876543211', 'whatsapp_opt_in' => 1]);
        $serviceId = (new ServiceModel())->insert(['name' => 'Hair Spa', 'price' => 800, 'duration_minutes' => 60, 'is_active' => 1]);
        $a         = service('appointments')->create(['customer_id' => $cid, 'starts_at' => Time::now()->subHours(2)->toDateTimeString()], [['service_id' => $serviceId]]);
        (new MessageModel())->builder()->where('appointment_id', $a['id'])->update(['status' => 'sent', 'sent_at' => Time::now()->toDateTimeString()]); // booking confirmation already out

        $login = $this->withBodyFormat('json')->post('auth/login', ['phone' => '9033319266', 'password' => 'secret123']);
        $token = json_decode($login->getJSON(), true, 512, JSON_THROW_ON_ERROR)['token'];
        $done  = $this->withHeaders(['Authorization' => 'Bearer ' . $token])->withBodyFormat('json')->patch("appointments/{$a['id']}/status", ['status' => 'completed']);
        $done->assertStatus(200);
        $this->seeInDatabase('reminders', ['appointment_id' => $a['id'], 'type' => 'review', 'status' => 'pending']);

        $out = $this->runCommand('whatsapp:send');
        $this->assertStringContainsString('reminders queued 1, sent 1, failed 0', $out);
        $this->assertSame('9876543211', $this->bridge->sent[0]['to']);
        $this->assertStringStartsWith("🌸 મારું બ્યુટી પાર્લર 🌸\n\nપ્રિય *Asha* ❤️", $this->bridge->sent[0]['text']);
        $this->assertArrayNotHasKey('image_url', $this->bridge->sent[0]);
        $this->seeInDatabase('messages', ['appointment_id' => $a['id'], 'type' => 'review', 'status' => 'sent']);
        $this->seeInDatabase('reminders', ['appointment_id' => $a['id'], 'type' => 'review', 'status' => 'sent']);
    }

    public function testDailyCapDefersTheRestAndJitterSpreadsCampaignRecipients(): void
    {
        $customers = new CustomerModel();
        for ($i = 1; $i <= 4; $i++) {
            $customers->insert(['name' => "C{$i}", 'phone' => '987654321' . $i, 'whatsapp_opt_in' => 1]);
        }
        (new SettingModel())->setValue('wa_daily_cap', '3');

        // campaign gaps are random inside [min, max]
        $config = config('WhatsApp');
        $config->campaignGapSeconds    = 30;
        $config->campaignGapSecondsMax = 90;
        $start  = Time::parse('2026-11-01 10:00:00');
        $result = service('outbox')->createCampaign('Diwali', 'Hi {customer_name}', 'all', null, $start);
        $this->assertSame(4, $result['recipients']);
        $this->seeInDatabase('campaigns', ['id' => $result['id'], 'scheduled_at' => '2026-11-01 10:00:00']);
        $times = array_column((new MessageModel())->where('campaign_id', $result['id'])->orderBy('id', 'ASC')->findAll(), 'scheduled_at');
        $this->assertSame('2026-11-01 10:00:00', $times[0], 'first recipient at the scheduled start');
        for ($i = 1; $i < 4; $i++) {
            $gap = Time::parse($times[$i])->getTimestamp() - Time::parse($times[$i - 1])->getTimestamp();
            $this->assertGreaterThanOrEqual(30, $gap);
            $this->assertLessThanOrEqual(90, $gap);
        }

        // nothing goes out before the scheduled start ...
        $out = $this->runCommand('whatsapp:send');
        $this->assertStringContainsString('sent 0', $out);
        $this->assertCount(0, $this->bridge->sent);

        // ... then at most the daily cap: 3 sent, the 4th waits for tomorrow
        $config->campaignGapSeconds = $config->campaignGapSecondsMax = 0;
        $summary = service('outbox')->process(Time::parse('2026-11-01 12:00:00'));
        $this->assertSame(3, $summary['sent']);
        $this->assertSame(1, $summary['deferred']);
        $summary = service('outbox')->process(Time::parse('2026-11-01 12:01:00'));
        $this->assertSame('daily_cap', $summary['skipped']);
        $this->assertSame(0, $summary['sent']);
        $this->assertSame(1, (new MessageModel())->where('status', 'queued')->countAllResults());
        $this->assertSame(3, service('outbox')->sentToday(Time::now()));
    }

    public function testOfferBroadcastsWaitForTheLinkWarmupButReminderMessagesDoNot(): void
    {
        $config = config('WhatsApp');
        $config->campaignWarmupHours = 24;
        $cid = (new CustomerModel())->insert(['name' => 'Asha', 'phone' => '9876543211', 'whatsapp_opt_in' => 1]);
        (new ReminderModel())->insert(['customer_id' => $cid, 'type' => 'custom', 'due_at' => Time::now()->subMinutes(1)->toDateTimeString(), 'channel' => 'whatsapp', 'message' => 'Hi', 'status' => 'pending']);
        service('outbox')->createCampaign('Offer', 'Deal {customer_name}', 'all', null);

        // first run after linking: the link time is recorded, the reminder goes, the offer waits
        $out = $this->runCommand('whatsapp:send');
        $this->assertStringContainsString('sent 1, failed 0, deferred 1 (campaign_warmup)', $out);
        $this->assertSame('Hi', $this->bridge->sent[0]['text']);
        $this->assertNotNull(service('outbox')->linkedSince());

        // 24 hours later the offer goes out
        (new SettingModel())->setValue('wa_linked_since', Time::now()->subHours(25)->toDateTimeString());
        $this->assertTrue(service('outbox')->campaignsAllowed(Time::now()));
        $out = $this->runCommand('whatsapp:send');
        $this->assertStringContainsString('sent 1, failed 0, deferred 0', $out);
        $this->assertSame('Deal Asha', $this->bridge->sent[1]['text']);

        // unlinking forgets the link time; a bridge that reports "not linked" does too
        service('outbox')->forgetLink();
        $this->assertNull(service('outbox')->linkedSince());
        $this->assertFalse(service('outbox')->campaignsAllowed(Time::now()));
    }

    public function testCampaignWithImageIsStoredResizedAndSentAsCaptionedPicture(): void
    {
        (new CustomerModel())->insert(['name' => 'Asha', 'phone' => '9876543211', 'whatsapp_opt_in' => 1]);

        // a 2400x1200 PNG upload -> 1600x800 JPEG inside the uploads dir
        $png = tempnam(sys_get_temp_dir(), 'bp') . '.png';
        $im  = imagecreatetruecolor(2400, 1200);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 50, 120));
        imagepng($im, $png);
        imagedestroy($im);
        $login = $this->withBodyFormat('json')->post('auth/login', ['phone' => '9033319266', 'password' => 'secret123']);
        $token = json_decode($login->getJSON(), true, 512, JSON_THROW_ON_ERROR)['token'];

        // the request reads uploads from the superglobals service, which snapshots $_FILES when created
        $_FILES = ['image' => ['name' => 'offer.png', 'type' => 'image/png', 'tmp_name' => $png, 'error' => UPLOAD_ERR_OK, 'size' => filesize($png)]];
        Services::resetSingle('superglobals');
        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $token])->post('campaigns', ['title' => 'Diwali', 'body' => 'Hi {customer_name}', 'audience' => 'all', 'scheduled_at' => '2026-11-01 10:00:00']);
        $this->assertSame(201, $res->response()->getStatusCode(), (string) $res->getJSON());
        $json = json_decode($res->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $id   = (int) $json['campaign']['id'];
        $this->assertSame(base_url('uploads/campaigns/' . $id . '.jpg'), $json['campaign']['image_url']);
        $this->assertStringStartsWith('http', $json['campaign']['image_url']);
        $this->assertSame('2026-11-01 10:00:00', $json['campaign']['scheduled_at']);

        $this->assertSame($this->uploadsDir, service('campaignImage')->dir(), 'the request used this test\'s uploads directory');
        $file = $this->uploadsDir . DIRECTORY_SEPARATOR . $id . '.jpg';
        $this->assertFileExists($file);
        [$w, $h, $type] = getimagesize($file);
        $this->assertSame([1600, 800, IMAGETYPE_JPEG], [$w, $h, $type]);
        $this->seeInDatabase('campaigns', ['id' => $id, 'image_path' => 'uploads/campaigns/' . $id . '.jpg']);

        // list and detail carry image_url; the bridge gets the picture with the text as caption
        $list = json_decode($this->withHeaders(['Authorization' => 'Bearer ' . $token])->get('campaigns')->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($json['campaign']['image_url'], $list['campaigns'][0]['image_url']);
        $summary = service('outbox')->process(Time::parse('2026-11-01 10:00:00'));
        $this->assertSame(1, $summary['sent']);
        $this->assertSame(['to' => '9876543211', 'text' => 'Hi Asha', 'image_url' => $json['campaign']['image_url']], $this->bridge->sent[0]);

        // bad uploads are refused before anything is queued
        file_put_contents($png, 'not an image');
        $_FILES['image']['size'] = filesize($png);
        Services::resetSingle('superglobals');
        $bad = $this->withHeaders(['Authorization' => 'Bearer ' . $token])->post('campaigns', ['title' => 'Bad', 'body' => 'x', 'audience' => 'all']);
        $bad->assertStatus(400);
        $this->assertSame(1, (new \App\Models\CampaignModel())->countAllResults());
        unlink($png);
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

    public function testBookingConfirmationIsGujaratiFollowsRescheduleAndIsCancelledWithTheAppointment(): void
    {
        $customers = new CustomerModel();
        $cid       = $customers->insert(['name' => 'આશા', 'phone' => '9876543211', 'whatsapp_opt_in' => 1]);
        $haircut   = (new ServiceModel())->insert(['name' => 'Haircut', 'price' => 500, 'duration_minutes' => 45, 'is_active' => 1]);
        $facial    = (new ServiceModel())->insert(['name' => 'Facial', 'price' => 1200, 'duration_minutes' => 60, 'is_active' => 1]);
        $starts    = Time::parse('2026-11-07 10:30:00'); // Saturday

        $a   = service('appointments')->create(['customer_id' => $cid, 'starts_at' => $starts->toDateTimeString()], [['service_id' => $haircut]]);
        $row = (new MessageModel())->where('appointment_id', $a['id'])->where('type', 'booking')->first();
        $this->assertNotNull($row);
        $this->assertSame('queued', $row['status']);
        $this->assertStringContainsString('નમસ્તે આશા', $row['body']);
        $this->assertStringContainsString('મારું બ્યુટી પાર્લર', $row['body']);
        $this->assertStringContainsString('📅 *તારીખ:* 07/11/2026', $row['body'], '{date} is dd/mm/yyyy');
        $this->assertStringContainsString('સવારે 10:30', $row['body'], '{time} is Gujarati');
        $this->assertStringContainsString('Haircut', $row['body']);
        $this->assertStringNotContainsString('{', $row['body'], 'every placeholder is replaced');

        // moving the appointment rewrites the queued confirmation (never a second one)
        service('appointments')->update((int) $a['id'], ['starts_at' => '2026-11-08 18:00:00'], [['service_id' => $facial]]);
        $rows = (new MessageModel())->where('appointment_id', $a['id'])->where('type', 'booking')->findAll();
        $this->assertCount(1, $rows);
        $this->assertSame('queued', $rows[0]['status']);
        $this->assertStringContainsString('08/11/2026', $rows[0]['body']);
        $this->assertStringContainsString('સાંજે 6:00', $rows[0]['body']);
        $this->assertStringContainsString('Facial', $rows[0]['body']);
        $this->assertStringNotContainsString('Haircut', $rows[0]['body']);

        // the cron sends exactly that Gujarati text through the bridge
        $out = $this->runCommand('whatsapp:send');
        $this->assertStringContainsString('sent 1, failed 0', $out);
        $this->assertSame('9876543211', $this->bridge->sent[0]['to']);
        $this->assertSame($rows[0]['body'], $this->bridge->sent[0]['text']);
        $this->seeInDatabase('messages', ['id' => $rows[0]['id'], 'status' => 'sent']);

        // a cancelled appointment cancels what is still queued for it (confirmation + reminder), sent rows stay
        $b = service('appointments')->create(['customer_id' => $cid, 'starts_at' => '2026-11-09 11:00:00'], [['service_id' => $haircut]]);
        (new ReminderModel())->builder()->where('appointment_id', $b['id'])->update(['due_at' => Time::now()->subMinutes(1)->toDateTimeString()]);
        $this->assertSame(1, service('outbox')->enqueueDueReminders(Time::now()), 'the appointment reminder is queued too');
        $this->assertSame(2, (new MessageModel())->where('appointment_id', $b['id'])->where('status', 'queued')->countAllResults());

        service('appointments')->changeStatus((int) $b['id'], AppointmentModel::STATUS_CANCELLED, 'બીમાર');
        $this->assertSame(0, (new MessageModel())->where('appointment_id', $b['id'])->where('status', 'queued')->countAllResults());
        $this->assertSame(2, (new MessageModel())->where('appointment_id', $b['id'])->where('status', 'cancelled')->countAllResults());
        $this->seeInDatabase('messages', ['id' => $rows[0]['id'], 'status' => 'sent']);

        $again = $this->runCommand('whatsapp:send');
        $this->assertStringContainsString('sent 0', $again);
        $this->assertCount(1, $this->bridge->sent, 'cancelled rows are never sent');
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
        $this->assertSame('Hi Asha, 20% off at મારું બ્યુટી પાર્લર this week!', $row['body']);

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
        $page->assertSee('ઑટો WhatsApp');

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
        $this->assertSame(150, $json['pacing']['daily_cap']);
        $this->assertSame(0, $json['pacing']['sent_today']);
        $this->assertSame([0, 0], $json['pacing']['gap_seconds'], 'setUp zeroes the gaps; production defaults are [4, 12]');
        $this->assertSame(8, $json['pacing']['per_run']);
        $this->assertArrayHasKey('campaigns_allowed', $json['pacing']);

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

    public function testStatusAndTestCommandsTalkToTheBridge(): void
    {
        $status = $this->runCommand('whatsapp:status');
        $this->assertStringContainsString('registered=yes me=919033319266', $status);
        $this->assertStringContainsString('auto_send=on', $status);
        $this->assertStringContainsString('outbox: queued 0 (due now 0)', $status);

        $bad = $this->runCommand('whatsapp:test --to 123');
        $this->assertStringContainsString('10-digit', $bad);
        $this->assertCount(0, $this->bridge->sent);

        $ok = $this->runCommand('whatsapp:test --to 9876543210');
        $this->assertStringContainsString('sent to 9876543210: id=ABC123', $ok);
        $this->assertCount(1, $this->bridge->sent);
        $this->assertStringContainsString('મારું બ્યુટી પાર્લર', $this->bridge->sent[0]['text']);

        $this->bridge->sendReply = ['ok' => false, 'error' => 'not_connected'];
        $fail = $this->runCommand('whatsapp:test --to 9876543210');
        $this->assertStringContainsString('NOT sent to 9876543210: not_connected', $fail);
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
