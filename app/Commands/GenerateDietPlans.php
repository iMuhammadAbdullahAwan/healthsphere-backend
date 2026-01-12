<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\UserModel;
use App\Models\DietPlanModel;
use App\Models\HealthProfileModel;

/**
 * Cron Job: Generate daily diet plans for users
 * 
 * Usage: php spark diet:generate
 * Recommended cron: 0 5 * * * (daily at 5 AM)
 */
class GenerateDietPlans extends BaseCommand
{
    protected $group       = 'HealthSphere';
    protected $name        = 'diet:generate';
    protected $description = 'Generate/refresh diet plans for users with expiring plans';
    protected $usage       = 'diet:generate [--force]';
    protected $options     = [
        '--force' => 'Force regenerate all active plans',
    ];

    public function run(array $params)
    {
        $force = array_key_exists('force', $params);

        CLI::write('Starting diet plan generation...', 'yellow');

        $userModel = new UserModel();
        $dietPlanModel = new DietPlanModel();
        $healthProfileModel = new HealthProfileModel();

        // Get users with health profiles
        $users = $userModel->where('deleted_at IS NULL')
            ->where('status', 'active')
            ->findAll();

        $generated = 0;
        $skipped = 0;

        foreach ($users as $user) {
            try {
                // Check if user has health profile
                $profile = $healthProfileModel->where('user_id', $user['id'])->first();
                if (!$profile) {
                    $skipped++;
                    continue;
                }

                // Check existing active plan
                $activePlan = $dietPlanModel->where('user_id', $user['id'])
                    ->where('status', 'active')
                    ->first();

                // Skip if active plan exists and not forcing
                if ($activePlan && !$force) {
                    $endDate = strtotime($activePlan['end_date']);
                    if ($endDate > time()) {
                        $skipped++;
                        continue;
                    }
                    // Mark expired plan as completed
                    $dietPlanModel->update($activePlan['id'], ['status' => 'completed']);
                }

                // Generate new plan
                $this->generatePlanForUser($user, $profile, $dietPlanModel);
                $generated++;
            } catch (\Throwable $e) {
                log_message('error', "Failed to generate diet plan for user {$user['id']}: " . $e->getMessage());
            }
        }

        CLI::write("Generated {$generated} diet plans. Skipped {$skipped} users.", 'green');
    }

    /**
     * Generate a personalized diet plan for user
     */
    private function generatePlanForUser(array $user, array $profile, DietPlanModel $model): void
    {
        // Calculate daily calorie target based on profile
        $calories = $this->calculateCalorieTarget($profile);

        $planData = [
            'user_id'        => $user['id'],
            'plan_name'      => 'Weekly Plan - ' . date('M d, Y'),
            'start_date'     => date('Y-m-d'),
            'end_date'       => date('Y-m-d', strtotime('+7 days')),
            'calorie_target' => $calories,
            'status'         => 'active',
            'generated_by'   => 'AI_CRON'
        ];

        $model->insert($planData);

        CLI::write("  → Generated plan for user {$user['id']} ({$calories} cal/day)", 'light_gray');
    }

    /**
     * Calculate recommended daily calorie intake
     */
    private function calculateCalorieTarget(array $profile): int
    {
        $bmr = 2000; // Default

        // Basic BMR calculation (Mifflin-St Jeor)
        $weight = (float) ($profile['weight'] ?? 70);
        $height = (float) ($profile['height'] ?? 170);
        $age = (int) ($profile['age'] ?? 30);
        $gender = $profile['gender'] ?? 'male';

        if ($gender === 'male') {
            $bmr = (10 * $weight) + (6.25 * $height) - (5 * $age) + 5;
        } else {
            $bmr = (10 * $weight) + (6.25 * $height) - (5 * $age) - 161;
        }

        // Activity multiplier
        $activityLevel = $profile['activity_level'] ?? 'moderate';
        $multipliers = [
            'sedentary'  => 1.2,
            'light'      => 1.375,
            'moderate'   => 1.55,
            'active'     => 1.725,
            'very_active' => 1.9
        ];

        $calories = $bmr * ($multipliers[$activityLevel] ?? 1.55);

        // Adjust for health goals
        $bmi = $profile['bmi'] ?? 25;
        if ($bmi > 25) {
            $calories *= 0.85; // Deficit for weight loss
        } elseif ($bmi < 18.5) {
            $calories *= 1.15; // Surplus for weight gain
        }

        return (int) round($calories);
    }
}
