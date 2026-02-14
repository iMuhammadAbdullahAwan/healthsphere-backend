<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFoodLogsTable extends Migration
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
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'food_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'image_path' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
                'null' => true,
            ],
            'portion_size' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'calories' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'null' => true,
                'default' => 0,
            ],
            'protein' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'null' => true,
                'default' => 0,
            ],
            'carbohydrates' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'null' => true,
                'default' => 0,
            ],
            'fat' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'null' => true,
                'default' => 0,
            ],
            'fiber' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'null' => true,
                'default' => 0,
            ],
            'sugar' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'null' => true,
                'default' => 0,
            ],
            'sodium' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'null' => true,
                'default' => 0,
            ],
            'other_nutrients' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'confidence_score' => [
                'type' => 'TINYINT',
                'constraint' => 3,
                'null' => true,
                'comment' => 'AI confidence score 0-100',
            ],
            'raw_analysis' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'Raw AI response for reference',
            ],
            'meal_type' => [
                'type' => 'ENUM',
                'constraint' => ['breakfast', 'lunch', 'dinner', 'snack'],
                'null' => true,
            ],
            'consumed_at' => [
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
        $this->forge->addKey('consumed_at');
        $this->forge->addKey('meal_type');
        $this->forge->addKey('deleted_at');

        // Foreign key constraint
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('food_logs');
    }

    public function down()
    {
        $this->forge->dropTable('food_logs');
    }
}
