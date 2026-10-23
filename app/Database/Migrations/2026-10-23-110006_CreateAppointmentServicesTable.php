<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * appointment_services - line items with name/duration/price snapshots.
 * See docs/database-schema.md section 3.6.
 */
class CreateAppointmentServicesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'appointment_id'   => ['type' => 'INT', 'unsigned' => true],
            'service_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'service_name'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'duration_minutes' => ['type' => 'INT', 'unsigned' => true],
            'price'            => ['type' => 'INT', 'unsigned' => true],
            'quantity'         => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('appointment_id');
        $this->forge->addKey('service_id');
        // addForeignKey(field, table, tableField, onUpdate, onDelete)
        $this->forge->addForeignKey('appointment_id', 'appointments', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('service_id', 'services', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('appointment_services');
    }

    public function down(): void
    {
        $this->forge->dropTable('appointment_services', true);
    }
}
