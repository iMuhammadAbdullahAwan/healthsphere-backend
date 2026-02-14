<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateJobQueueTable extends Migration
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
            'type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'comment'    => 'Job type (send_email, send_notification, ml_inference, etc.)',
            ],
            'payload' => [
                'type'    => 'TEXT',
                'comment' => 'JSON encoded job data',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'processing', 'completed', 'failed'],
                'default'    => 'pending',
            ],
            'priority' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
                'comment'    => 'Higher number = more urgent',
            ],
            'attempts' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
                'comment'    => 'Number of retry attempts',
            ],
            'run_at' => [
                'type'    => 'DATETIME',
                'comment' => 'When to run this job',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'started_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'completed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'failed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'last_error' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'result' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => 'JSON encoded result',
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['status', 'run_at']); // Index for queue worker queries
        $this->forge->addKey('created_at');

        $this->forge->createTable('job_queue');
    }

    public function down()
    {
        $this->forge->dropTable('job_queue');
    }
}
