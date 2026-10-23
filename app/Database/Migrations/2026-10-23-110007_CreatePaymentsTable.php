<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * payments - money received or refunded, whole rupees, always positive amounts.
 * See docs/database-schema.md section 3.7.
 */
class CreatePaymentsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'customer_id'    => ['type' => 'INT', 'unsigned' => true],
            'appointment_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'type'           => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'payment'],
            'method'         => ['type' => 'VARCHAR', 'constraint' => 10],
            'amount'         => ['type' => 'INT', 'unsigned' => true],
            'paid_at'        => ['type' => 'DATETIME'],
            'reference'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'notes'          => ['type' => 'TEXT', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('paid_at');
        $this->forge->addKey('appointment_id');
        $this->forge->addKey('customer_id');
        // addForeignKey(field, table, tableField, onUpdate, onDelete)
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('appointment_id', 'appointments', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('payments');
    }

    public function down(): void
    {
        $this->forge->dropTable('payments', true);
    }
}
