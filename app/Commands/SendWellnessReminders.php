<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\UserModel;
use App\Libraries\NotificationService;

/**
 * Cron Job: Send daily wellness reminders
 * 
 * Usage: php spark wellness:reminders
 * Recommended cron: 0 8 * * * (daily at 8 AM)
 */
class SendWellnessReminders extends BaseCommand
{
    protected $group       = 'HealthSphere';
    protected $name        = 'wellness:reminders';
    protected $description = 'Send daily wellness reminders to users (water intake, exercise, meals)';
    protected $usage       = 'wellness:reminders [--type=<type>]';
    protected $options     = [
        '--type' => 'Reminder type: water, exercise, meal, all (default: all)',
    ];

    public function run(array $params)
    {
        $type = $params['type'] ?? 'all';

        CLI::write('Starting wellness reminders...', 'yellow');

        $userModel = new UserModel();
        $notificationService = new NotificationService();

        // Get active users who have notifications enabled
        $users = $userModel->where('deleted_at IS NULL')
            ->where('status', 'active')
            ->findAll();

        if (empty($users)) {
            CLI::write('No active users found.', 'yellow');
            return;
        }

        $sentCount = 0;
        $hour = (int) date('H');

        foreach ($users as $user) {
            $reminders = $this->getRemindersByTimeAndType($hour, $type);

            foreach ($reminders as $reminder) {
                try {
                    $notificationService->createNotification([
                        'user_ids'   => [$user['id']],
                        'created_by' => 0, // System
                        'message'    => $reminder['message'],
                        'type'       => 'wellness_reminder',
                        'link'       => $reminder['link'] ?? null,
                    ]);
                    $sentCount++;
                } catch (\Throwable $e) {
                    log_message('error', "Failed to send reminder to user {$user['id']}: " . $e->getMessage());
                }
            }
        }

        CLI::write("Sent {$sentCount} wellness reminders to " . count($users) . " users.", 'green');
    }

    /**
     * Get appropriate reminders based on time of day and type
     */
    private function getRemindersByTimeAndType(int $hour, string $type): array
    {
        $reminders = [];

        // Morning reminders (6-9 AM)
        if ($hour >= 6 && $hour < 9) {
            if ($type === 'all' || $type === 'water') {
                $reminders[] = [
                    'message' => '💧 Good morning! Start your day with a glass of water.',
                    'link'    => '/wellness/water'
                ];
            }
            if ($type === 'all' || $type === 'meal') {
                $reminders[] = [
                    'message' => '🍳 Time for a healthy breakfast! Check your meal plan.',
                    'link'    => '/diet-plans/active'
                ];
            }
        }

        // Mid-morning (10-11 AM)
        if ($hour >= 10 && $hour < 12) {
            if ($type === 'all' || $type === 'water') {
                $reminders[] = [
                    'message' => '💧 Stay hydrated! Have you had your morning water?',
                    'link'    => '/wellness/water'
                ];
            }
        }

        // Lunch time (12-2 PM)
        if ($hour >= 12 && $hour < 14) {
            if ($type === 'all' || $type === 'meal') {
                $reminders[] = [
                    'message' => '🥗 Lunch time! Follow your personalized diet plan.',
                    'link'    => '/diet-plans/active'
                ];
            }
        }

        // Afternoon (3-5 PM)
        if ($hour >= 15 && $hour < 17) {
            if ($type === 'all' || $type === 'exercise') {
                $reminders[] = [
                    'message' => '🏃 Time for some movement! Start an exercise session.',
                    'link'    => '/exercises'
                ];
            }
            if ($type === 'all' || $type === 'water') {
                $reminders[] = [
                    'message' => '💧 Afternoon hydration check! Drink some water.',
                    'link'    => '/wellness/water'
                ];
            }
        }

        // Evening (6-8 PM)
        if ($hour >= 18 && $hour < 20) {
            if ($type === 'all' || $type === 'meal') {
                $reminders[] = [
                    'message' => '🍽️ Dinner time! Check your evening meal recommendations.',
                    'link'    => '/diet-plans/active'
                ];
            }
        }

        // Night (9-10 PM)
        if ($hour >= 21 && $hour < 22) {
            if ($type === 'all') {
                $reminders[] = [
                    'message' => '🌙 Great job today! Review your wellness progress.',
                    'link'    => '/wellness/summary'
                ];
            }
        }

        return $reminders;
    }
}
