<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\MessageModel;
use App\Models\ReminderModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\I18n\Time;

/**
 * php spark whatsapp:status
 *
 * One-screen health check for operations (also usable from a one-off cron job on the host):
 * bridge link state, the auto-send switch and send window, outbox counts and what is due right now.
 */
class WhatsappStatus extends BaseCommand
{
    protected $group       = 'Maintenance';
    protected $name        = 'whatsapp:status';
    protected $description = 'Shows the WhatsApp bridge state, the auto-send switch and the outbox counts.';
    protected $usage       = 'whatsapp:status';

    public function run(array $params)
    {
        $bridge = service('whatsappBridge');
        $outbox = service('outbox');
        $now    = Time::now();

        if (! $bridge->configured()) {
            CLI::error('bridge: NOT configured (whatsapp.bridgeKey is empty in .env)');
        } else {
            $s = $bridge->status();
            CLI::write(sprintf(
                'bridge: ok=%s connection=%s registered=%s me=%s last_error=%s since=%s sent_total=%d (http %d)',
                $this->yn($s['ok'] ?? false),
                (string) ($s['connection'] ?? '-'),
                $this->yn($s['registered'] ?? false),
                (string) ($s['me'] ?? '-'),
                (string) ($s['last_error'] ?? ($s['error'] ?? '-')),
                (string) ($s['since'] ?? '-'),
                (int) ($s['sent_total'] ?? 0),
                (int) ($s['http'] ?? 0),
            ));
        }

        [$from, $to] = $outbox->sendWindow();
        CLI::write(sprintf(
            'auto_send=%s window=%s-%s now=%s in_window=%s',
            $outbox->autoSendEnabled() ? 'on' : 'off',
            $from,
            $to,
            $now->toDateTimeString(),
            $this->yn($outbox->inSendWindow($now)),
        ));

        $messages = new MessageModel();
        $counts   = $messages->counts();
        $dueNow   = $messages->where('status', 'queued')->where('scheduled_at <=', $now->toDateTimeString())->countAllResults();
        CLI::write(sprintf(
            'outbox: queued %d (due now %d), sent %d, failed %d, cancelled %d; pending reminders due %d',
            $counts['queued'],
            $dueNow,
            $counts['sent'],
            $counts['failed'],
            $counts['cancelled'],
            count((new ReminderModel())->due($now->toDateTimeString())),
        ));

        return EXIT_SUCCESS;
    }

    private function yn(mixed $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
