<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * campaigns - offer / announcement broadcasts sent through the WhatsApp bridge
 * (one row per campaign; the per-recipient rows live in `messages`).
 */
class CreateCampaignsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'title'         => ['type' => 'VARCHAR', 'constraint' => 120],
            'body'          => ['type' => 'TEXT'],
            'audience'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'all'],
            'audience_days' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'recipients'    => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'sent'          => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'failed'        => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'status'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'queued'],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('status');
        $this->forge->createTable('campaigns');
    }

    public function down(): void
    {
        $this->forge->dropTable('campaigns', true);
    }
}
