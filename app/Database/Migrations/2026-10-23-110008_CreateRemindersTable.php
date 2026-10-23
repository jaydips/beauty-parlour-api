<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * reminders - the owner's manual WhatsApp to-do list (click-to-chat, no automation).
 * See docs/database-schema.md section 3.8.
 */
class CreateRemindersTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'customer_id'    => ['type' => 'INT', 'unsigned' => true],
            'appointment_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'type'           => ['type' => 'VARCHAR', 'constraint' => 20],
            'due_at'         => ['type' => 'DATETIME'],
            'channel'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'whatsapp'],
            'message'        => ['type' => 'TEXT'],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'pending'],
            'sent_at'        => ['type' => 'DATETIME', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['status', 'due_at']);
        $this->forge->addKey('appointment_id');
        $this->forge->addKey('customer_id');
        // addForeignKey(field, table, tableField, onUpdate, onDelete)
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('appointment_id', 'appointments', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('reminders');
    }

    public function down(): void
    {
        $this->forge->dropTable('reminders', true);
    }
}
