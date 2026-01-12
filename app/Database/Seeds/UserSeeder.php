<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $userModel = model('App\Models\UserModel');
        $healthModel = model('App\Models\HealthProfileModel');
        $wellnessModel = model('App\Models\WellnessLogModel');
        $nutritionSummaryModel = model('App\Models\DailyNutritionSummaryModel');
        $sessionModel = model('App\Models\ExerciseSessionModel');
        $exerciseModel = model('App\Models\ExerciseModel');

        // Clean up existing users to avoid duplicates
        // Note: In a real app, you might not want to delete, but for seeding it's okay.
        // We delete by email to ensure clean state for these demo accounts.
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
            'password' => 'password123', // Model hashes it
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
        $userId = $userModel->insert($user);

        // Seed Data for Standard User
        if ($userId) {
            // Health Profile
            $healthModel->insert([
                'user_id' => $userId,
                'height' => 180,
                'weight' => 75,
                'activity_level' => 'active',
                'bmi' => 23.1,
                'health_goals' => json_encode(['Build muscle']),
                'medical_conditions' => json_encode([]),
                'allergies' => json_encode(['Peanuts']),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Wellness Logs
            $wellnessModel->insert([
                'user_id' => $userId,
                'mood_score' => 8,
                'stress_level' => 3,
                'energy_level' => 7,
                'sleep_hours' => 7.5,
                'water_intake_ml' => 2500,
                'notes' => 'Feeling good today!',
                'log_date' => date('Y-m-d'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

             // Nutrition Summary
            $nutritionSummaryModel->insert([
                'user_id' => $userId,
                'date' => date('Y-m-d'),
                'total_calories' => 1850,
                'total_protein' => 120,
                'total_carbs' => 200,
                'total_fat' => 60,
                'water_intake' => 2000,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Create an exercise
            $exId = $exerciseModel->insert([
                'name' => 'Morning Yoga',
                'category' => 'Flexibility',
                'difficulty_level' => 'beginner',
                'duration_seconds' => 1200,
                'video_url' => 'https://example.com/yoga',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            if ($exId) {
                $sessionModel->insert([
                    'user_id' => $userId,
                    'exercise_id' => $exId,
                    'start_time' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                    'end_time' => date('Y-m-d H:i:s'),
                    'duration_seconds' => 1200,
                    'status' => 'completed',
                    'posture_score' => 95,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
