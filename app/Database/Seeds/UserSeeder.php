<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $userModel = model('App\Models\UserModel');

        // Clean up existing users to avoid duplicates
        $emails = ['super@admin.com', 'manager@admin.com', 'user@healthsphere.com'];
        foreach ($emails as $email) {
            $existing = $userModel->where('email', $email)->first();
            if ($existing) {
                $userModel->delete($existing['id'], true); // Hard delete
            }
        }

        // 1. Super Admin
        $superAdmin = [
            'email' => 'super@admin.com',
            'password' => 'password123', // Model should hash it
            'full_name' => 'Super Admin',
            'role' => 'super_admin',
            'profile_img' => 'https://ui-avatars.com/api/?name=Super+Admin&background=0D8ABC&color=fff',
        ];
        $userModel->insert($superAdmin);

        // 2. User Admin (Manager)
        $manager = [
            'email' => 'manager@admin.com',
            'password' => 'password123',
            'full_name' => 'Manager User',
            'role' => 'user_admin',
            'profile_img' => 'https://ui-avatars.com/api/?name=Manager+User&background=6c5ce7&color=fff',
        ];
        $userModel->insert($manager);

        // 3. Standard User
        $user = [
            'email' => 'user@healthsphere.com',
            'password' => 'password123',
            'full_name' => 'John Doe',
            'role' => 'user',
            'profile_img' => 'https://ui-avatars.com/api/?name=John+Doe&background=ff7675&color=fff',
        ];
        $userModel->insert($user);
    }
}
