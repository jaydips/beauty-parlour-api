<?php

declare(strict_types=1);

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

        $this->seeInDatabase('settings', ['key' => 'tpl_whatsapp_booking', 'value' => SettingsSeeder::DEFAULTS['tpl_whatsapp_booking']]);
        $this->seeInDatabase('settings', ['key' => 'tpl_whatsapp_reminder', 'value' => SettingsSeeder::DEFAULTS['tpl_whatsapp_reminder']]);
        $this->seeInDatabase('settings', ['key' => 'tpl_whatsapp_birthday', 'value' => SettingsSeeder::DEFAULTS['tpl_whatsapp_birthday']]);
        $this->seeInDatabase('settings', ['key' => 'tpl_whatsapp_followup', 'value' => $customised]);

        // running it again changes nothing; down() restores the English texts it replaced
        $migration->up();
        $this->assertSame(1, $settings->where('value', $customised)->countAllResults());
        $migration->down();
        $this->seeInDatabase('settings', ['key' => 'tpl_whatsapp_booking', 'value' => $english['tpl_whatsapp_booking'][0]]);
        $this->seeInDatabase('settings', ['key' => 'tpl_whatsapp_followup', 'value' => $customised]);
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
