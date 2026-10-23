<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * customers - phone (10 digits) is unique across soft-deleted rows too
 * (no partial unique indexes on the hosting SQLite 3.7.17).
 * See docs/database-schema.md section 3.4.
 */
class CreateCustomersTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 100],
            'phone'           => ['type' => 'VARCHAR', 'constraint' => 15],
            'alt_phone'       => ['type' => 'VARCHAR', 'constraint' => 15, 'null' => true],
            'gender'          => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'birthday'        => ['type' => 'DATE', 'null' => true],
            'notes'           => ['type' => 'TEXT', 'null' => true],
            'whatsapp_opt_in' => ['type' => 'BOOLEAN', 'default' => 1],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('phone');
        $this->forge->addKey('name');
        $this->forge->createTable('customers');
    }

    public function down(): void
    {
        $this->forge->dropTable('customers', true);
    }
}
