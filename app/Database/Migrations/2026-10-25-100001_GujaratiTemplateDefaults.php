<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\I18n\Time;

/**
 * Switches the WhatsApp message templates to Gujarati on existing installations.
 *
 * Only a row whose value is still the old English default is replaced; a template the owner
 * edited is left alone. New installations get the Gujarati texts from SettingsSeeder directly
 * (the table is empty when this migration runs there). Plain UPDATE statements only, so it works
 * on MySQL and on the host's SQLite 3.7.17 alike. Both texts are frozen here on purpose: the
 * seeder's defaults may change later without changing what this migration does.
 */
class GujaratiTemplateDefaults extends Migration
{
    /**
     * @var array<string, array{0: string, 1: string}> key => [old English default, new Gujarati default]
     */
    public const REPLACEMENTS = [
        'tpl_whatsapp_booking' => [
            'Hello {customer_name}, your appointment at {parlour_name} is booked for {date} at {time} ({services}). See you!',
            "નમસ્તે {customer_name}, {parlour_name} માં તમારી અપોઇન્ટમેન્ટ બુક થઈ ગઈ છે.\nતારીખ: {date}\nસમય: {time}\nસેવા: {services}\nસમયસર પધારશો. આભાર!",
        ],
        'tpl_whatsapp_reminder' => [
            'Hello {customer_name}, this is a reminder of your appointment at {parlour_name} on {date} at {time} for {services}. See you soon!',
            "નમસ્તે {customer_name}, યાદ અપાવવા માટે: {parlour_name} માં તમારી અપોઇન્ટમેન્ટ {date} ના {time} એ છે.\nસેવા: {services}\nમળીએ!",
        ],
        'tpl_whatsapp_followup' => [
            'Hello {customer_name}, it has been a while since your last visit to {parlour_name}. Would you like to book your next appointment?',
            'નમસ્તે {customer_name}, {parlour_name} માં તમારી છેલ્લી મુલાકાતને ઘણો સમય થયો. તમારી આગલી અપોઇન્ટમેન્ટ બુક કરવી છે? આ મેસેજનો જવાબ આપો અથવા ફોન કરો.',
        ],
        'tpl_whatsapp_birthday' => [
            'Happy birthday {customer_name}! Warm wishes from {parlour_name}. Treat yourself to a visit this month.',
            'જન્મદિવસની હાર્દિક શુભેચ્છાઓ {customer_name}! {parlour_name} તરફથી ખૂબ ખૂબ શુભકામનાઓ. આ મહિને મુલાકાત લઈને પોતાને ખુશ કરો.',
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
