<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class StatsController extends BaseController
{
    public function index(): ResponseInterface
    {
        try {
            $userId = $this->current_user_id;
            if (!$userId) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $db = \Config\Database::connect();

            // 1. Scheduler Stats
            $schedulerStats = $this->getSchedulerStats($db, $userId);

            // 2. Device Guider Stats
            $deviceStats = $this->getDeviceStats($db, $userId);

            // 3. Food Lens Stats
            $foodStats = $this->getFoodStats($db, $userId);

            // 4. Steps Stats
            $stepStats = $this->getStepStats($db, $userId);

            // 5. Therapist Stats
            $therapistStats = $this->getTherapistStats($db, $userId);

            $overallStats = [
                'scheduler' => $schedulerStats,
                'device_guider' => $deviceStats,
                'food_lens' => $foodStats,
                'steps' => $stepStats,
                'therapist' => $therapistStats,
            ];

            return sendApiResponse($overallStats, 'Overall stats retrieved successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Get overall stats error: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            return sendApiResponse(null, 'Failed to retrieve stats', 500);
        }
    }

    private function getSchedulerStats($db, $userId)
    {
        // Total Active Routines
        $activeRoutines = $db->table('schedules')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->countAllResults();

        // Overall Adherence Rate
        $completedLogs = $db->table('schedule_history')
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->countAllResults();

        $missedSkippedLogs = $db->table('schedule_history')
            ->where('user_id', $userId)
            ->whereIn('status', ['canceled', 'skipped', 'missed'])
            ->countAllResults();

        $missedSkippedLogs += $db->table('schedule_logs')
            ->where('user_id', $userId)
            ->whereIn('status', ['canceled', 'skipped', 'missed'])
            ->countAllResults();

        $totalLogs = $completedLogs + $missedSkippedLogs;
        $adherenceRate = $totalLogs > 0 ? round(($completedLogs / $totalLogs) * 100, 2) : 0;

        // Current Streak
        $completedDates = $db->table('schedule_history')
            ->select('DATE(scheduled_for) as date')
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->groupBy('DATE(scheduled_for)')
            ->orderBy('DATE(scheduled_for)', 'DESC')
            ->get()
            ->getResultArray();

        $streak = 0;
        $currentDate = new \DateTime();
        $currentDate->setTime(0, 0, 0);
        $lastDate = null;

        foreach ($completedDates as $row) {
            $date = new \DateTime($row['date']);
            $date->setTime(0, 0, 0);

            if ($lastDate === null) {
                $diff = $currentDate->diff($date)->days;
                if ($diff <= 1) { // Today or yesterday
                    $streak++;
                    $lastDate = $date;
                } else {
                    break;
                }
            } else {
                if ($lastDate->diff($date)->days == 1) {
                    $streak++;
                    $lastDate = $date;
                } else {
                    break;
                }
            }
        }

        // Medicine Adherence
        $medCompleted = $db->table('schedule_history h')
            ->join('schedules s', 'h.schedule_id = s.id')
            ->where('h.user_id', $userId)
            ->where('s.schedule_type', 'medicine')
            ->where('h.status', 'completed')
            ->countAllResults();

        $medMissed = $db->table('schedule_history h')
            ->join('schedules s', 'h.schedule_id = s.id')
            ->where('h.user_id', $userId)
            ->where('s.schedule_type', 'medicine')
            ->whereIn('h.status', ['canceled', 'skipped', 'missed'])
            ->countAllResults();

        $medMissed += $db->table('schedule_logs l')
            ->join('schedules s', 'l.schedule_id = s.id')
            ->where('l.user_id', $userId)
            ->where('s.schedule_type', 'medicine')
            ->whereIn('l.status', ['canceled', 'skipped', 'missed'])
            ->countAllResults();

        $totalMed = $medCompleted + $medMissed;
        $medAdherence = $totalMed > 0 ? round(($medCompleted / $totalMed) * 100, 2) : 0;

        // Average Water Intake
        $waterHistory = $db->table('schedule_history h')
            ->select('h.schedule_snapshot, DATE(h.scheduled_for) as date')
            ->join('schedules s', 'h.schedule_id = s.id')
            ->where('h.user_id', $userId)
            ->where('s.schedule_type', 'water')
            ->where('h.status', 'completed')
            ->get()
            ->getResultArray();

        $totalWaterMl = 0;
        $waterDays = [];
        foreach ($waterHistory as $wh) {
            $snap = is_string($wh['schedule_snapshot']) ? json_decode($wh['schedule_snapshot'], true) : $wh['schedule_snapshot'];
            if (is_string($snap)) $snap = json_decode($snap, true); // double decode

            $amount = $snap['water_details']['amount_ml'] ?? 0;
            $totalWaterMl += (float)$amount;
            $waterDays[$wh['date']] = true;
        }
        $avgWater = count($waterDays) > 0 ? round($totalWaterMl / count($waterDays), 2) : 0;

        // Sleep Duration
        $sleepHistory = $db->table('schedule_history h')
            ->select('h.schedule_snapshot, DATE(h.scheduled_for) as date')
            ->join('schedules s', 'h.schedule_id = s.id')
            ->where('h.user_id', $userId)
            ->where('s.schedule_type', 'sleep')
            ->where('h.status', 'completed')
            ->get()
            ->getResultArray();

        $totalSleepMins = 0;
        $sleepCount = 0;
        foreach ($sleepHistory as $sh) {
            $snap = is_string($sh['schedule_snapshot']) ? json_decode($sh['schedule_snapshot'], true) : $sh['schedule_snapshot'];
            if (is_string($snap)) $snap = json_decode($snap, true); // double decode

            // Try to extract hours/minutes or calculate from sleep_time to wake_time
            $sleepMins = 0;
            if (isset($snap['sleep_details'])) {
                $details = $snap['sleep_details'];
                if (isset($details['duration_minutes'])) {
                    $sleepMins = (float)$details['duration_minutes'];
                } elseif (isset($details['target_hours'])) {
                    $sleepMins = (float)$details['target_hours'] * 60;
                }
            }
            if ($sleepMins > 0) {
                $totalSleepMins += $sleepMins;
                $sleepCount++;
            }
        }
        $avgSleepHours = $sleepCount > 0 ? round(($totalSleepMins / 60) / $sleepCount, 2) : 0;

        // Count fields
        $totalSchedules = $db->table('schedules')
            ->where('user_id', $userId)
            ->countAllResults();

        $totalMedicineSchedules = $db->table('schedules')
            ->where('user_id', $userId)
            ->where('schedule_type', 'medicine')
            ->countAllResults();

        $totalWaterIntakes = $db->table('schedule_history h')
            ->join('schedules s', 'h.schedule_id = s.id')
            ->where('h.user_id', $userId)
            ->where('s.schedule_type', 'water')
            ->where('h.status', 'completed')
            ->countAllResults();

        $totalSleepLogs = $sleepCount;

        return [
            'total_active_routines' => $activeRoutines,
            'total_schedules_count' => $totalSchedules,
            'total_completed_logs_count' => $completedLogs,
            'total_medicine_schedules_count' => $totalMedicineSchedules,
            'total_water_intakes_count' => $totalWaterIntakes,
            'total_sleep_logs_count' => $totalSleepLogs,
            'overall_adherence_rate_percent' => $adherenceRate,
            'current_streak_days' => $streak,
            'medicine_adherence_percent' => $medAdherence,
            'average_water_intake_ml' => $avgWater,
            'average_sleep_duration_hours' => $avgSleepHours,
        ];
    }

    private function getDeviceStats($db, $userId)
    {
        // Total Lifetime Scans
        $totalScans = $db->table('device_readings')
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->countAllResults();

        // Device Usage Breakdown
        $breakdownQuery = $db->table('device_readings')
            ->select('device_name, COUNT(*) as count')
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->groupBy('device_name')
            ->get()
            ->getResultArray();

        $breakdown = [];
        foreach ($breakdownQuery as $row) {
            $breakdown[$row['device_name'] ?: 'Unknown'] = (int)$row['count'];
        }

        // Biomarker Stability
        $normalScans = $db->table('device_readings')
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->where('status', 'normal')
            ->countAllResults();

        $stabilityPercent = $totalScans > 0 ? round(($normalScans / $totalScans) * 100, 2) : 0;

        // Recent Abnormalities
        $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
        $recentAbnormal = $db->table('device_readings')
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->where('recorded_at >=', $thirtyDaysAgo)
            ->whereIn('status', ['high', 'low'])
            ->countAllResults();

        // Count fields
        $totalNormalReadings = $normalScans;
        $totalAbnormalReadings = $totalScans - $normalScans;

        return [
            'total_lifetime_scans' => $totalScans,
            'total_normal_readings_count' => $totalNormalReadings,
            'total_abnormal_readings_count' => $totalAbnormalReadings,
            'recent_abnormalities_30d_count' => $recentAbnormal,
            'device_usage_breakdown' => $breakdown,
            'biomarker_stability_percent' => $stabilityPercent,
        ];
    }

    private function getFoodStats($db, $userId)
    {
        $foodLogs = $db->table('food_logs')
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        $totalMeals = count($foodLogs);

        $totalCals = 0;
        $totalProtein = 0;
        $totalCarbs = 0;
        $totalFat = 0;
        $days = [];

        foreach ($foodLogs as $log) {
            $date = date('Y-m-d', strtotime($log['consumed_at']));
            $days[$date] = true;

            $totalCals += (float)$log['calories'];
            $totalProtein += (float)$log['protein'];
            $totalCarbs += (float)$log['carbohydrates'];
            $totalFat += (float)$log['fat'];
        }

        $numDays = count($days);
        $avgCals = $numDays > 0 ? round($totalCals / $numDays, 2) : 0;
        $avgProtein = $numDays > 0 ? round($totalProtein / $numDays, 2) : 0;
        $avgCarbs = $numDays > 0 ? round($totalCarbs / $numDays, 2) : 0;
        $avgFat = $numDays > 0 ? round($totalFat / $numDays, 2) : 0;
        $mealConsistency = $numDays > 0 ? round($totalMeals / $numDays, 2) : 0;

        return [
            'total_meals_logged_count' => $totalMeals,
            'days_with_meals_count' => $numDays,
            'daily_caloric_average' => $avgCals,
            'macronutrient_averages' => [
                'protein' => $avgProtein,
                'carbohydrates' => $avgCarbs,
                'fat' => $avgFat,
            ],
            'meal_consistency_per_day' => $mealConsistency,
        ];
    }

    private function getStepStats($db, $userId)
    {
        $sessions = $db->table('step_sessions')
            ->where('user_id', $userId)
            ->get()
            ->getResultArray();

        $totalSteps = 0;
        $totalDistance = 0;
        $totalCalories = 0;

        $stepsPerDay = [];

        foreach ($sessions as $s) {
            $totalSteps += (int)$s['steps'];
            $totalDistance += (float)$s['distance_km'];
            $totalCalories += (float)$s['calories'];

            $date = date('Y-m-d', strtotime($s['started_at'] ?? $s['created_at']));
            if (!isset($stepsPerDay[$date])) {
                $stepsPerDay[$date] = 0;
            }
            $stepsPerDay[$date] += (int)$s['steps'];
        }

        // Get user daily step goal
        $userRow = $db->table('users')->select('daily_step_goal')->where('id', $userId)->get()->getRowArray();
        $goal = $userRow ? (int)($userRow['daily_step_goal'] ?? 10000) : 10000;
        if ($goal <= 0) $goal = 10000;

        $goalMetDays = 0;
        foreach ($stepsPerDay as $date => $steps) {
            if ($steps >= $goal) {
                $goalMetDays++;
            }
        }

        $totalDays = count($stepsPerDay);
        $goalAchievementRate = $totalDays > 0 ? round(($goalMetDays / $totalDays) * 100, 2) : 0;
        $totalSessions = count($sessions);

        return [
            'total_lifetime_steps' => $totalSteps,
            'total_sessions_count' => $totalSessions,
            'days_tracked_count' => $totalDays,
            'days_goal_met_count' => $goalMetDays,
            'total_distance_km' => round($totalDistance, 2),
            'total_calories_burned' => round($totalCalories, 2),
            'goal_achievement_rate_percent' => $goalAchievementRate,
        ];
    }

    private function getTherapistStats($db, $userId)
    {
        $exercises = $db->table('exercise_logs')
            ->where('user_id', $userId)
            ->get()
            ->getResultArray();

        $totalExercises = count($exercises);
        $totalTime = 0;
        $totalCals = 0;

        $exerciseCounts = [];

        foreach ($exercises as $ex) {
            $totalTime += (int)$ex['duration_minutes'];
            $totalCals += (float)$ex['calories_burned'];

            $name = $ex['exercise_name'] ?: 'Unknown';
            if (!isset($exerciseCounts[$name])) {
                $exerciseCounts[$name] = 0;
            }
            $exerciseCounts[$name]++;
        }

        $topExercise = null;
        $maxCount = 0;
        foreach ($exerciseCounts as $name => $count) {
            if ($count > $maxCount) {
                $maxCount = $count;
                $topExercise = $name;
            }
        }

        $uniqueExercises = count($exerciseCounts);

        return [
            'total_exercises_done_count' => $totalExercises,
            'unique_exercises_count' => $uniqueExercises,
            'total_time_invested_minutes' => $totalTime,
            'calories_burned' => round($totalCals, 2),
            'top_exercise' => $topExercise,
        ];
    }
}
