<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\WellnessModel;
use App\Models\ScheduleLogModel;
use App\Models\DeviceReadingModel;
use App\Models\FoodLogModel;
use App\Models\ExerciseLogModel;
use App\Models\StepSessionModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

class WellnessController extends BaseController
{
    protected WellnessModel $wellnessModel;

    public function __construct()
    {
        $this->wellnessModel = new WellnessModel();
    }

    /**
     * Get real-time wellness status and insights
     * GET /api/wellness/status
     */
    public function getStatus(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            // Define the 7-day window
            $endDate = date('Y-m-d H:i:s');
            $startDate = date('Y-m-d H:i:s', strtotime('-7 days'));

            // 1. Metabolic Integrity (30%) - Based on Device Readings
            $metabolicScore = $this->calculateMetabolicScore($startDate, $endDate);

            // 2. Routine Adherence (30%) - Based on Schedule Logs
            $routineScore = $this->calculateRoutineScore($startDate, $endDate);

            // 3. Nutritional Balance (20%) - Based on Food Logs
            $nutritionScore = $this->calculateNutritionScore($startDate, $endDate);

            // 4. Activity & Vigor (20%) - Based on Steps & Exercises
            $activityScore = $this->calculateActivityScore($startDate, $endDate);

            // Calculate Overall Score
            $overallScore = round(
                ($metabolicScore * 0.30) + 
                ($routineScore * 0.30) + 
                ($nutritionScore * 0.20) + 
                ($activityScore * 0.20)
            );

            $status = $this->wellnessModel->getStatusFromScore($overallScore);

            // Determine Trend (comparing with previous 7-day period)
            $trend = $this->determineTrend($overallScore);

            // Generate AI Insight
            $insight = $this->generateAIInsight([
                'overall'   => $overallScore,
                'metabolic' => $metabolicScore,
                'routine'   => $routineScore,
                'nutrition' => $nutritionScore,
                'activity'  => $activityScore,
                'status'    => $status
            ]);

            $data = [
                'overall_score' => (int)$overallScore,
                'health_status' => $status,
                'trend'         => $trend,
                'ai_advisor'    => $insight,
                'domains' => [
                    'metabolic' => [
                        'score' => (int)$metabolicScore,
                        'label' => 'Metabolic Integrity',
                        'description' => 'Stability of vitals like glucose and BP.'
                    ],
                    'routine' => [
                        'score' => (int)$routineScore,
                        'label' => 'Routine Adherence',
                        'description' => 'Consistency in following medication and health tasks.'
                    ],
                    'nutrition' => [
                        'score' => (int)$nutritionScore,
                        'label' => 'Nutritional Balance',
                        'description' => 'Regularity and quality of meal logging.'
                    ],
                    'activity' => [
                        'score' => (int)$activityScore,
                        'label' => 'Activity & Vigor',
                        'description' => 'Physical movement and workout frequency.'
                    ]
                ],
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Save snapshot for today (async-ish/background)
            $this->saveDailySnapshot($data);

            return sendApiResponse($data, 'Wellness intelligence retrieved successfully');
        } catch (\Throwable $e) {
            log_message('error', 'Wellness status error: ' . $e->getMessage());
            return sendApiResponse(null, 'An error occurred while analyzing your wellness data', 500);
        }
    }

