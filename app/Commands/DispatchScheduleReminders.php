<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\ScheduleLogModel;
// helper functions are loaded via helper('queue') when needed

/**
 * Dispatch schedule reminders: enqueue notifications for pending schedule_logs
 * Usage: php spark schedules:dispatch-reminders
 */
class DispatchScheduleReminders extends BaseCommand
{
    protected $group = 'HealthSphere';
    protected $name = 'schedules:dispatch-reminders';
    protected $description = 'Dispatch reminders for pending schedule logs (enqueue notifications)';
    protected $usage = 'schedules:dispatch-reminders';

    public function run(array $params)
    {
        CLI::write('Dispatching schedule reminders...', 'yellow');

        $logModel = new ScheduleLogModel();
        $db = \Config\Database::connect();

        $now = date('Y-m-d H:i:s');

        // load queue helper which provides the global queue_notification() function
        helper('queue');

        // Find pending logs that haven't been notified yet
        $builder = $db->table('schedule_logs')
            ->where('status', 'pending')
            ->where('scheduled_for <=', $now)
            ->where('notified_at IS NULL');

        $rows = $builder->get()->getResultArray();
        $dispatched = 0;

        foreach ($rows as $r) {
            // Build notification payload
            $schedule = $db->table('schedules')->where('id', $r['schedule_id'])->get()->getRowArray();
            if (!$schedule) continue;

            $message = match ($schedule['schedule_type']) {
                'medicine' => "Time to take your medicine: {$schedule['title']}",
                'food' => "Meal reminder: {$schedule['title']}",
                'water' => "Time to drink water: {$schedule['title']}",
                'running' => "Time for your activity: {$schedule['title']}",
                'sleep' => "Sleep reminder: {$schedule['title']}",
                'custom' => "Reminder: {$schedule['title']}",
                default => "Schedule reminder: {$schedule['title']}"
            };

            // enqueue notification (high priority)
            \queue_notification([$r['user_id']], $message, 'schedule_reminder', (int)$r['user_id'], [
                'link' => '/schedules/' . $schedule['id'],
                'related_id' => $schedule['id']
            ]);

            // mark notified
            $logModel->update($r['id'], ['notified_at' => date('Y-m-d H:i:s')]);
            $dispatched++;
        }

        CLI::write("Dispatched {$dispatched} reminder(s).", 'green');

        // Archive stale pending logs as 'missed' (run as part of scheduler)
        // Consider logs older than 1 hour as missed
        $threshold = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $staleBuilder = $db->table('schedule_logs')
            ->where('status', 'pending')
            ->where('scheduled_for <=', $threshold);

        $staleRows = $staleBuilder->get()->getResultArray();
        $historyModel = new \App\Models\ScheduleHistoryModel();
        $scheduleModel = new \App\Models\ScheduleModel();
        $archived = 0;

        foreach ($staleRows as $sr) {
            $schedule = $scheduleModel->find($sr['schedule_id']);
            $historyModel->insert([
                'original_log_id' => $sr['id'],
                'schedule_id' => $sr['schedule_id'],
                'user_id' => $sr['user_id'],
                'scheduled_for' => $sr['scheduled_for'],
                'status' => 'missed',
                'notes' => $sr['notes'] ?? null,
                'notified_at' => $sr['notified_at'] ?? null,
                'completed_at' => $sr['completed_at'] ?? null,
                'schedule_snapshot' => $schedule ? json_encode($schedule) : null,
                'archived_at' => date('Y-m-d H:i:s'),
            ]);

            $db->table('schedule_logs')->delete(['id' => $sr['id']]);
            $archived++;
        }

        if ($archived > 0) {
            CLI::write("Archived {$archived} stale pending log(s) as missed.", 'green');
        }
    }
}
