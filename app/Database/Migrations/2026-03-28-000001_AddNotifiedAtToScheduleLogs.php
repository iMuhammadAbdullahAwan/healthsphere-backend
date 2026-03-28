<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNotifiedAtToScheduleLogs extends Migration
{
    public function up()
    {
        $fields = [
            'notified_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
        ];
        $this->forge->addColumn('schedule_logs', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('schedule_logs', 'notified_at');
    }
}
