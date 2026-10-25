<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

/**
 * The standard beauty-parlour price list (2026-10-25, owner's request): 85 services with
 * starting prices in whole rupees and durations in minutes. The owner adjusts prices and
 * deactivates what the parlour does not offer.
 *
 * Idempotent: a service is inserted only when no row with the same name exists yet (compared
 * case-insensitively, soft-deleted rows included, so a service the owner removed is not revived);
 * existing rows, prices and flags are never touched. Runs on every deploy from Install::migrate
 * and by hand with `php spark db:seed ServicesSeeder`.
 */
class ServicesSeeder extends Seeder
{
    /** How many rows the last run() inserted / left alone. */
    public int $inserted = 0;
    public int $skipped  = 0;

    /**
     * @var list<array{0: string, 1: string, 2: int, 3: int}> [category, name, price, minutes] in display order
     */
    public const SERVICES = [
        ['વાળ', 'Haircut (Women)', 300, 45],
        ['વાળ', 'Haircut (Kids)', 150, 30],
        ['વાળ', 'Hair Trim', 150, 20],
        ['વાળ', 'Hair Wash & Blow Dry', 300, 30],
        ['વાળ', 'Head Massage', 300, 30],
        ['વાળ', 'Hair Spa', 800, 60],
        ['વાળ', 'Hair Spa (Keratin)', 1500, 75],
        ['વાળ', 'Dandruff Treatment', 700, 50],
        ['વાળ', 'Hair Fall Treatment', 800, 50],
        ['વાળ', 'Hair Colour (Root Touch-up)', 800, 60],
        ['વાળ', 'Hair Colour (Global)', 2000, 90],
        ['વાળ', 'Hair Highlights', 2500, 120],
        ['વાળ', 'Keratin Treatment', 4000, 150],
        ['વાળ', 'Hair Smoothening', 3500, 150],
        ['વાળ', 'Hair Straightening', 3000, 120],
        ['વાળ', 'Hair Ironing', 300, 30],
        ['વાળ', 'Hair Curls', 400, 40],
        ['વાળ', 'Hairstyle / Updo', 500, 45],
        ['ત્વચા', 'Clean Up', 500, 40],
        ['ત્વચા', 'Classic Facial', 800, 60],
        ['ત્વચા', 'Fruit Facial', 900, 60],
        ['ત્વચા', 'Gold Facial', 1500, 75],
        ['ત્વચા', 'Pearl Facial', 1500, 75],
        ['ત્વચા', 'Diamond Facial', 1800, 75],
        ['ત્વચા', 'Whitening Facial', 1500, 75],
        ['ત્વચા', 'Anti-Tan Facial', 1200, 60],
        ['ત્વચા', 'Anti-Ageing Facial', 2000, 75],
        ['ત્વચા', 'Hydra Facial', 2500, 75],
        ['ત્વચા', 'De-Tan Pack', 500, 30],
        ['ત્વચા', 'Face Massage', 400, 30],
        ['ત્વચા', 'Bleach (Face)', 400, 30],
        ['ત્વચા', 'Bleach (Full Arms)', 500, 30],
        ['ત્વચા', 'Bleach (Full Body)', 1500, 60],
        ['થ્રેડિંગ', 'Eyebrow Threading', 50, 10],
        ['થ્રેડિંગ', 'Upper Lip Threading', 30, 5],
        ['થ્રેડિંગ', 'Chin Threading', 30, 5],
        ['થ્રેડિંગ', 'Forehead Threading', 30, 5],
        ['થ્રેડિંગ', 'Full Face Threading', 150, 20],
        ['વેક્સિંગ', 'Full Arms Waxing', 300, 30],
        ['વેક્સિંગ', 'Half Arms Waxing', 200, 20],
        ['વેક્સિંગ', 'Underarms Waxing', 100, 10],
        ['વેક્સિંગ', 'Full Legs Waxing', 500, 40],
        ['વેક્સિંગ', 'Half Legs Waxing', 300, 25],
        ['વેક્સિંગ', 'Back Waxing', 400, 30],
        ['વેક્સિંગ', 'Stomach Waxing', 300, 20],
        ['વેક્સિંગ', 'Bikini Waxing', 600, 30],
        ['વેક્સિંગ', 'Face Waxing', 300, 20],
        ['વેક્સિંગ', 'Full Body Waxing', 1500, 90],
        ['વેક્સિંગ', 'Rica Wax – Full Arms', 500, 30],
        ['વેક્સિંગ', 'Rica Wax – Full Legs', 800, 40],
        ['હાથ-પગ', 'Manicure', 500, 45],
        ['હાથ-પગ', 'Pedicure', 700, 60],
        ['હાથ-પગ', 'Spa Manicure', 800, 60],
        ['હાથ-પગ', 'Spa Pedicure', 1000, 75],
        ['હાથ-પગ', 'Foot Massage', 400, 30],
        ['હાથ-પગ', 'Nail Paint', 100, 15],
        ['હાથ-પગ', 'Nail Art', 300, 30],
        ['હાથ-પગ', 'Gel Nail Polish', 600, 45],
        ['હાથ-પગ', 'Nail Extensions', 1500, 90],
        ['મેકઅપ', 'Party Makeup', 2500, 90],
        ['મેકઅપ', 'HD Makeup', 4000, 120],
        ['મેકઅપ', 'Engagement Makeup', 5000, 120],
        ['મેકઅપ', 'Reception Makeup', 6000, 120],
        ['મેકઅપ', 'Airbrush Makeup', 8000, 150],
        ['મેકઅપ', 'Hairstyle with Makeup', 800, 45],
        ['મેકઅપ', 'Saree Draping', 300, 20],
        ['મેકઅપ', 'Eyelash Extensions', 1500, 90],
        ['બ્રાઇડલ', 'Bridal Makeup (HD)', 15000, 180],
        ['બ્રાઇડલ', 'Bridal Makeup (Airbrush)', 25000, 180],
        ['બ્રાઇડલ', 'Bridal Makeup Trial', 3000, 120],
        ['બ્રાઇડલ', 'Pre-Bridal Package (Basic)', 8000, 240],
        ['બ્રાઇડલ', 'Pre-Bridal Package (Premium)', 15000, 300],
        ['બ્રાઇડલ', 'Bridal Mehndi', 5000, 180],
        ['બ્રાઇડલ', 'Simple Mehndi', 500, 45],
        ['બોડી અને સ્પા', 'Body Polishing', 2500, 90],
        ['બોડી અને સ્પા', 'Full Body Massage', 1500, 60],
        ['બોડી અને સ્પા', 'Body Scrub', 1200, 60],
        ['બોડી અને સ્પા', 'Back Massage', 600, 30],
        ['અન્ય', 'Ear Piercing', 300, 15],
        ['અન્ય', 'Nose Piercing', 300, 15],
        ['અન્ય', 'Eyebrow Tinting', 300, 20],
        ['અન્ય', 'Lash Lift', 1200, 60],
        ['અન્ય', 'Saree Pre-pleating', 200, 15],
    ];

    public function run(): void
    {
        $existing = [];

        foreach ($this->db->table('services')->select('name')->get()->getResultArray() as $row) {
            $existing[self::key((string) $row['name'])] = true;
        }

        $now  = Time::now()->toDateTimeString();
        $rows = [];

        $this->inserted = 0;
        $this->skipped  = 0;

        foreach (self::SERVICES as $i => [$category, $name, $price, $minutes]) {
            if (isset($existing[self::key($name)])) {
                $this->skipped++;

                continue;
            }

            $existing[self::key($name)] = true;
            $rows[] = [
                'name'             => $name,
                'category'         => $category,
                'duration_minutes' => $minutes,
                'price'            => $price,
                'is_active'        => 1,
                'sort_order'       => $i + 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }

        if ($rows !== []) {
            $this->db->table('services')->insertBatch($rows);
        }

        $this->inserted = count($rows);
    }

    private static function key(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? $name));
    }
}
