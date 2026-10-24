<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\I18n\Time;
use Config\Database;
use SQLite3;
use Throwable;

/**
 * php spark db:backup [--keep N] [--dir PATH]
 *
 * Copies the live SQLite database with SQLite's online backup API (consistent even while the app
 * is writing) into writable/backups/beauty_parlour-YYYYmmdd-HHiiss.sqlite and keeps the newest N
 * copies. Meant for a daily cPanel cron job; see docs/deployment.md section 9.
 */
class DbBackup extends BaseCommand
{
    protected $group       = 'Maintenance';
    protected $name        = 'db:backup';
    protected $description = 'Backs up the SQLite database into writable/backups/ and keeps the newest N copies.';
    protected $usage       = 'db:backup [--keep N] [--dir PATH]';
    protected $options     = [
        '--keep' => 'How many backup files to keep (default 14).',
        '--dir'  => 'Target directory (default writable/backups).',
    ];

    public function run(array $params)
    {
        $keep = max(1, (int) ($params['keep'] ?? CLI::getOption('keep') ?? 14));
        $dir  = rtrim((string) ($params['dir'] ?? CLI::getOption('dir') ?? WRITEPATH . 'backups'), '/\\');

        $db = Database::connect();
        $db->initialize();

        if ($db->getPlatform() !== 'SQLite3' || ! $db->connID instanceof SQLite3) {
            CLI::error('db:backup only supports the SQLite3 driver (current: ' . $db->getPlatform() . ').');

            return EXIT_ERROR;
        }

        if (! is_dir($dir) && ! @mkdir($dir, 0700, true)) {
            CLI::error("Cannot create backup directory: {$dir}");

            return EXIT_ERROR;
        }
        @chmod($dir, 0700);

        $file = $dir . DIRECTORY_SEPARATOR . 'beauty_parlour-' . Time::now()->format('Ymd-His') . '.sqlite';

        try {
            $dest = new SQLite3($file);
            $ok   = $db->connID->backup($dest);
            $dest->close();
        } catch (Throwable $e) {
            $ok = false;
            CLI::error('Backup failed: ' . $e->getMessage());
        }

        if (! $ok) {
            @unlink($file);
            CLI::error("Backup NOT written: {$file}");

            return EXIT_ERROR;
        }

        @chmod($file, 0600);

        $files = glob($dir . DIRECTORY_SEPARATOR . 'beauty_parlour-*.sqlite') ?: [];
        rsort($files, SORT_STRING);
        $deleted = 0;

        foreach (array_slice($files, $keep) as $old) {
            if (@unlink($old)) {
                $deleted++;
            }
        }

        CLI::write(sprintf(
            '[%s] backup written: %s (%s KB); keeping %d, deleted %d old file(s)',
            Time::now()->toDateTimeString(),
            basename($file),
            number_format(filesize($file) / 1024),
            min($keep, count($files)),
            $deleted,
        ));

        return EXIT_SUCCESS;
    }
}
