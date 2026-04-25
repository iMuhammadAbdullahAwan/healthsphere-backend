<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDeviceReadingsTable extends Migration
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
            'device_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'reading_value' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'reading_unit' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'image_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['low', 'normal', 'high'],
                'default'    => 'normal',
            ],
            'recorded_at' => [
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
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addKey('status');
        $this->forge->addKey('recorded_at');
        $this->forge->addKey('deleted_at');

        // Foreign key constraint
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('device_readings');
    }

    public function down()
    {
        $this->forge->dropTable('device_readings');
    }
}
