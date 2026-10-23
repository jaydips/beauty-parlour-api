<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Seeds\SettingsSeeder;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;
use Config\Services;
use Throwable;

/**
 * Web-triggered migration runner for shared hosting without SSH.
 *
 * Both routes answer 404 unless `.env` defines `install.key` (at least 32
 * characters) AND the request carries the same value in `?key=`. Remove or
 * comment out `install.key` on the server once migrations have been applied.
 *
 *   GET /install/status?key=...   -> applied and pending migrations
 *   GET /install/migrate?key=...  -> run pending migrations + SettingsSeeder
 */
class Install extends BaseController
{
    use ResponseTrait;

    private const MIN_KEY_LENGTH = 32;

    public function status(): ResponseInterface
    {
        if (! $this->authorized()) {
            return $this->failNotFound();
        }

        return $this->respond($this->report());
    }

    public function migrate(): ResponseInterface
    {
        if (! $this->authorized()) {
            return $this->failNotFound();
        }

        try {
            $runner = Services::migrations();
            $runner->setSilent(true);
            $runner->latest();

            $seeder = Database::seeder();
            $seeder->setSilent(true);
            $seeder->call(SettingsSeeder::class);
        } catch (Throwable $e) {
            log_message('error', 'Install::migrate failed: {message}', ['message' => $e->getMessage()]);

            return $this->fail(['migrated' => false, 'error' => $e->getMessage()], 500);
        }

        return $this->respond(['migrated' => true] + $this->report());
    }

    private function authorized(): bool
    {
        $expected = (string) env('install.key', '');
        $given    = $this->request->getGet('key');

        return strlen($expected) >= self::MIN_KEY_LENGTH
            && is_string($given)
            && hash_equals($expected, $given);
    }

    /**
     * @return array<string, mixed>
     */
    private function report(): array
    {
        $runner = Services::migrations();
        $runner->setSilent(true);

        $applied = [];

        foreach ($runner->getHistory() as $row) {
            $applied[] = ['version' => $row->version, 'class' => $row->class, 'batch' => (int) $row->batch];
        }

        $appliedVersions = array_column($applied, 'version');
        $pending         = [];

        foreach ($runner->findMigrations() as $migration) {
            if (! in_array($migration->version, $appliedVersions, true)) {
                $pending[] = ['version' => $migration->version, 'class' => $migration->class];
            }
        }

        $db = Database::connect();

        return [
            'database' => ['driver' => $db->getPlatform(), 'version' => $db->getVersion()],
            'applied'  => $applied,
            'pending'  => $pending,
        ];
    }
}
