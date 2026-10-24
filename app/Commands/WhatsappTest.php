<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\SettingModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark whatsapp:test --to 98XXXXXXXX [--text "..."]
 *
 * Sends ONE message through the bridge right now (no outbox, no auto-send switch) to prove that the
 * linked WhatsApp account can deliver. Meant for a one-off cron job on the host or local checks.
 */
class WhatsappTest extends BaseCommand
{
    protected $group       = 'Maintenance';
    protected $name        = 'whatsapp:test';
    protected $description = 'Sends one test message through the WhatsApp bridge immediately.';
    protected $usage       = 'whatsapp:test --to 98XXXXXXXX [--text "message"]';
    protected $options     = [
        '--to'   => '10-digit Indian mobile number (required).',
        '--text' => 'Message text (default: a short test line with the parlour name).',
    ];

    public function run(array $params)
    {
        $to = preg_replace('/\D+/', '', (string) ($params['to'] ?? CLI::getOption('to') ?? '')) ?? '';

        if (! preg_match('/^[6-9]\d{9}$/', $to)) {
            CLI::error('--to must be a 10-digit Indian mobile number (without country code).');

            return EXIT_ERROR;
        }

        $parlour = (string) (new SettingModel())->value('parlour_name', 'Beauty Parlour');
        $text    = trim((string) ($params['text'] ?? CLI::getOption('text') ?? ''));

        if ($text === '') {
            $text = 'Test message from ' . $parlour . ' - WhatsApp auto-send is working.';
        }

        $bridge = service('whatsappBridge');

        if (! $bridge->configured()) {
            CLI::error('bridge: NOT configured (whatsapp.bridgeKey is empty in .env)');

            return EXIT_ERROR;
        }

        $result = $bridge->send($to, $text);

        if ($result['ok'] ?? false) {
            CLI::write(sprintf('sent to %s: id=%s', $to, (string) ($result['id'] ?? '-')));

            return EXIT_SUCCESS;
        }

        CLI::error(sprintf('NOT sent to %s: %s (http %d)', $to, (string) ($result['error'] ?? 'unknown'), (int) ($result['http'] ?? 0)));

        return EXIT_ERROR;
    }
}
