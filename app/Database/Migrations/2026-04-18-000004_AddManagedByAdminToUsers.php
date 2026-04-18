<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddManagedByAdminToUsers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'managed_by_admin_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'role',
            ],
        ]);

        $this->forge->addKey('managed_by_admin_id');
        $this->forge->addForeignKey('managed_by_admin_id', 'users', 'id', 'SET NULL', 'SET NULL', 'users_managed_by_admin_fk');
        $this->forge->processIndexes('users');
    }

    public function down()
    {
        $this->forge->dropForeignKey('users', 'users_managed_by_admin_fk');
        $this->forge->dropColumn('users', 'managed_by_admin_id');
    }
}
