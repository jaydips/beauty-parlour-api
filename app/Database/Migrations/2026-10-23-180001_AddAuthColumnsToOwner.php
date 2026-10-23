<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Phase 2: credentials on the owner row. ADD COLUMN works on the hosting SQLite 3.7.17;
 * down() uses dropColumn(), which only works locally / on MySQL (table rebuild needs SQLite >= 3.16).
 */
class AddAuthColumnsToOwner extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('owner', [
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'last_login_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('owner', ['password_hash', 'last_login_at']);
    }
}
