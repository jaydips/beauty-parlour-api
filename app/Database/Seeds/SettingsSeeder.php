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
        // WhatsApp formatting: *bold*, _italic_; emoji need utf8mb4 on MySQL (SQLite stores UTF-8 as is).
        // Same texts as the Flutter app's local defaults (2026-10-25); migration 2026-10-25-120001 upgrades
        // existing installations that still carry the previous plain Gujarati defaults.
        'tpl_whatsapp_booking'  => "💖 *નમસ્તે {customer_name}!*\n✅ *{parlour_name}* માં તમારી અપોઇન્ટમેન્ટ *બુક થઈ ગઈ છે.*\n\n📅 *તારીખ:* {date}\n⏰ *સમય:* {time}\n💇‍♀️ *સેવા:* {services}\n\n🙏 સમયસર પધારશો. આભાર! ✨",
        'tpl_whatsapp_reminder' => "⏰ *રિમાઇન્ડર – નમસ્તે {customer_name}!*\n📍 *{parlour_name}* માં તમારી અપોઇન્ટમેન્ટ:\n\n📅 *તારીખ:* {date}\n🕒 *સમય:* {time}\n💇‍♀️ *સેવા:* {services}\n\n🔁 સમય બદલવો હોય તો આ મેસેજનો જવાબ આપો.\n💖 મળીએ!",
        'tpl_whatsapp_followup' => "🌟 *નમસ્તે {customer_name}!*\n💆‍♀️ આશા છે કે તમારી છેલ્લી સેવા તમને ગમી હશે!\n📅 *{parlour_name}* માં તમારી આગલી અપોઇન્ટમેન્ટ બુક કરવાનો સમય થઈ ગયો છે.\n✨ તમારા માટે slot *reserve* કરીએ? આ મેસેજનો જવાબ આપો અથવા ફોન કરો. 💖",
        'tpl_whatsapp_birthday' => "🎂🎉 *જન્મદિવસની હાર્દિક શુભેચ્છાઓ {customer_name}!* 🎉🎂\n🌸 *{parlour_name}* તરફથી ખૂબ ખૂબ શુભકામનાઓ.\n💖 આ મહિને મુલાકાત લઈને પોતાને ખુશ કરો! ✨",
        'tpl_whatsapp_offer'    => "🎁 *નમસ્તે {customer_name}!*\n✨ *{parlour_name}* માં આ અઠવાડિયે *ખાસ ઑફર!*\n📲 વધુ માહિતી માટે આ મેસેજનો જવાબ આપો. 💖\n_(ઑફર મેસેજ બંધ કરવા STOP લખો.)_",
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
