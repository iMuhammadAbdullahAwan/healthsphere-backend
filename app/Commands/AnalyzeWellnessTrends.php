<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\UserModel;
use App\Models\WellnessLogModel;

/**
 * Cron Job: Analyze wellness trends and send insights
 * 
 * Usage: php spark wellness:analyze
 * Recommended cron: 0 22 * * 0 (weekly on Sunday at 10 PM)
 */
class AnalyzeWellnessTrends extends BaseCommand
{
    protected $group       = 'HealthSphere';
    protected $name        = 'wellness:analyze';
    protected $description = 'Analyze user wellness data and generate weekly insights';
    protected $usage       = 'wellness:analyze [--user=<id>]';
    protected $options     = [
        '--user' => 'Analyze specific user ID (default: all active users)',
    ];

    public function run(array $params)
    {
        $specificUserId = $params['user'] ?? null;

        CLI::write('Starting wellness trend analysis...', 'yellow');

        $userModel = new UserModel();

        if ($specificUserId) {
            $users = [$userModel->find($specificUserId)];
        } else {
            $users = $userModel->where('deleted_at IS NULL')
                ->where('status', 'active')
                ->findAll();
        }

        $analyzed = 0;

        foreach ($users as $user) {
            if (!$user) continue;

            try {
                $insights = $this->analyzeUserWellness($user['id']);

                if (!empty($insights)) {
                    $this->saveInsights($user['id'], $insights);
                    $analyzed++;
                    CLI::write("  → Analyzed user {$user['id']}: " . count($insights) . " insights", 'light_gray');
                }
            } catch (\Throwable $e) {
                log_message('error', "Failed to analyze user {$user['id']}: " . $e->getMessage());
            }
        }

        CLI::write("Analyzed {$analyzed} users.", 'green');
    }

    /**
     * Analyze wellness data for a user
     */
    private function analyzeUserWellness(int $userId): array
    {
        $insights = [];
        $db = \Config\Database::connect();

        // Get last 7 days of wellness data
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $endDate = date('Y-m-d');

        // Analyze water intake
        $waterData = $db->table('wellness_logs')
            ->select('AVG(value) as avg_value, COUNT(*) as count')
            ->where('user_id', $userId)
            ->where('type', 'water_intake')
            ->where('logged_at >=', $startDate)
            ->get()
            ->getRowArray();

        if ($waterData && $waterData['count'] > 0) {
            $avgWater = (float) $waterData['avg_value'];
            if ($avgWater < 2000) {
                $insights[] = [
                    'type'       => 'hydration',
                    'severity'   => 'warning',
                    'message'    => "Your average daily water intake is {$avgWater}ml. Aim for at least 2000ml.",
                    'suggestion' => 'Set hourly reminders to drink water throughout the day.'
                ];
            } elseif ($avgWater >= 2500) {
                $insights[] = [
                    'type'       => 'hydration',
                    'severity'   => 'positive',
                    'message'    => "Excellent hydration! You're averaging {$avgWater}ml daily. 💧",
                    'suggestion' => 'Keep up the great work!'
                ];
            }
        }

        // Analyze exercise sessions
        $exerciseData = $db->table('exercise_sessions')
            ->select('COUNT(*) as count, AVG(duration_seconds) as avg_duration, AVG(posture_score) as avg_score')
            ->where('user_id', $userId)
            ->where('session_date >=', $startDate)
            ->where('completion_status', 'completed')
            ->get()
            ->getRowArray();

        if ($exerciseData && $exerciseData['count'] > 0) {
            $sessionCount = (int) $exerciseData['count'];
            $avgScore = (float) $exerciseData['avg_score'];

            if ($sessionCount < 3) {
                $insights[] = [
                    'type'       => 'exercise',
                    'severity'   => 'warning',
                    'message'    => "You completed only {$sessionCount} exercise sessions this week.",
                    'suggestion' => 'Aim for at least 3-4 sessions per week for optimal health.'
                ];
            } else {
                $insights[] = [
                    'type'       => 'exercise',
                    'severity'   => 'positive',
                    'message'    => "Great job! {$sessionCount} exercise sessions completed this week. 🏃",
                    'suggestion' => 'Keep the momentum going!'
                ];
            }

            if ($avgScore > 0 && $avgScore < 70) {
                $insights[] = [
                    'type'       => 'posture',
                    'severity'   => 'warning',
                    'message'    => "Your average posture score is " . round($avgScore) . "/100.",
                    'suggestion' => 'Focus on form over speed. Use AR guidance for corrections.'
                ];
            }
        } else {
            $insights[] = [
                'type'       => 'exercise',
                'severity'   => 'alert',
                'message'    => "No exercise sessions recorded this week.",
                'suggestion' => 'Start with just 10 minutes of daily movement. Every step counts!'
            ];
        }

        // Analyze food logging consistency
        $mealData = $db->table('meal_logs')
            ->select('COUNT(DISTINCT DATE(logged_at)) as days_logged')
            ->where('user_id', $userId)
            ->where('logged_at >=', $startDate)
            ->get()
            ->getRowArray();

        if ($mealData) {
            $daysLogged = (int) $mealData['days_logged'];

            if ($daysLogged < 5) {
                $insights[] = [
                    'type'       => 'nutrition',
                    'severity'   => 'warning',
                    'message'    => "You logged meals on only {$daysLogged} days this week.",
                    'suggestion' => 'Consistent logging helps AI provide better diet recommendations.'
                ];
            }
        }

        return $insights;
    }

    /**
     * Save insights to database and optionally notify user
     */
    private function saveInsights(int $userId, array $insights): void
    {
        $db = \Config\Database::connect();

        foreach ($insights as $insight) {
            $db->table('user_insights')->insert([
                'user_id'    => $userId,
                'type'       => $insight['type'],
                'severity'   => $insight['severity'],
                'message'    => $insight['message'],
                'suggestion' => $insight['suggestion'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        // Send summary notification
        $notificationService = new \App\Libraries\NotificationService();
        $notificationService->createNotification([
            'user_ids'   => [$userId],
            'created_by' => 0,
            'message'    => '📊 Your weekly wellness insights are ready! Check your dashboard.',
            'type'       => 'wellness_insight',
            'link'       => '/insights'
        ]);
    }
}
