<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * auth_tokens - bearer tokens for the Flutter app. Only the SHA-256 hash of a token is stored;
 * the raw 64-hex token is shown once at login. One row per device/login.
 */
class CreateAuthTokensTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'owner_id'     => ['type' => 'INT', 'unsigned' => true],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'token_hash'   => ['type' => 'VARCHAR', 'constraint' => 64],
            'last_used_at' => ['type' => 'DATETIME', 'null' => true],
            'expires_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey('owner_id');
        // addForeignKey(field, table, tableField, onUpdate, onDelete)
        $this->forge->addForeignKey('owner_id', 'owner', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('auth_tokens');
    }

    public function down(): void
    {
        $this->forge->dropTable('auth_tokens', true);
    }
}
