<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * messages - the WhatsApp outbox: one row per automatic message (booking confirmation, reminder,
 * follow-up, birthday, offer). Delivered by `spark whatsapp:send` through the bridge.
 */
class CreateMessagesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'customer_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'appointment_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'reminder_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'campaign_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'phone'          => ['type' => 'VARCHAR', 'constraint' => 15],
            'type'           => ['type' => 'VARCHAR', 'constraint' => 20],
            'body'           => ['type' => 'TEXT'],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'queued'],
            'attempts'       => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'last_error'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'scheduled_at'   => ['type' => 'DATETIME'],
            'sent_at'        => ['type' => 'DATETIME', 'null' => true],
            'wa_id'          => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['status', 'scheduled_at']);
        $this->forge->addKey('customer_id');
        $this->forge->addKey('reminder_id');
        $this->forge->addKey('campaign_id');
        // addForeignKey(field, table, tableField, onUpdate, onDelete)
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('appointment_id', 'appointments', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('reminder_id', 'reminders', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('messages');
    }

    public function down(): void
    {
        $this->forge->dropTable('messages', true);
    }
}
