<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSchedulesTable extends Migration
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
            'schedule_type' => [
                'type'       => 'ENUM',
                'constraint' => ['medicine', 'food', 'water', 'running', 'sleep', 'custom'],
                'null'       => false,
            ],
            // Basic Info
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            // Time & Repeat
            'start_date' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'start_time' => [
                'type' => 'TIME',
                'null' => false,
            ],
            'repeat_type' => [
                'type'       => 'ENUM',
                'constraint' => ['once', 'daily', 'weekly', 'custom_days'],
                'null'       => false,
                'default'    => 'once',
            ],
            'repeat_days' => [
                'type' => 'JSON',
                'null' => true,
                'comment' => 'Array of day numbers [1-7] for weekly/custom schedules',
            ],
            'end_condition' => [
                'type'       => 'ENUM',
                'constraint' => ['never', 'on_date', 'after_occurrences'],
                'null'       => false,
                'default'    => 'never',
            ],
            'end_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'max_occurrences' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            // Reminder Settings
            'reminder_enabled' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
            ],
            'reminder_mode' => [
                'type'       => 'ENUM',
                'constraint' => ['notification', 'voice', 'both'],
                'null'       => false,
                'default'    => 'notification',
            ],
            'voice_command_text' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            // Status
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['active', 'paused', 'completed'],
                'null'       => false,
                'default'    => 'active',
            ],
            // Type-Specific JSON Fields
            'medicine_details' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'food_details' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'water_details' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'running_details' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'sleep_details' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'custom_details' => [
                'type' => 'JSON',
                'null' => true,
            ],
            // Timestamps
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
        $this->forge->addKey('user_id');
        $this->forge->addKey('schedule_type');
        $this->forge->addKey('status');
        $this->forge->addKey('start_date');

        // Foreign key constraint
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('schedules');
    }

    public function down()
    {
        $this->forge->dropTable('schedules');
    }
}
