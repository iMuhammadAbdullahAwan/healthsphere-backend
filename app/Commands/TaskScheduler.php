<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Task Scheduler
 * 
 * Master scheduler that runs all scheduled tasks based on their frequency.
 * Add this to cron to run every minute:
 * * * * * * cd /path/to/project && php spark task:schedule >> /dev/null 2>&1
 * 
 * Usage: php spark task:schedule
 */
class TaskScheduler extends BaseCommand
{
    protected $group       = 'HealthSphere';
    protected $name        = 'task:schedule';
    protected $description = 'Run scheduled tasks based on current time';
    protected $usage       = 'task:schedule [--list]';
    protected $options     = [
        '--list' => 'List all scheduled tasks without running them',
    ];

    /**
     * Scheduled tasks configuration
     * 
     * Each task has:
     * - command: The spark command to execute
     * - frequency: When to run (everyMinute, hourly, daily, weekly, monthly)
     * - time: Specific time for daily/weekly tasks (HH:MM format)
     * - day: Day of week for weekly (0=Sunday, 6=Saturday)
     * - enabled: Whether the task is active
     */
    private array $tasks = [
        [
            'name'      => 'Wellness Reminders',
            'command'   => 'wellness:reminders',
            'frequency' => 'hourly',
            'time'      => '00', // Run at minute 0 of every hour
            'hours'     => [6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22], // 6 AM to 10 PM
            'enabled'   => true,
        ],
        [
            'name'      => 'Generate Diet Plans',
            'command'   => 'diet:generate',
            'frequency' => 'daily',
            'time'      => '05:00', // 5 AM daily
            'enabled'   => true,
        ],
        [
            'name'      => 'Analyze Wellness Trends',
            'command'   => 'wellness:analyze',
            'frequency' => 'weekly',
            'time'      => '22:00', // 10 PM
            'day'       => 0, // Sunday
            'enabled'   => true,
        ],
        [
            'name'      => 'Clear Cache',
            'command'   => 'cache:clear',
            'frequency' => 'daily',
            'time'      => '03:00', // 3 AM daily
            'enabled'   => true,
        ],
        [
            'name'      => 'Clear Old Logs',
            'command'   => 'logs:clear',
            'frequency' => 'weekly',
            'time'      => '02:00', // 2 AM
            'day'       => 1, // Monday
            'enabled'   => true,
        ],
        [
            'name'      => 'Process Failed Jobs Retry',
            'command'   => 'queue:retry-failed',
            'frequency' => 'hourly',
            'time'      => '15', // At minute 15 of every hour
            'enabled'   => false, // Disabled by default
        ],
        [
            'name'      => 'Generate Schedule Logs',
            'command'   => 'schedules:generate-logs --days=7',
            'frequency' => 'daily',
            'time'      => '00:00', // Daily at midnight
            'enabled'   => true,
        ],
        [
            'name'      => 'Dispatch Schedule Reminders',
            'command'   => 'schedules:dispatch-reminders',
            'frequency' => 'everyMinute',
            'enabled'   => true,
        ],
    ];

    public function run(array $params)
    {
        // If --list flag, just show scheduled tasks
        if (array_key_exists('list', $params)) {
            $this->listTasks();
            return;
        }

        $now = new \DateTime();
        $currentHour = (int) $now->format('H');
        $currentMinute = (int) $now->format('i');
        $currentDay = (int) $now->format('w'); // 0 = Sunday
        $currentTime = $now->format('H:i');

        CLI::write('[' . $now->format('Y-m-d H:i:s') . '] Running task scheduler...', 'yellow');

        $ranTasks = 0;
        $skippedTasks = 0;

        foreach ($this->tasks as $task) {
            if (!$task['enabled']) {
                $skippedTasks++;
                continue;
            }

            if ($this->shouldRun($task, $currentHour, $currentMinute, $currentDay, $currentTime)) {
                CLI::write("→ Running: {$task['name']}", 'light_gray');

                try {
                    $command = "php spark {$task['command']}";
                    exec($command . ' 2>&1', $output, $returnCode);

                    if ($returnCode === 0) {
                        CLI::write("  ✓ Completed: {$task['name']}", 'green');
                        $this->logTaskRun($task, 'success');
                    } else {
                        CLI::write("  ✗ Failed: {$task['name']}", 'red');
                        $this->logTaskRun($task, 'failed', implode("\n", $output));
                    }

                    $ranTasks++;
                } catch (\Throwable $e) {
                    CLI::write("  ✗ Error: {$e->getMessage()}", 'red');
                    $this->logTaskRun($task, 'error', $e->getMessage());
                }
            }
        }

        if ($ranTasks === 0) {
            CLI::write('No tasks scheduled for this time.', 'light_gray');
        } else {
            CLI::write("Executed {$ranTasks} task(s). Skipped {$skippedTasks} disabled task(s).", 'green');
        }
    }

