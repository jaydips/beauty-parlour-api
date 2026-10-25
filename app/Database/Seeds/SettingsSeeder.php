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
        // Structure (owner's sample, 2026-10-25): greeting with the customer's name in bold + status,
        // detail lines, parlour signature ({parlour_address} / {parlour_phone} lines vanish when empty),
        // closing line. Migration 2026-10-25-130001 upgrades installations still on the previous defaults.
        'tpl_whatsapp_booking'  => "✅ *નમસ્તે {customer_name}!* તમારી અપોઇન્ટમેન્ટ બુક થઈ ગઈ છે.\n\n📅 *તારીખ:* {date}\n⏰ *સમય:* {time}\n💅 *સર્વિસ:* {services}\n\n*{parlour_name}*\n📍 {parlour_address}\n📞 {parlour_phone}\n\nકોઈ ફેરફાર કરવો હોય તો અમને જણાવશો. આભાર! 🌸",
        'tpl_whatsapp_reminder' => "⏰ *નમસ્તે {customer_name}!* તમારી અપોઇન્ટમેન્ટનું રિમાઇન્ડર.\n\n📅 *તારીખ:* {date}\n⏰ *સમય:* {time}\n💅 *સર્વિસ:* {services}\n\n*{parlour_name}*\n📍 {parlour_address}\n📞 {parlour_phone}\n\nકોઈ ફેરફાર કરવો હોય તો અમને જણાવશો. આભાર! 🌸",
        'tpl_whatsapp_followup' => "🌸 *નમસ્તે {customer_name}!* તમારી છેલ્લી મુલાકાતને ઘણો સમય થયો.\n\n✨ આગલી અપોઇન્ટમેન્ટ બુક કરવા આ મેસેજનો જવાબ આપો અથવા ફોન કરો.\n\n*{parlour_name}*\n📍 {parlour_address}\n📞 {parlour_phone}\n\nકોઈ ફેરફાર કરવો હોય તો અમને જણાવશો. આભાર! 🌸",
        'tpl_whatsapp_birthday' => "🎂 *નમસ્તે {customer_name}!* જન્મદિવસની હાર્દિક શુભેચ્છાઓ! 🎉\n\n💖 આ મહિને મુલાકાત લઈને પોતાને ખુશ કરો. ✨\n\n*{parlour_name}*\n📍 {parlour_address}\n📞 {parlour_phone}\n\nકોઈ ફેરફાર કરવો હોય તો અમને જણાવશો. આભાર! 🌸",
        'tpl_whatsapp_offer'    => "🎁 *નમસ્તે {customer_name}!* આ અઠવાડિયે ખાસ ઑફર!\n\n✨ વધુ માહિતી માટે આ મેસેજનો જવાબ આપો.\n\n*{parlour_name}*\n📍 {parlour_address}\n📞 {parlour_phone}\n\nકોઈ ફેરફાર કરવો હોય તો અમને જણાવશો. આભાર! 🌸\n_(ઑફર મેસેજ બંધ કરવા STOP લખો.)_",
        // Review request after a completed appointment (owner's own text, 2026-10-25). {social_links} is
        // built by the renderer from the three URL settings below (only the links that are set; a Gujarati
        // hint while none is); {tagline} is the line under the parlour name. URLs stay outside *bold*.
        'tpl_whatsapp_review'   => "🌸 {parlour_name} 🌸\n\nપ્રિય *{customer_name}* ❤️\n\nઅમારી પાર્લર સર્વિસ પસંદ કરવા બદલ આપનો ખૂબ ખૂબ આભાર! 🥰✨\n\nઆપને અમારી સર્વિસ કેવી લાગી? આપનો અનુભવ અમારી સાથે શેર કરવા માટે એક નાનું રીવ્યુ જરૂર આપશો. ⭐⭐⭐⭐⭐\n\nઆપનો એક નાનો રીવ્યુ અમને વધુ સારી સર્વિસ આપવા માટે પ્રેરણા આપશે અને અન્ય બહેનોને પણ અમારું પાર્લર પસંદ કરવામાં મદદ કરશે. 💖\n\n━━━━━━━━━━━━━━\n\n{social_links}\n\n━━━━━━━━━━━━━━\n\n🙏 આપના પ્રેમ અને વિશ્વાસ બદલ હૃદયપૂર્વક આભાર!\n\n🌷 {parlour_name} – {tagline} 🌷",
        'google_review_url'     => '',
        'instagram_url'         => '',
        'whatsapp_group_url'    => '',
        'tagline'               => 'આપની સુંદરતા, અમારી ખુશી!',
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
