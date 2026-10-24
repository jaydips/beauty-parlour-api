<?php

declare(strict_types=1);

use App\Database\Seeds\SettingsSeeder;
use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Models\ReminderModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\StreamFilterTrait;

/**
 * spark db:backup and spark reminders:generate against the in-memory SQLite "tests" group.
 *
 * @internal
 */
final class MaintenanceCommandsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use StreamFilterTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = SettingsSeeder::class;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();
        // forward slashes: the command() helper strips backslashes from its arguments
        $this->backupDir = str_replace(DIRECTORY_SEPARATOR, "/", WRITEPATH) . "backups-test-" . bin2hex(random_bytes(3));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->backupDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->backupDir);
        parent::tearDown();
    }

    /** CLI::write() goes to STDOUT, so collect it through the test stream filter. */
    private function runCommand(string $command): string
    {
        $this->resetStreamFilterBuffer();
        command($command);

        return $this->getStreamFilterBuffer();
    }

    public function testDbBackupWritesValidCopyAndRotates(): void
    {
        mkdir($this->backupDir, 0700, true);
        touch($this->backupDir . '/beauty_parlour-20200101-000000.sqlite');
        touch($this->backupDir . '/beauty_parlour-20200102-000000.sqlite');

        $out = $this->runCommand('db:backup --keep 2 --dir ' . $this->backupDir);
        $this->assertStringContainsString('backup written', $out);
        $this->assertStringContainsString('deleted 1 old file', $out);

        $files = glob($this->backupDir . '/beauty_parlour-*.sqlite');
        sort($files);
        $this->assertCount(2, $files, 'rotation keeps only the newest --keep files');
        $this->assertSame('beauty_parlour-20200102-000000.sqlite', basename($files[0]), 'the oldest file was deleted');

        $copy   = new SQLite3($files[1], SQLITE3_OPEN_READONLY);
        $tables = [];
        $res    = $copy->query("SELECT name FROM sqlite_master WHERE type = 'table'");
        while ($row = $res->fetchArray(SQLITE3_NUM)) {
            $tables[] = $row[0];
        }
        $copy->close();

        $this->assertContains('db_settings', $tables, 'the copy is a real SQLite database carrying the schema');
    }

    public function testRemindersGenerateIsIdempotentAndHonoursDryRun(): void
    {
        $today     = Time::now();
        $customers = new CustomerModel();

        $birthdayId = $customers->insert(['name' => 'Bina', 'phone' => '9000000011', 'whatsapp_opt_in' => 1, 'birthday' => '1990-' . $today->format('m-d')]);
        $customers->insert(['name' => 'Opted out', 'phone' => '9000000012', 'whatsapp_opt_in' => 0, 'birthday' => '1985-' . $today->format('m-d')]);
        $followId = $customers->insert(['name' => 'Falguni', 'phone' => '9000000013', 'whatsapp_opt_in' => 1]);
        $apptId   = $customers->insert(['name' => 'Asha', 'phone' => '9000000014', 'whatsapp_opt_in' => 1]);

        $appointments = new AppointmentModel();
        $lastVisit    = $today->subDays(30)->toDateString(); // settings.followup_days default = 30
        $appointments->insert(['customer_id' => $followId, 'starts_at' => $lastVisit . ' 11:00:00', 'ends_at' => $lastVisit . ' 11:45:00', 'status' => 'completed', 'total' => 500]);
        $tomorrow = $today->addDays(1)->toDateString();
        $upcoming = $appointments->insert(['customer_id' => $apptId, 'starts_at' => $tomorrow . ' 10:00:00', 'ends_at' => $tomorrow . ' 10:45:00', 'status' => 'scheduled', 'total' => 500]);

        $dry = $this->runCommand('reminders:generate --dry-run');
        $this->assertStringContainsString('DRY RUN', $dry);
        $this->assertStringContainsString('birthday 1, follow-up 1, appointment 1', $dry);
        $this->assertSame(0, (new ReminderModel())->countAllResults());

        $out = $this->runCommand('reminders:generate');
        $this->assertStringContainsString('created: birthday 1, follow-up 1, appointment 1', $out);
        $this->seeInDatabase('reminders', ['customer_id' => $birthdayId, 'type' => 'birthday', 'status' => 'pending']);
        $this->seeInDatabase('reminders', ['customer_id' => $followId, 'type' => 'follow_up', 'status' => 'pending']);
        $this->seeInDatabase('reminders', ['appointment_id' => $upcoming, 'type' => 'appointment', 'status' => 'pending']);
        $this->assertSame(3, (new ReminderModel())->countAllResults());

        $again = $this->runCommand('reminders:generate');
        $this->assertStringContainsString('created: birthday 0, follow-up 0, appointment 0', $again);
        $this->assertSame(3, (new ReminderModel())->countAllResults());
    }
}
