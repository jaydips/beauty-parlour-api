<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

/**
 * Default parlour settings. Idempotent: only inserts keys that do not exist yet,
 * so it is safe to run on every deploy (no upsert() - not available on the host).
 *
 * The WhatsApp templates are Gujarati (the owner and the customers read Gujarati). Placeholders:
 * {customer_name} {parlour_name} {date} {time} {services}. Dates and times are rendered in Gujarati
 * by App\Libraries\WhatsApp (e.g. "શનિવાર, 25 ઑક્ટોબર 2026", "સવારે 10:30").
 * Existing installations get the Gujarati texts through migration 2026-10-25-100001, which replaces
 * only the old English defaults and leaves templates the owner edited untouched.
 */
class SettingsSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    public const DEFAULTS = [
        'parlour_name'          => 'મારું બ્યુટી પાર્લર',
        'parlour_phone'         => '',
        'parlour_address'       => '',
        'country_code'          => '91',
        'currency'              => 'INR',
        'timezone'              => 'Asia/Kolkata',
        'opening_hours'         => '{"mon":["10:00","20:00"],"tue":["10:00","20:00"],"wed":["10:00","20:00"],"thu":["10:00","20:00"],"fri":["10:00","20:00"],"sat":["10:00","20:00"],"sun":null}',
        'slot_minutes'          => '15',
        'reminder_lead_hours'   => '24',
        'followup_days'         => '30',
        'tpl_whatsapp_booking'  => "નમસ્તે {customer_name}, {parlour_name} માં તમારી અપોઇન્ટમેન્ટ બુક થઈ ગઈ છે.\nતારીખ: {date}\nસમય: {time}\nસેવા: {services}\nસમયસર પધારશો. આભાર!",
        'tpl_whatsapp_reminder' => "નમસ્તે {customer_name}, યાદ અપાવવા માટે: {parlour_name} માં તમારી અપોઇન્ટમેન્ટ {date} ના {time} એ છે.\nસેવા: {services}\nમળીએ!",
        'tpl_whatsapp_followup' => 'નમસ્તે {customer_name}, {parlour_name} માં તમારી છેલ્લી મુલાકાતને ઘણો સમય થયો. તમારી આગલી અપોઇન્ટમેન્ટ બુક કરવી છે? આ મેસેજનો જવાબ આપો અથવા ફોન કરો.',
        'tpl_whatsapp_birthday' => 'જન્મદિવસની હાર્દિક શુભેચ્છાઓ {customer_name}! {parlour_name} તરફથી ખૂબ ખૂબ શુભકામનાઓ. આ મહિને મુલાકાત લઈને પોતાને ખુશ કરો.',
        'tpl_whatsapp_offer'    => 'નમસ્તે {customer_name}, {parlour_name} માં આ અઠવાડિયે ખાસ ઑફર! વધુ માહિતી માટે આ મેસેજનો જવાબ આપો.',
        // Automatic sending through the WhatsApp bridge (off until the owner links a number and switches it on)
        'wa_auto_send'          => '0',
        'wa_send_from'          => '09:00',
        'wa_send_to'            => '21:00',
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
