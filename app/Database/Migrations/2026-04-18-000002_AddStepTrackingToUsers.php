<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddStepTrackingToUsers extends Migration
{
    public function up()
    {
        $fields = [
            'step_tracking_enabled' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'after'      => 'last_login',
            ],
            'daily_step_goal' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 10000,
                'after'      => 'step_tracking_enabled',
            ],
        ];
        $this->forge->addColumn('users', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('users', ['step_tracking_enabled', 'daily_step_goal']);
    }
}
