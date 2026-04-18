<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStepSessionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'steps' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'distance_km' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'duration_seconds' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'calories' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
            ],
            'started_at' => [
                'type' => 'DATETIME',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('step_sessions');
    }

    public function down()
    {
        $this->forge->dropTable('step_sessions');
    }
}
