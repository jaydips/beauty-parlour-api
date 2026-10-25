<?php

declare(strict_types=1);

use App\Database\Seeds\ServicesSeeder;
use App\Database\Seeds\SettingsSeeder;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Runs the real App migrations against the in-memory SQLite "tests" group and
 * checks the schema, the settings seeder and the foreign-key behaviour.
 *
 * @internal
 */
final class SchemaMigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = SettingsSeeder::class;

    private const TABLES = [
        'owner', 'settings', 'services', 'customers',
        'appointments', 'appointment_services', 'payments', 'reminders',
    ];

    public function testAllPhaseOneTablesExist(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertTrue($this->db->tableExists($table), "table {$table} missing");
        }
    }

    public function testSettingsAreSeededIdempotently(): void
    {
        $this->seeInDatabase('settings', ['key' => 'country_code', 'value' => '91']);
        $this->seeInDatabase('settings', ['key' => 'timezone', 'value' => 'Asia/Kolkata']);

        $before = $this->db->table('settings')->countAllResults();
        (new SettingsSeeder(config('Database'), $this->db))->run();
        $after = $this->db->table('settings')->countAllResults();

        $this->assertSame($before, $after, 'seeder must not duplicate keys');
        $this->assertSame(count(SettingsSeeder::DEFAULTS), $after);
    }

    public function testGujaratiTemplateMigrationReplacesOnlyUntouchedEnglishDefaults(): void
    {
        require_once APPPATH . 'Database/Migrations/2026-10-25-100001_GujaratiTemplateDefaults.php';

        $english   = \App\Database\Migrations\GujaratiTemplateDefaults::REPLACEMENTS;
        $settings  = $this->db->table('settings');
        $customised = 'Hello {customer_name}, see you at {parlour_name}!';

        // an existing installation: three untouched English defaults, one template the owner edited
        $settings->where('key', 'tpl_whatsapp_booking')->update(['value' => $english['tpl_whatsapp_booking'][0]]);
        $settings->where('key', 'tpl_whatsapp_reminder')->update(['value' => $english['tpl_whatsapp_reminder'][0]]);
        $settings->where('key', 'tpl_whatsapp_birthday')->update(['value' => $english['tpl_whatsapp_birthday'][0]]);
        $settings->where('key', 'tpl_whatsapp_followup')->update(['value' => $customised]);

        $migration = new \App\Database\Migrations\GujaratiTemplateDefaults(\Config\Database::forge($this->db));
        $migration->up();

        // 100001 writes the plain Gujarati texts (120001 upgrades them to the seeder's emoji texts next)
        $this->seeInDatabase('settings', ['key' => 'tpl_whatsapp_booking', 'value' => $english['tpl_whatsapp_booking'][1]]);
        $this->seeInDatabase('settings', ['key' => 'tpl_whatsapp_reminder', 'value' => $english['tpl_whatsapp_reminder'][1]]);
        $this->seeInDatabase('settings', ['key' => 'tpl_whatsapp_birthday', 'value' => $english['tpl_whatsapp_birthday'][1]]);
        $this->seeInDatabase('settings', ['key' => 'tpl_whatsapp_followup', 'value' => $customised]);

        // running it again changes nothing; down() restores the English texts it replaced
        $migration->up();
        $this->assertSame(1, $settings->where('value', $customised)->countAllResults());
        $migration->down();
        $this->seeInDatabase('settings', ['key' => 'tpl_whatsapp_booking', 'value' => $english['tpl_whatsapp_booking'][0]]);
        $this->seeInDatabase('settings', ['key' => 'tpl_whatsapp_followup', 'value' => $customised]);
    }

    public function testTemplateMigrationChainEndsAtTheSeederDefaults(): void
    {
        // every template migration looks for exactly what the previous one wrote, and the last one
        // writes the seeder's defaults: fresh and upgraded installations end up identical
        $chain = [];

        foreach ($this->templateMigrations() as $class) {
            $chain[] = $class::REPLACEMENTS;
        }

        $lastWrite = [];

        foreach ($chain as $i => $replacements) {
            foreach ($replacements as $key => $texts) {
                if (isset($lastWrite[$key])) {
                    $this->assertSame($lastWrite[$key], $texts[0], "{$key}: migration {$i} must continue the previous one");
                }
                $lastWrite[$key] = $texts[1];
            }
        }

        foreach ($lastWrite as $key => $text) {
            $this->assertSame(SettingsSeeder::DEFAULTS[$key], $text, "{$key}: the last migration that touches it writes the seeder default");
            $this->assertStringContainsString('*', $text, "{$key}: WhatsApp bold markup");
            $this->assertStringContainsString('{parlour_name}', $text);
        }
        $this->assertArrayHasKey('tpl_whatsapp_review', $lastWrite);
    }

    public function testOwnerLinksMigrationFillsOnlyBlankSettings(): void
    {
        [, , , $class] = $this->templateMigrations();
        $links    = $class::OWNER_LINKS;
        $settings = $this->db->table('settings');

        // Google link blank (filled), Instagram typed by the owner (kept), draft review template (replaced)
        $settings->where('key', 'google_review_url')->update(['value' => '']);
        $settings->where('key', 'instagram_url')->update(['value' => 'https://instagram.com/mine']);
        $settings->where('key', 'tpl_whatsapp_review')->update(['value' => $class::REPLACEMENTS['tpl_whatsapp_review'][0]]);

        $migration = new $class(\Config\Database::forge($this->db));
        $migration->up();
        $this->seeInDatabase('settings', ['key' => 'google_review_url', 'value' => $links['google_review_url']]);
        $this->seeInDatabase('settings', ['key' => 'instagram_url', 'value' => 'https://instagram.com/mine']);
        $this->seeInDatabase('settings', ['key' => 'tpl_whatsapp_review', 'value' => SettingsSeeder::DEFAULTS['tpl_whatsapp_review']]);

        // a missing row is inserted; running again changes nothing; down() clears only the owner's values
        $settings->where('key', 'instagram_url')->delete();
        $migration->up();
        $this->seeInDatabase('settings', ['key' => 'instagram_url', 'value' => $links['instagram_url']]);
        $migration->up();
        $this->assertSame(1, $this->db->table('settings')->where('key', 'instagram_url')->countAllResults());
        $migration->down();
        $this->seeInDatabase('settings', ['key' => 'google_review_url', 'value' => '']);
        $this->seeInDatabase('settings', ['key' => 'instagram_url', 'value' => '']);
        $this->seeInDatabase('settings', ['key' => 'tpl_whatsapp_review', 'value' => $class::REPLACEMENTS['tpl_whatsapp_review'][0]]);
    }

    public function testEmojiTemplateMigrationUpgradesOnlyThePreviousDefaults(): void
    {
        [, $emojiClass, $signatureClass] = $this->templateMigrations();
        $emoji     = $emojiClass::REPLACEMENTS;
        $signature = $signatureClass::REPLACEMENTS;

        $settings   = $this->db->table('settings');
        $customised = "મારો પોતાનો મેસેજ {customer_name} 💖";

        // an upgraded installation: four rows still at the plain Gujarati default, one edited by the owner
        foreach (['tpl_whatsapp_booking', 'tpl_whatsapp_reminder', 'tpl_whatsapp_followup', 'tpl_whatsapp_offer'] as $key) {
            $settings->where('key', $key)->update(['value' => $emoji[$key][0]]);
        }
        $settings->where('key', 'tpl_whatsapp_birthday')->update(['value' => $customised]);

        $migration = new $emojiClass(\Config\Database::forge($this->db));
        $migration->up();

        foreach (['tpl_whatsapp_booking', 'tpl_whatsapp_reminder', 'tpl_whatsapp_followup', 'tpl_whatsapp_offer'] as $key) {
            $this->seeInDatabase('settings', ['key' => $key, 'value' => $emoji[$key][1]]);
        }
        $this->seeInDatabase('settings', ['key' => 'tpl_whatsapp_birthday', 'value' => $customised]);

        // the signature migration takes the chain to the current defaults, still skipping the edited row
        $next = new $signatureClass(\Config\Database::forge($this->db));
        $next->up();

        foreach (['tpl_whatsapp_booking', 'tpl_whatsapp_reminder', 'tpl_whatsapp_followup', 'tpl_whatsapp_offer'] as $key) {
            $this->seeInDatabase('settings', ['key' => $key, 'value' => SettingsSeeder::DEFAULTS[$key]]);
            $this->assertStringContainsString('{parlour_phone}', $signature[$key][1]);
        }
        $this->seeInDatabase('settings', ['key' => 'tpl_whatsapp_birthday', 'value' => $customised]);

        $next->down();
        $migration->down();
        $this->seeInDatabase('settings', ['key' => 'tpl_whatsapp_offer', 'value' => $emoji['tpl_whatsapp_offer'][0]]);
        $this->seeInDatabase('settings', ['key' => 'tpl_whatsapp_birthday', 'value' => $customised]);
    }

    public function testServicesSeederInsertsTheStandardListOnceAndNeverTouchesExistingRows(): void
    {
        $services = $this->db->table('services');
        $services->insert(['name' => 'HAIRCUT (women)', 'price' => 999, 'duration_minutes' => 50, 'is_active' => 0, 'sort_order' => 7]);
        $services->insert(['name' => 'Pedicure', 'price' => 650, 'duration_minutes' => 60, 'is_active' => 1, 'deleted_at' => '2026-10-01 10:00:00']);

        $seeder = new ServicesSeeder(config('Database'), $this->db);
        $seeder->run();

        $this->assertSame(count(ServicesSeeder::SERVICES) - 2, $seeder->inserted, 'case-insensitive and soft-deleted names are skipped');
        $this->assertSame(2, $seeder->skipped);
        $this->assertSame(count(ServicesSeeder::SERVICES), $services->countAllResults());
        $this->seeInDatabase('services', ['name' => 'HAIRCUT (women)', 'price' => 999, 'is_active' => 0, 'sort_order' => 7]);
        $this->seeInDatabase('services', ['name' => 'Pedicure', 'price' => 650]);
        $this->seeInDatabase('services', ['name' => 'Hair Spa', 'category' => 'વાળ', 'price' => 800, 'duration_minutes' => 60, 'is_active' => 1, 'sort_order' => 6]);
        $this->seeInDatabase('services', ['name' => 'Rica Wax – Full Arms', 'category' => 'વેક્સિંગ', 'price' => 500]);
        $this->seeInDatabase('services', ['name' => 'Saree Pre-pleating', 'category' => 'અન્ય', 'sort_order' => count(ServicesSeeder::SERVICES)]);

        $seeder->run();
        $this->assertSame(0, $seeder->inserted, 'second run inserts nothing');
        $this->assertSame(count(ServicesSeeder::SERVICES), $this->db->table('services')->countAllResults());

        $names = array_map(static fn (array $s): string => mb_strtolower($s[1]), ServicesSeeder::SERVICES);
        $this->assertSame(count($names), count(array_unique($names)), 'the list itself has no duplicate names');
    }

    /**
     * Template migrations in order; the files are outside the composer classmap, hence require_once.
     *
     * @return list<class-string>
     */
    private function templateMigrations(): array
    {
        require_once APPPATH . 'Database/Migrations/2026-10-25-100001_GujaratiTemplateDefaults.php';
        require_once APPPATH . 'Database/Migrations/2026-10-25-120001_EmojiTemplateDefaults.php';
        require_once APPPATH . 'Database/Migrations/2026-10-25-130001_SignatureTemplateDefaults.php';
        require_once APPPATH . 'Database/Migrations/2026-10-25-140001_ReviewTemplateAndOwnerLinks.php';

        return [
            \App\Database\Migrations\GujaratiTemplateDefaults::class,
            \App\Database\Migrations\EmojiTemplateDefaults::class,
            \App\Database\Migrations\SignatureTemplateDefaults::class,
            \App\Database\Migrations\ReviewTemplateAndOwnerLinks::class,
        ];
    }

    public function testCustomerPhoneIsUnique(): void
    {
        $this->insertCustomer('9876543210');

        $this->expectException(DatabaseException::class);
        $this->insertCustomer('9876543210');
    }

    public function testDeletingAppointmentCascadesToItemsAndCustomerDeleteIsRestricted(): void
    {
        $customerId    = $this->insertCustomer('9000000001');
        $appointmentId = $this->insertAppointment($customerId);

        $this->db->table('appointment_services')->insert([
            'appointment_id'   => $appointmentId,
            'service_id'       => null,
            'service_name'     => 'Haircut',
            'duration_minutes' => 30,
            'price'            => 300,
            'quantity'         => 1,
        ]);
        $this->seeInDatabase('appointment_services', ['appointment_id' => $appointmentId]);

        // RESTRICT: customer with an appointment cannot be hard-deleted
        try {
            $this->db->table('customers')->where('id', $customerId)->delete();
            $this->fail('deleting a customer with appointments must be rejected');
        } catch (DatabaseException $e) {
            $this->assertStringContainsStringIgnoringCase('foreign key', $e->getMessage());
        }

        // CASCADE: deleting the appointment removes its line items
        $this->db->table('appointments')->where('id', $appointmentId)->delete();
        $this->dontSeeInDatabase('appointment_services', ['appointment_id' => $appointmentId]);
    }

    public function testForeignKeysAreEnabledOnTheConnection(): void
    {
        $this->assertSame(1, (int) $this->db->query('PRAGMA foreign_keys')->getRow()->foreign_keys);
    }

    private function insertCustomer(string $phone): int
    {
        $this->db->table('customers')->insert([
            'name'            => 'Test Customer',
            'phone'           => $phone,
            'whatsapp_opt_in' => 1,
        ]);

        return (int) $this->db->insertID();
    }

    private function insertAppointment(int $customerId): int
    {
        $this->db->table('appointments')->insert([
            'customer_id' => $customerId,
            'starts_at'   => '2026-11-01 10:00:00',
            'ends_at'     => '2026-11-01 10:30:00',
            'status'      => 'scheduled',
            'subtotal'    => 300,
            'discount'    => 0,
            'total'       => 300,
        ]);

        return (int) $this->db->insertID();
    }
}
