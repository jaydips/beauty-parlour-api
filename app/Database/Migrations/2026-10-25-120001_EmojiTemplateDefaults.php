<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\I18n\Time;

/**
 * Second pass on the WhatsApp templates (same day as 2026-10-25-100001): the Gujarati defaults get
 * WhatsApp *bold* markup and emoji, identical to the Flutter app's local defaults.
 *
 * As before, a row is replaced only while it still equals the previous default (the plain Gujarati
 * text introduced by 100001 / seeded for tpl_whatsapp_offer); a template the owner edited is left
 * alone. Plain UPDATEs only: fine on the host's SQLite 3.7.17 and on MySQL (utf8mb4 needed there for
 * the emoji, see app/Config/Database.php). Both texts are frozen here on purpose.
 */
class EmojiTemplateDefaults extends Migration
{
    /**
     * @var array<string, array{0: string, 1: string}> key => [previous plain Gujarati default, new emoji default]
     */
    public const REPLACEMENTS = [
        'tpl_whatsapp_booking' => [
            "નમસ્તે {customer_name}, {parlour_name} માં તમારી અપોઇન્ટમેન્ટ બુક થઈ ગઈ છે.\nતારીખ: {date}\nસમય: {time}\nસેવા: {services}\nસમયસર પધારશો. આભાર!",
            "💖 *નમસ્તે {customer_name}!*\n✅ *{parlour_name}* માં તમારી અપોઇન્ટમેન્ટ *બુક થઈ ગઈ છે.*\n\n📅 *તારીખ:* {date}\n⏰ *સમય:* {time}\n💇‍♀️ *સેવા:* {services}\n\n🙏 સમયસર પધારશો. આભાર! ✨",
        ],
        'tpl_whatsapp_reminder' => [
            "નમસ્તે {customer_name}, યાદ અપાવવા માટે: {parlour_name} માં તમારી અપોઇન્ટમેન્ટ {date} ના {time} એ છે.\nસેવા: {services}\nમળીએ!",
            "⏰ *રિમાઇન્ડર – નમસ્તે {customer_name}!*\n📍 *{parlour_name}* માં તમારી અપોઇન્ટમેન્ટ:\n\n📅 *તારીખ:* {date}\n🕒 *સમય:* {time}\n💇‍♀️ *સેવા:* {services}\n\n🔁 સમય બદલવો હોય તો આ મેસેજનો જવાબ આપો.\n💖 મળીએ!",
        ],
        'tpl_whatsapp_followup' => [
            'નમસ્તે {customer_name}, {parlour_name} માં તમારી છેલ્લી મુલાકાતને ઘણો સમય થયો. તમારી આગલી અપોઇન્ટમેન્ટ બુક કરવી છે? આ મેસેજનો જવાબ આપો અથવા ફોન કરો.',
            "🌟 *નમસ્તે {customer_name}!*\n💆‍♀️ આશા છે કે તમારી છેલ્લી સેવા તમને ગમી હશે!\n📅 *{parlour_name}* માં તમારી આગલી અપોઇન્ટમેન્ટ બુક કરવાનો સમય થઈ ગયો છે.\n✨ તમારા માટે slot *reserve* કરીએ? આ મેસેજનો જવાબ આપો અથવા ફોન કરો. 💖",
        ],
        'tpl_whatsapp_birthday' => [
            'જન્મદિવસની હાર્દિક શુભેચ્છાઓ {customer_name}! {parlour_name} તરફથી ખૂબ ખૂબ શુભકામનાઓ. આ મહિને મુલાકાત લઈને પોતાને ખુશ કરો.',
            "🎂🎉 *જન્મદિવસની હાર્દિક શુભેચ્છાઓ {customer_name}!* 🎉🎂\n🌸 *{parlour_name}* તરફથી ખૂબ ખૂબ શુભકામનાઓ.\n💖 આ મહિને મુલાકાત લઈને પોતાને ખુશ કરો! ✨",
        ],
        'tpl_whatsapp_offer' => [
            'નમસ્તે {customer_name}, {parlour_name} માં આ અઠવાડિયે ખાસ ઑફર! વધુ માહિતી માટે આ મેસેજનો જવાબ આપો.',
            "🎁 *નમસ્તે {customer_name}!*\n✨ *{parlour_name}* માં આ અઠવાડિયે *ખાસ ઑફર!*\n📲 વધુ માહિતી માટે આ મેસેજનો જવાબ આપો. 💖\n_(ઑફર મેસેજ બંધ કરવા STOP લખો.)_",
        ],
    ];

    public function up(): void
    {
        $this->swap(0, 1);
    }

    public function down(): void
    {
        $this->swap(1, 0);
    }

    private function swap(int $from, int $to): void
    {
        $now = Time::now()->toDateTimeString();

        foreach (self::REPLACEMENTS as $key => $texts) {
            $this->db->table('settings')
                ->where('key', $key)
                ->where('value', $texts[$from])
                ->update(['value' => $texts[$to], 'updated_at' => $now]);
        }
    }
}
