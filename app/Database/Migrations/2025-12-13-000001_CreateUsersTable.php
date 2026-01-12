<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * CreateUsersTable Migration
 * 
 * Creates the users table with future-ready fields for subscription and admin delegation.
 * These fields are disabled by feature flags but included in schema for scalability.
 * 
 * @package HealthSphere
 * @version 1.0.0
 */
class CreateUsersTable extends Migration
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
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'unique'     => true,
            ],
            'password_hash' => [
                'type' => 'TEXT',
            ],
            'full_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'date_of_birth' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'gender' => [
                'type'       => 'ENUM',
                'constraint' => ['male', 'female', 'other'],
                'null'       => true,
            ],
            'phone' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'profile_img' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'default'    => 'defaults/images/default_user.jpg',
            ],

            // Future-ready fields (disabled by feature flags)
            'subscription_tier' => [
                'type'       => 'ENUM',
                'constraint' => ['free', 'basic', 'premium', 'enterprise'],
                'default'    => 'free',
                'null'       => true,
                'comment'    => 'For future subscription system',
            ],
            'subscription_expires_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'For future subscription system',
            ],
            'role' => [
                'type'       => 'ENUM',
                'constraint' => ['user', 'user_admin', 'super_admin'],
                'default'    => 'user',
                'comment'    => 'For future admin delegation system',
            ],

            // Session & Security
            'refresh_token' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'refresh_token_expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'password_reset_token' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'password_reset_expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'email_verified_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'last_login' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            // Timestamps
            'created_at' => [
                'type'    => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP'),
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
        $this->forge->addKey('created_at');
        $this->forge->createTable('users');
    }

    public function down()
    {
        $this->forge->dropTable('users');
    }
}
