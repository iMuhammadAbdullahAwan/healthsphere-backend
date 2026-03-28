<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddImageToSchedules extends Migration
{
    public function up()
    {
        $fields = [
            'image' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'comment' => 'Optional image URL or path for the schedule',
            ],
        ];

        $this->forge->addColumn('schedules', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('schedules', 'image');
    }
}
