<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

/**
 * Default parlour settings. Idempotent: only inserts keys that do not exist yet,
 * so it is safe to run on every deploy (no upsert() - not available on the host).
 */
class SettingsSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    public const DEFAULTS = [
        'parlour_name'          => 'My Beauty Parlour',
        'parlour_phone'         => '',
        'parlour_address'       => '',
        'country_code'          => '91',
        'currency'              => 'INR',
        'timezone'              => 'Asia/Kolkata',
        'opening_hours'         => '{"mon":["10:00","20:00"],"tue":["10:00","20:00"],"wed":["10:00","20:00"],"thu":["10:00","20:00"],"fri":["10:00","20:00"],"sat":["10:00","20:00"],"sun":null}',
        'slot_minutes'          => '15',
        'reminder_lead_hours'   => '24',
        'followup_days'         => '30',
        'tpl_whatsapp_reminder' => 'Hello {customer_name}, this is a reminder of your appointment at {parlour_name} on {date} at {time} for {services}. See you soon!',
        'tpl_whatsapp_followup' => 'Hello {customer_name}, it has been a while since your last visit to {parlour_name}. Would you like to book your next appointment?',
        'tpl_whatsapp_birthday' => 'Happy birthday {customer_name}! Warm wishes from {parlour_name}. Treat yourself to a visit this month.',
    ];

    public function run(): void
    {
        $existing = array_column(
            $this->db->table('settings')->select('key')->get()->getResultArray(),
            'key'
        );

        $now  = Time::now()->toDateTimeString();
        $rows = [];

        foreach (self::DEFAULTS as $key => $value) {
            if (! in_array($key, $existing, true)) {
                $rows[] = ['key' => $key, 'value' => $value, 'updated_at' => $now];
            }
        }

        if ($rows !== []) {
            $this->db->table('settings')->insertBatch($rows);
        }
    }
}
