<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * appointments - local-time bookings; amounts in whole rupees; cancelled by status.
 * See docs/database-schema.md section 3.5.
 */
class CreateAppointmentsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'customer_id'   => ['type' => 'INT', 'unsigned' => true],
            'starts_at'     => ['type' => 'DATETIME'],
            'ends_at'       => ['type' => 'DATETIME'],
            'status'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'scheduled'],
            'source'        => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'notes'         => ['type' => 'TEXT', 'null' => true],
            'subtotal'      => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'discount'      => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'total'         => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'cancelled_at'  => ['type' => 'DATETIME', 'null' => true],
            'cancel_reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('starts_at');
        $this->forge->addKey(['customer_id', 'starts_at']);
        $this->forge->addKey(['status', 'starts_at']);
        // addForeignKey(field, table, tableField, onUpdate, onDelete)
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('appointments');
    }

    public function down(): void
    {
        $this->forge->dropTable('appointments', true);
    }
}
