<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateScheduleLogsTable extends Migration
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
            'schedule_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'scheduled_for' => [
                'type' => 'DATETIME',
                'null' => false,
                'comment' => 'When this instance was scheduled to occur',
            ],
            'completed_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'comment' => 'When the user marked it as completed',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'completed', 'skipped', 'missed'],
                'null'       => false,
                'default'    => 'pending',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'User notes for this occurrence',
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
        $this->forge->addKey('schedule_id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('scheduled_for');
        $this->forge->addKey('status');

        // Foreign keys
        $this->forge->addForeignKey('schedule_id', 'schedules', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('schedule_logs');
    }

    public function down()
    {
        $this->forge->dropTable('schedule_logs');
    }
}
