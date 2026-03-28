<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\ScheduleModel;
use App\Models\ScheduleLogModel;

/**
 * Generate schedule_logs for upcoming occurrences
 * Usage: php spark schedules:generate-logs --days=7
 */
class GenerateScheduleLogs extends BaseCommand
{
    protected $group = 'HealthSphere';
    protected $name = 'schedules:generate-logs';
    protected $description = 'Generate schedule log occurrences for active schedules';
    protected $usage = 'schedules:generate-logs [--days=<n>]';

    public function run(array $params)
    {
        $days = (int) ($params['days'] ?? CLI::getOption('days') ?? 7);
        $today = new \DateTime();
        $future = (clone $today)->modify("+{$days} days");

        CLI::write("Generating schedule logs for next {$days} days...", 'yellow');

        $scheduleModel = new ScheduleModel();
        $logModel = new ScheduleLogModel();

        $schedules = $scheduleModel->where('status', 'active')->findAll();
        $created = 0;

        foreach ($schedules as $s) {
            $startDate = new \DateTime($s['start_date']);
            $endDate = $s['end_date'] ? new \DateTime($s['end_date']) : null;
            $repeatType = $s['repeat_type'] ?? 'once';
            $repeatDays = [];
            if (!empty($s['repeat_days'])) {
                $rd = $s['repeat_days'];
                if (is_string($rd)) {
                    $rd = json_decode($rd, true) ?: [];
                }
                $repeatDays = is_array($rd) ? $rd : [];
            }

            // Normalize repeatDays to 0-6 weekday indices if values look like 1-7
            $normalizedWeekdays = array_map(function ($v) {
                $iv = (int) $v;
                if ($iv >= 1 && $iv <= 7) {
                    return ($iv % 7); // 7 -> 0
                }
                return $iv;
            }, $repeatDays);

            // Determine loop start
            $cursor = max($startDate, $today);

            while ($cursor <= $future) {
                $dateStr = $cursor->format('Y-m-d');

                // Skip if before schedule start
                if ($cursor < $startDate) {
                    $cursor->modify('+1 day');
                    continue;
                }

                // Respect end_date if set
                if ($endDate && $cursor > $endDate) {
                    break;
                }

                $shouldCreate = false;
                switch ($repeatType) {
                    case 'once':
                        if ($dateStr === $startDate->format('Y-m-d')) {
                            $shouldCreate = true;
                        }
                        break;

                    case 'daily':
                        $shouldCreate = true;
                        break;

                    case 'weekly':
                        $weekdayStart = (int) $startDate->format('w');
                        if ((int) $cursor->format('w') === $weekdayStart) {
                            $shouldCreate = true;
                        }
                        break;

                    case 'custom_days':
                        $wd = (int) $cursor->format('w');
                        if (in_array($wd, $normalizedWeekdays, true)) {
                            $shouldCreate = true;
                        }
                        break;

                    default:
                        // fallback: only on start date
                        if ($dateStr === $startDate->format('Y-m-d')) {
                            $shouldCreate = true;
                        }
                }

                if ($shouldCreate) {
                    // compose scheduled_for datetime
                    $time = $s['start_time'] ?? '00:00';
                    $scheduledFor = $dateStr . ' ' . (strlen($time) === 5 ? $time : substr($time, 0, 5)) . ':00';

                    // check existing
                    $exists = $logModel->where('schedule_id', $s['id'])
                        ->where('user_id', $s['user_id'])
                        ->where("DATE(scheduled_for)", $dateStr)
                        ->first();

                    if (!$exists) {
                        $logModel->insert([
                            'schedule_id' => $s['id'],
                            'user_id' => $s['user_id'],
                            'scheduled_for' => $scheduledFor,
                            'status' => 'pending',
                        ]);
                        $created++;
                    }
                }

                $cursor->modify('+1 day');
            }
        }

        CLI::write("Created {$created} schedule log(s).", 'green');
    }
}