    /**
     * Determine if a task should run based on current time
     */
    private function shouldRun(array $task, int $hour, int $minute, int $day, string $time): bool
    {
        switch ($task['frequency']) {
            case 'everyMinute':
                return true;

            case 'everyFiveMinutes':
                return $minute % 5 === 0;

            case 'everyTenMinutes':
                return $minute % 10 === 0;

            case 'everyFifteenMinutes':
                return $minute % 15 === 0;

            case 'everyThirtyMinutes':
                return $minute % 30 === 0;

            case 'hourly':
                $targetMinute = (int) ($task['time'] ?? '00');
                $shouldRunThisHour = true;

                // Check if hours restriction exists
                if (isset($task['hours']) && is_array($task['hours'])) {
                    $shouldRunThisHour = in_array($hour, $task['hours']);
                }

                return $minute === $targetMinute && $shouldRunThisHour;

            case 'daily':
                return $time === $task['time'];

            case 'weekly':
                return $day === $task['day'] && $time === $task['time'];

            case 'monthly':
                $currentDayOfMonth = (int) date('d');
                $targetDay = $task['day'] ?? 1;
                return $currentDayOfMonth === $targetDay && $time === $task['time'];

            default:
                return false;
        }
    }

    /**
     * List all scheduled tasks
     */
    private function listTasks(): void
    {
        CLI::write('Scheduled Tasks:', 'yellow');
        CLI::newLine();

        $table = [];
        foreach ($this->tasks as $task) {
            $schedule = $this->getScheduleDescription($task);
            $status = $task['enabled'] ? CLI::color('Enabled', 'green') : CLI::color('Disabled', 'red');

            $table[] = [
                $task['name'],
                $task['command'],
                $schedule,
                $status,
            ];
        }

        CLI::table($table, ['Task Name', 'Command', 'Schedule', 'Status']);
    }

    /**
     * Get human-readable schedule description
     */
    private function getScheduleDescription(array $task): string
    {
        switch ($task['frequency']) {
            case 'everyMinute':
                return 'Every minute';

            case 'everyFiveMinutes':
                return 'Every 5 minutes';

            case 'everyTenMinutes':
                return 'Every 10 minutes';

            case 'everyFifteenMinutes':
                return 'Every 15 minutes';

            case 'everyThirtyMinutes':
                return 'Every 30 minutes';

            case 'hourly':
                $minute = $task['time'] ?? '00';
                $hoursDesc = '';
                if (isset($task['hours'])) {
                    $hoursDesc = ' (between ' . min($task['hours']) . ':00-' . max($task['hours']) . ':00)';
                }
                return "Hourly at minute :{$minute}{$hoursDesc}";

            case 'daily':
                return "Daily at {$task['time']}";

            case 'weekly':
                $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                return "Weekly on {$days[$task['day']]} at {$task['time']}";

            case 'monthly':
                $day = $task['day'] ?? 1;
                return "Monthly on day {$day} at {$task['time']}";

            default:
                return 'Unknown';
        }
    }

    /**
     * Log task execution to database
     */
    private function logTaskRun(array $task, string $status, string $output = ''): void
    {
        try {
            $db = \Config\Database::connect();

            $db->table('scheduled_task_logs')->insert([
                'task_name'  => $task['name'],
                'command'    => $task['command'],
                'status'     => $status,
                'output'     => $output,
                'executed_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Silently fail if table doesn't exist yet
            log_message('debug', 'Could not log task run: ' . $e->getMessage());
        }
    }
}
