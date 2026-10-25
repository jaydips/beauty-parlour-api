<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\SettingModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\I18n\Time;

/**
 * php spark whatsapp:send [--limit N]
 *
 * Every-minute cron job: queues due reminders as outbox messages and delivers queued messages
 * (booking confirmations, reminders, offers) through the WhatsApp bridge, a few per run, inside the
 * owner's send window. Does nothing while settings.wa_auto_send is "0".
 */
class WhatsappSend extends BaseCommand
{
    protected $group       = 'Maintenance';
    protected $name        = 'whatsapp:send';
    protected $description = 'Sends queued WhatsApp messages (reminders, booking confirmations, offers) through the Baileys bridge.';
    protected $usage       = 'whatsapp:send [--limit N]';
    protected $options     = ['--limit' => 'Maximum messages for this run (default: Config\WhatsApp::$perRun).'];

    public function run(array $params)
    {
        $limit   = (int) ($params['limit'] ?? CLI::getOption('limit') ?? 0);
        $now     = Time::now();
        $summary = service('outbox')->process($now, $limit > 0 ? $limit : null);
        $line    = sprintf(
            'reminders queued %d, sent %d, failed %d, deferred %d%s',
            $summary['queued_reminders'],
            $summary['sent'],
            $summary['failed'],
            $summary['deferred'],
            $summary['skipped'] !== null ? ' (' . $summary['skipped'] . ')' : '',
        );

        // heartbeat for /health?deep=1 and GET /whatsapp/status: proves the cron job runs and shows why
        // nothing went out (auto_send_off, outside_send_window, bridge_not_linked, daily_cap, ...)
        $settings = new SettingModel();
        $settings->setValue('wa_cron_last_run', $now->toDateTimeString());
        $settings->setValue('wa_cron_last_result', $line);

        CLI::write('[' . $now->toDateTimeString() . '] ' . $line);

        return EXIT_SUCCESS;
    }
}
