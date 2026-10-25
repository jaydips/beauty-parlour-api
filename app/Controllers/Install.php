<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Seeds\ServicesSeeder;
use App\Database\Seeds\SettingsSeeder;
use App\Libraries\OwnerAuth;
use App\Models\OwnerModel;
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
 *   GET /install/migrate?key=...  -> run pending migrations + SettingsSeeder + ServicesSeeder (both idempotent)
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

            // the standard price list: inserts only names that do not exist yet (idempotent per deploy)
            $services = new ServicesSeeder(config(Database::class));
            $services->run();
        } catch (Throwable $e) {
            log_message('error', 'Install::migrate failed: {message}', ['message' => $e->getMessage()]);

            return $this->fail(['migrated' => false, 'error' => $e->getMessage()], 500);
        }

        return $this->respond(['migrated' => true, 'services_seeded' => ['inserted' => $services->inserted, 'existing' => $services->skipped]] + $this->report());
    }

    /**
     * POST /install/owner?key=...  {name, phone, password, email?}
     * One-time creation of the owner login. Refuses (409) once an owner exists;
     * afterwards use PUT /auth/profile and PUT /auth/password with a bearer token.
     */
    public function owner(): ResponseInterface
    {
        if (! $this->authorized()) {
            return $this->failNotFound();
        }

        $owners = new OwnerModel();

        if ($owners->getOwner() !== null) {
            return $this->failResourceExists('The owner already exists. Use /auth endpoints to change it.');
        }

        $data  = $this->request->getJSON(true);
        $data  = is_array($data) ? $data : $this->request->getPost();
        $rules = [
            'name'     => 'required|string|max_length[100]',
            'phone'    => 'required|regex_match[/^[0-9]{10}$/]',
            'password' => 'required|min_length[8]|max_length[200]',
            'email'    => 'permit_empty|valid_email|max_length[190]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $owners->saveOwner([
            'name'          => $data['name'],
            'phone'         => $data['phone'],
            'email'         => $data['email'] ?? null,
            'password_hash' => OwnerAuth::hashPassword($data['password']),
        ]);

        return $this->respondCreated(['owner' => OwnerAuth::publicOwner($owners->getOwner())]);
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
