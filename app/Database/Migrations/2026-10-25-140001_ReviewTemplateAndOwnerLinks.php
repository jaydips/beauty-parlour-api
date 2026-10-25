<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\I18n\Time;

/**
 * Review message in the owner's own words (2026-10-25) and the owner's public links.
 *
 * 1. tpl_whatsapp_review: the draft default shipped earlier the same day is replaced by the owner's
 *    text ({social_links}, {tagline}); as always only a row still equal to the previous default changes.
 * 2. google_review_url / instagram_url: the owner's Google My Business review link and Instagram page
 *    (public, meant to be sent to customers). Set only while the setting is blank or missing, so a
 *    value the owner typed in the settings page is never overwritten. down() clears exactly these
 *    values again and restores the previous template.
 */
class ReviewTemplateAndOwnerLinks extends Migration
{
    /**
     * @var array<string, array{0: string, 1: string}> key => [previous default, new default]
     */
    public const REPLACEMENTS = [
        'tpl_whatsapp_review' => [
            "નમસ્તે *{customer_name}*, {parlour_name} ની મુલાકાત બદલ આભાર 🙏\n\n💅 *સર્વિસ:* {services}\n⭐ આપનો અનુભવ કેવો રહ્યો? Google પર review આપો:\n👉 {review_link}\n📸 Instagram પર અમને follow કરો:\n👉 {instagram_link}\n\n*{parlour_name}*\n📍 {parlour_address}\n📞 {parlour_phone}\n\nઆપનો અભિપ્રાય અમારા માટે મહત્વનો છે. આભાર! 🌸",
            "🌸 {parlour_name} 🌸\n\nપ્રિય *{customer_name}* ❤️\n\nઅમારી પાર્લર સર્વિસ પસંદ કરવા બદલ આપનો ખૂબ ખૂબ આભાર! 🥰✨\n\nઆપને અમારી સર્વિસ કેવી લાગી? આપનો અનુભવ અમારી સાથે શેર કરવા માટે એક નાનું રીવ્યુ જરૂર આપશો. ⭐⭐⭐⭐⭐\n\nઆપનો એક નાનો રીવ્યુ અમને વધુ સારી સર્વિસ આપવા માટે પ્રેરણા આપશે અને અન્ય બહેનોને પણ અમારું પાર્લર પસંદ કરવામાં મદદ કરશે. 💖\n\n━━━━━━━━━━━━━━\n\n{social_links}\n\n━━━━━━━━━━━━━━\n\n🙏 આપના પ્રેમ અને વિશ્વાસ બદલ હૃદયપૂર્વક આભાર!\n\n🌷 {parlour_name} – {tagline} 🌷",
        ],
    ];

    /**
     * @var array<string, string> owner's links, applied only to blank/missing settings
     */
    public const OWNER_LINKS = [
        'google_review_url' => 'https://search.google.com/local/writereview?placeid=ChIJKw67quW3WTkRx7NbDtV8SjM',
        'instagram_url'     => 'https://www.instagram.com/delicate_beauty_world_rajkot',
    ];

    public function up(): void
    {
        $now = Time::now()->toDateTimeString();
        $this->swap(0, 1, $now);

        $settings = $this->db->table('settings');

        foreach (self::OWNER_LINKS as $key => $url) {
            $row = $settings->where('key', $key)->get()->getRowArray();

            if ($row === null) {
                $this->db->table('settings')->insert(['key' => $key, 'value' => $url, 'updated_at' => $now]);
            } elseif (trim((string) $row['value']) === '') {
                $this->db->table('settings')->where('key', $key)->update(['value' => $url, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        $now = Time::now()->toDateTimeString();
        $this->swap(1, 0, $now);

        foreach (self::OWNER_LINKS as $key => $url) {
            $this->db->table('settings')->where('key', $key)->where('value', $url)->update(['value' => '', 'updated_at' => $now]);
        }
    }

    private function swap(int $from, int $to, string $now): void
    {
        foreach (self::REPLACEMENTS as $key => $texts) {
            $this->db->table('settings')
                ->where('key', $key)
                ->where('value', $texts[$from])
                ->update(['value' => $texts[$to], 'updated_at' => $now]);
        }
    }
}
