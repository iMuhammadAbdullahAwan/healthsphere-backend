<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWellnessScoresTable extends Migration
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
            'overall_score' => [
                'type'       => 'TINYINT',
                'constraint' => 3,
                'unsigned'   => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['optimal', 'fair', 'guarded', 'critical'],
                'default'    => 'fair',
            ],
            'breakdown' => [
                'type' => 'JSON',
                'null' => true,
                'comment' => 'Scores for each health domain',
            ],
            'ai_insight' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'score_date' => [
                'type' => 'DATE',
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
        $this->forge->addKey('user_id');
        $this->forge->addKey('score_date');
        
        // Ensure one snapshot per user per day
        $this->forge->addUniqueKey(['user_id', 'score_date']);

        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('wellness_scores');
    }

    public function down()
    {
        $this->forge->dropTable('wellness_scores');
    }
}
