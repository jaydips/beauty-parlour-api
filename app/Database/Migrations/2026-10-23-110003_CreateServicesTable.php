<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * services - the price list (soft-deleted). Prices are whole rupees.
 * See docs/database-schema.md section 3.3.
 */
class CreateServicesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'category'         => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'duration_minutes' => ['type' => 'INT', 'unsigned' => true, 'default' => 30],
            'price'            => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'is_active'        => ['type' => 'BOOLEAN', 'default' => 1],
            'sort_order'       => ['type' => 'INT', 'default' => 0],
            'notes'            => ['type' => 'TEXT', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['is_active', 'sort_order']);
        $this->forge->createTable('services');
    }

    public function down(): void
    {
        $this->forge->dropTable('services', true);
    }
}
