<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * campaigns: an optional image (sent with every recipient's message as the caption's picture) and
 * the time the broadcast may start. ALTER TABLE ... ADD COLUMN only, which the host's SQLite 3.7.17
 * supports; nothing is dropped in down() because that SQLite cannot drop columns (the columns are
 * nullable and harmless when left behind).
 */
class AddCampaignImageAndSchedule extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('campaigns', [
            'image_path'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'audience_days'],
            'scheduled_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'image_path'],
        ]);
    }

    public function down(): void
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->dropColumn('campaigns', ['image_path', 'scheduled_at']);
        }
    }
}