    /**
     * Get historical wellness trend
     * GET /api/wellness/history?days=30
     */
    public function getHistory(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $days = (int)($this->request->getVar('days') ?? 30);

            $history = $this->wellnessModel->builder()
                ->where('user_id', $this->current_user_id)
                ->orderBy('score_date', 'ASC')
                ->limit($days)
                ->get()
                ->getResultArray();

            // Manually decode breakdown (handles single or double encoding)
            foreach ($history as &$item) {
                if (isset($item['breakdown']) && is_string($item['breakdown']) && !empty($item['breakdown'])) {
                    $decoded = json_decode($item['breakdown'], true);
                    // If still a string after first decode, decode again (handles double-encoding)
                    if (is_string($decoded)) {
                        $decoded = json_decode($decoded, true);
                    }
                    $item['breakdown'] = $decoded ?? [];
                } elseif (empty($item['breakdown'])) {
                    $item['breakdown'] = [];
                }
            }

            return sendApiResponse($history, 'Wellness history retrieved successfully');
        } catch (\Throwable $e) {
            log_message('error', 'Wellness history error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve wellness history', 500);
        }
    }

    // --- Private Calculation Methods ---

    private function calculateMetabolicScore($start, $end): float
    {
        $deviceModel = new DeviceReadingModel();
        $readings = $deviceModel->builder()
            ->where('user_id', $this->current_user_id)
            ->where('recorded_at >=', $start)
            ->where('recorded_at <=', $end)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        if (empty($readings)) return 70.0;

        $normalCount = 0;
        foreach ($readings as $r) {
            if (isset($r['status']) && $r['status'] === 'normal') $normalCount++;
        }

        return round(($normalCount / count($readings)) * 100, 2);
    }

    private function calculateRoutineScore($start, $end): float
    {
        $logModel = new \App\Models\ScheduleLogModel();
        $historyModel = new \App\Models\ScheduleHistoryModel();

        // Get active logs
        $logs = $logModel->builder()
            ->where('user_id', $this->current_user_id)
            ->where('scheduled_for >=', $start)
            ->where('scheduled_for <=', $end)
            ->get()
            ->getResultArray();

        // Get historical logs
        $history = $historyModel->builder()
            ->where('user_id', $this->current_user_id)
            ->where('scheduled_for >=', $start)
            ->where('scheduled_for <=', $end)
            ->get()
            ->getResultArray();

        $totalItems = count($logs) + count($history);
        if ($totalItems === 0) return 75.0;

        $doneCount = 0;
        foreach ($logs as $l) {
            if (isset($l['status']) && ($l['status'] === 'completed' || $l['status'] === 'done')) $doneCount++;
        }
        foreach ($history as $h) {
            if (isset($h['status']) && ($h['status'] === 'completed' || $h['status'] === 'done')) $doneCount++;
        }

        return round(($doneCount / $totalItems) * 100, 2);
    }

    private function calculateNutritionScore($start, $end): float
    {
        $foodModel = new FoodLogModel();
        $logs = $foodModel->builder()
            ->where('user_id', $this->current_user_id)
            ->where('consumed_at >=', $start)
            ->where('consumed_at <=', $end)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        if (empty($logs)) return 50.0;

        $days = [];
        foreach ($logs as $l) {
            if (isset($l['consumed_at'])) {
                $day = substr($l['consumed_at'], 0, 10);
                $days[$day] = ($days[$day] ?? 0) + 1;
            }
        }

        $consistencyScore = 0;
        foreach ($days as $count) {
            if ($count >= 3) $consistencyScore += (100 / 7);
            elseif ($count >= 1) $consistencyScore += (50 / 7);
        }

        return min(100, round($consistencyScore, 2));
    }

    private function calculateActivityScore($start, $end): float
    {
        $userModel = new UserModel();
        $user = $userModel->find($this->current_user_id);
        $goal = (int)($user['daily_step_goal'] ?? 5000);

        $stepModel = new StepSessionModel();
        $steps = $stepModel->builder()
            ->where('user_id', $this->current_user_id)
            ->where('started_at >=', $start)
            ->where('started_at <=', $end)
            ->selectSum('steps')
            ->get()
            ->getRowArray();

        $totalSteps = (int)($steps['steps'] ?? 0);
        $avgDailySteps = $totalSteps / 7;
        $stepRatio = ($goal > 0) ? min(1, $avgDailySteps / $goal) : 1;

        $exerciseModel = new ExerciseLogModel();
        $workouts = $exerciseModel->builder()
            ->where('user_id', $this->current_user_id)
            ->where('performed_at >=', $start)
            ->where('performed_at <=', $end)
            ->countAllResults();

        $workoutRatio = min(1, $workouts / 3);

        return round((($stepRatio * 50) + ($workoutRatio * 50)), 2);
    }

    private function determineTrend($currentScore): string
    {
        $lastSnapshot = $this->wellnessModel->where('user_id', $this->current_user_id)
            ->orderBy('score_date', 'DESC')
            ->first();

        if (!$lastSnapshot) return 'stable';

        $diff = $currentScore - $lastSnapshot['overall_score'];
        if ($diff > 3) return 'improving';
        if ($diff < -3) return 'declining';
        return 'stable';
    }

    private function generateAIInsight($data): string
    {
        $status = $data['status'];
        $overall = $data['overall'];

        if ($status === 'optimal') {
            return "Your health intelligence is at an optimal level. You are maintaining excellent consistency across all pillars. Continue your current routine to sustain this vitality.";
        }

        if ($status === 'critical') {
            return "Alert: Your wellness indicators suggest a critical decline. Multiple missed routines combined with abnormal vitals require immediate attention. Please review your medication adherence and consult your healthcare provider.";
        }

        // Identify weakest domain
        $weakest = 'metabolic';
        $minScore = $data['metabolic'];

        foreach (['routine', 'nutrition', 'activity'] as $d) {
            if ($data[$d] < $minScore) {
                $minScore = $data[$d];
                $weakest = $d;
            }
        }

        $advice = [
            'metabolic' => "Your vitals show some instability. Focus on monitoring your triggers and ensuring your device readings are performed at consistent times.",
            'routine'   => "Consistency is key. You've missed several scheduled tasks this week. Setting stronger reminders could help bridge the adherence gap.",
            'nutrition' => "Your nutritional logging has been irregular. To get better insights, try to log at least three meals a day to help the AI better understand your metabolic patterns.",
            'activity'  => "Your physical vigor is lower than your goals. Even a 10-minute daily walk can significantly boost your overall health score and metabolic response."
        ];

        return "Your overall wellness is $status. " . $advice[$weakest];
    }

    private function saveDailySnapshot($data): void
    {
        $today = date('Y-m-d');
        $snapshot = [
            'user_id'       => $this->current_user_id,
            'overall_score' => $data['overall_score'],
            'status'        => $data['health_status'],
            'breakdown'     => $data['domains'],
            'ai_insight'    => $data['ai_advisor'],
            'score_date'    => $today
        ];

        $existing = $this->wellnessModel->builder()
            ->where('user_id', $this->current_user_id)
            ->where('score_date', $today)
            ->get()
            ->getRowArray();

        if ($existing) {
            $this->wellnessModel->update($existing['id'], $snapshot);
        } else {
            $this->wellnessModel->insert($snapshot);
        }
    }
}
