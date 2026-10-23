<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * owner - the single parlour owner (profile only; auth columns arrive in Phase 2 via ADD COLUMN).
 * See docs/database-schema.md section 3.1.
 */
class CreateOwnerTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'phone'      => ['type' => 'VARCHAR', 'constraint' => 15],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('owner');
    }

    public function down(): void
    {
        $this->forge->dropTable('owner', true);
    }
}
