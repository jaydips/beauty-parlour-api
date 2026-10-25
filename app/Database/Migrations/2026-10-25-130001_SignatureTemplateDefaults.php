<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\I18n\Time;

/**
 * Third pass on the WhatsApp templates (2026-10-25, owner's sample message): greeting with the
 * customer's name in bold plus the status, detail lines, the parlour signature
 * (*{parlour_name}* / 📍 {parlour_address} / 📞 {parlour_phone}, empty lines are dropped by the
 * renderer) and a closing line. {date} is now dd/mm/yyyy.
 *
 * Same rule as 100001 and 120001: a row is replaced only while it still equals the previous
 * default (the emoji texts of 120001); owner-edited templates are left alone. Plain UPDATEs only.
 */
class SignatureTemplateDefaults extends Migration
{
    /**
     * @var array<string, array{0: string, 1: string}> key => [previous default (120001), new default]
     */
    public const REPLACEMENTS = [
        'tpl_whatsapp_booking' => [
            "💖 *નમસ્તે {customer_name}!*\n✅ *{parlour_name}* માં તમારી અપોઇન્ટમેન્ટ *બુક થઈ ગઈ છે.*\n\n📅 *તારીખ:* {date}\n⏰ *સમય:* {time}\n💇‍♀️ *સેવા:* {services}\n\n🙏 સમયસર પધારશો. આભાર! ✨",
            "✅ *નમસ્તે {customer_name}!* તમારી અપોઇન્ટમેન્ટ બુક થઈ ગઈ છે.\n\n📅 *તારીખ:* {date}\n⏰ *સમય:* {time}\n💅 *સર્વિસ:* {services}\n\n*{parlour_name}*\n📍 {parlour_address}\n📞 {parlour_phone}\n\nકોઈ ફેરફાર કરવો હોય તો અમને જણાવશો. આભાર! 🌸",
        ],
        'tpl_whatsapp_reminder' => [
            "⏰ *રિમાઇન્ડર – નમસ્તે {customer_name}!*\n📍 *{parlour_name}* માં તમારી અપોઇન્ટમેન્ટ:\n\n📅 *તારીખ:* {date}\n🕒 *સમય:* {time}\n💇‍♀️ *સેવા:* {services}\n\n🔁 સમય બદલવો હોય તો આ મેસેજનો જવાબ આપો.\n💖 મળીએ!",
            "⏰ *નમસ્તે {customer_name}!* તમારી અપોઇન્ટમેન્ટનું રિમાઇન્ડર.\n\n📅 *તારીખ:* {date}\n⏰ *સમય:* {time}\n💅 *સર્વિસ:* {services}\n\n*{parlour_name}*\n📍 {parlour_address}\n📞 {parlour_phone}\n\nકોઈ ફેરફાર કરવો હોય તો અમને જણાવશો. આભાર! 🌸",
        ],
        'tpl_whatsapp_followup' => [
            "🌟 *નમસ્તે {customer_name}!*\n💆‍♀️ આશા છે કે તમારી છેલ્લી સેવા તમને ગમી હશે!\n📅 *{parlour_name}* માં તમારી આગલી અપોઇન્ટમેન્ટ બુક કરવાનો સમય થઈ ગયો છે.\n✨ તમારા માટે slot *reserve* કરીએ? આ મેસેજનો જવાબ આપો અથવા ફોન કરો. 💖",
            "🌸 *નમસ્તે {customer_name}!* તમારી છેલ્લી મુલાકાતને ઘણો સમય થયો.\n\n✨ આગલી અપોઇન્ટમેન્ટ બુક કરવા આ મેસેજનો જવાબ આપો અથવા ફોન કરો.\n\n*{parlour_name}*\n📍 {parlour_address}\n📞 {parlour_phone}\n\nકોઈ ફેરફાર કરવો હોય તો અમને જણાવશો. આભાર! 🌸",
        ],
        'tpl_whatsapp_birthday' => [
            "🎂🎉 *જન્મદિવસની હાર્દિક શુભેચ્છાઓ {customer_name}!* 🎉🎂\n🌸 *{parlour_name}* તરફથી ખૂબ ખૂબ શુભકામનાઓ.\n💖 આ મહિને મુલાકાત લઈને પોતાને ખુશ કરો! ✨",
            "🎂 *નમસ્તે {customer_name}!* જન્મદિવસની હાર્દિક શુભેચ્છાઓ! 🎉\n\n💖 આ મહિને મુલાકાત લઈને પોતાને ખુશ કરો. ✨\n\n*{parlour_name}*\n📍 {parlour_address}\n📞 {parlour_phone}\n\nકોઈ ફેરફાર કરવો હોય તો અમને જણાવશો. આભાર! 🌸",
        ],
        'tpl_whatsapp_offer' => [
            "🎁 *નમસ્તે {customer_name}!*\n✨ *{parlour_name}* માં આ અઠવાડિયે *ખાસ ઑફર!*\n📲 વધુ માહિતી માટે આ મેસેજનો જવાબ આપો. 💖\n_(ઑફર મેસેજ બંધ કરવા STOP લખો.)_",
            "🎁 *નમસ્તે {customer_name}!* આ અઠવાડિયે ખાસ ઑફર!\n\n✨ વધુ માહિતી માટે આ મેસેજનો જવાબ આપો.\n\n*{parlour_name}*\n📍 {parlour_address}\n📞 {parlour_phone}\n\nકોઈ ફેરફાર કરવો હોય તો અમને જણાવશો. આભાર! 🌸\n_(ઑફર મેસેજ બંધ કરવા STOP લખો.)_",
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
