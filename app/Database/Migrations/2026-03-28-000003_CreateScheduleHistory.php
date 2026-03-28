<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateScheduleHistory extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'original_log_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ],
            'schedule_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => false,
            ],
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => false,
            ],
            'scheduled_for' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => '30',
                'null' => false,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'notified_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'completed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'schedule_snapshot' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'archived_at' => [
                'type' => 'DATETIME',
                'null' => true,
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
        $this->forge->addKey('original_log_id');
        $this->forge->createTable('schedule_history');
    }

    public function down()
    {
        $this->forge->dropTable('schedule_history');
    }
}
