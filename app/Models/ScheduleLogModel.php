<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Models\ScheduleHistoryModel;
use App\Models\ScheduleModel;

class ScheduleLogModel extends Model
{
    protected $table            = 'schedule_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'schedule_id',
        'user_id',
        'scheduled_for',
        'completed_at',
        'status',
        'notes',
        'notified_at',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'schedule_id'   => 'required|integer',
        'user_id'       => 'required|integer',
        'scheduled_for' => 'required|valid_date',
        'status'        => 'permit_empty|in_list[pending,completed,skipped,missed,canceled]',
    ];

    protected $validationMessages = [
        'schedule_id' => [
            'required' => 'Schedule ID is required',
            'integer'  => 'Schedule ID must be an integer',
        ],
        'user_id' => [
            'required' => 'User ID is required',
            'integer'  => 'User ID must be an integer',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Get logs for a specific schedule
     */
    public function getScheduleLogs(int $scheduleId, int $userId, array $filters = [])
    {
        $builder = $this->where([
            'schedule_id' => $scheduleId,
            'user_id'     => $userId,
        ]);

        if (!empty($filters['status'])) {
            $builder->where('status', $filters['status']);
        }

        if (!empty($filters['start_date'])) {
            $builder->where('scheduled_for >=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $builder->where('scheduled_for <=', $filters['end_date']);
        }

        return $builder->orderBy('scheduled_for', 'DESC')->findAll();
    }

    /**
     * Get user's completion history
     */
    public function getUserHistory(int $userId, array $filters = [], int $page = 1, int $perPage = 20)
    {
        $builder = $this->select('schedule_logs.*, schedules.title, schedules.schedule_type')
            ->join('schedules', 'schedules.id = schedule_logs.schedule_id')
            ->where('schedule_logs.user_id', $userId);

        if (!empty($filters['schedule_type'])) {
            $builder->where('schedules.schedule_type', $filters['schedule_type']);
        }

        if (!empty($filters['status'])) {
            $builder->where('schedule_logs.status', $filters['status']);
        }

        if (!empty($filters['start_date'])) {
            $builder->where('schedule_logs.scheduled_for >=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $builder->where('schedule_logs.scheduled_for <=', $filters['end_date']);
        }

        $countBuilder = clone $builder;
        $total = (int) $countBuilder->countAllResults();

        $offset = max(0, ($page - 1) * $perPage);
        $items = $builder->orderBy('schedule_logs.scheduled_for', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
            ],
        ];
    }

    /**
     * Mark a log entry as completed
     */
    public function markCompleted(int $logId, int $userId, ?string $notes = null)
    {
        // Find the existing log
        $log = $this->where(['id' => $logId, 'user_id' => $userId])->first();
        if (!$log) {
            return false;
        }

        $data = [
            'status'       => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
        ];

        if ($notes) {
            $data['notes'] = $notes;
        }

        $updated = $this->where(['id' => $logId, 'user_id' => $userId])->set($data)->update();
        if (!$updated) {
            return false;
        }

        // Archive to history and remove original log
        $historyModel = new ScheduleHistoryModel();
        $scheduleModel = new ScheduleModel();
        $schedule = $scheduleModel->find($log['schedule_id']);

        $historyData = [
            'original_log_id' => $logId,
            'schedule_id' => $log['schedule_id'],
            'user_id' => $log['user_id'],
            'scheduled_for' => $log['scheduled_for'],
            'status' => 'completed',
            'notes' => $data['notes'] ?? $log['notes'] ?? null,
            'notified_at' => $log['notified_at'] ?? null,
            'completed_at' => $data['completed_at'],
            'schedule_snapshot' => $schedule ? json_encode($schedule) : null,
            'archived_at' => date('Y-m-d H:i:s'),
        ];

        $historyModel->insert($historyData);
        $historyId = $historyModel->insertID();

        // remove original log (we no longer keep history in schedule_logs)
        $this->delete($logId);

        return $historyModel->find($historyId);
    }

    /**
     * Mark a log entry as canceled (user skipped/cancelled for that occurrence)
     */
    public function markCanceled(int $logId, int $userId, ?string $notes = null)
    {
        $log = $this->where(['id' => $logId, 'user_id' => $userId])->first();
        if (!$log) {
            return false;
        }

        $data = [
            'status' => 'canceled',
        ];

        if ($notes) {
            $data['notes'] = $notes;
        }

        $updated = $this->where(['id' => $logId, 'user_id' => $userId])->set($data)->update();
        if (!$updated) {
            return false;
        }

        $historyModel = new ScheduleHistoryModel();
        $scheduleModel = new ScheduleModel();
        $schedule = $scheduleModel->find($log['schedule_id']);

        $historyData = [
            'original_log_id' => $logId,
            'schedule_id' => $log['schedule_id'],
            'user_id' => $log['user_id'],
            'scheduled_for' => $log['scheduled_for'],
            'status' => 'canceled',
            'notes' => $data['notes'] ?? $log['notes'] ?? null,
            'notified_at' => $log['notified_at'] ?? null,
            'completed_at' => $log['completed_at'] ?? null,
            'schedule_snapshot' => $schedule ? json_encode($schedule) : null,
            'archived_at' => date('Y-m-d H:i:s'),
        ];

        $historyModel->insert($historyData);
        $historyId = $historyModel->insertID();

        $this->delete($logId);

        return $historyModel->find($historyId);
    }

    /**
     * Un-cancel a canceled log (set back to pending)
     */
    public function unCancel(int $logId, int $userId)
    {
        // If original log still exists, just set it back to pending
        $existing = $this->where(['id' => $logId, 'user_id' => $userId])->first();
        if ($existing) {
            return $this->where(['id' => $logId, 'user_id' => $userId])->set([
                'status' => 'pending',
                'notified_at' => null,
                'completed_at' => null,
            ])->update();
        }

        // Otherwise, try to restore from history
        $historyModel = new ScheduleHistoryModel();
        $hist = $historyModel->where(['original_log_id' => $logId, 'user_id' => $userId])->first();
        if (!$hist) {
            return false;
        }

        // restore back to schedule_logs
        $insert = [
            'schedule_id' => $hist['schedule_id'],
            'user_id' => $hist['user_id'],
            'scheduled_for' => $hist['scheduled_for'],
            'status' => 'pending',
            'notes' => null,
            'notified_at' => null,
        ];

        $this->insert($insert);
        $newId = $this->insertID();
        // remove history
        $historyModel->delete($hist['id']);

        return $this->find($newId);
    }

    /**
     * Undo a completed log (set back to pending)
     */
    public function undoCompleted(int $logId, int $userId)
    {
        // If original log exists, revert it
        $existing = $this->where(['id' => $logId, 'user_id' => $userId])->first();
        if ($existing) {
            return $this->where(['id' => $logId, 'user_id' => $userId])->set([
                'status' => 'pending',
                'completed_at' => null,
                'notes' => null,
            ])->update();
        }

        // Otherwise, restore from history
        $historyModel = new ScheduleHistoryModel();
        $hist = $historyModel->where(['original_log_id' => $logId, 'user_id' => $userId])->first();
        if (!$hist) {
            return false;
        }

        $insert = [
            'schedule_id' => $hist['schedule_id'],
            'user_id' => $hist['user_id'],
            'scheduled_for' => $hist['scheduled_for'],
            'status' => 'pending',
            'notes' => null,
            'notified_at' => null,
        ];

        $this->insert($insert);
        $newId = $this->insertID();
        $historyModel->delete($hist['id']);

        return $this->find($newId);
    }

    /**
     * Get completion statistics for a user
     */
    public function getCompletionStats(int $userId, ?string $scheduleType = null, ?string $startDate = null, ?string $endDate = null)
    {
        $builder = $this->select('status, COUNT(*) as count')
            ->where('user_id', $userId);

        if ($scheduleType) {
            $builder->join('schedules', 'schedules.id = schedule_logs.schedule_id')
                ->where('schedules.schedule_type', $scheduleType);
        }

        if ($startDate) {
            $builder->where('scheduled_for >=', $startDate);
        }

        if ($endDate) {
            $builder->where('scheduled_for <=', $endDate);
        }

        return $builder->groupBy('status')->findAll();
    }

    /**
     * Get today's pending logs for a user
     */
    public function getTodayPending(int $userId)
    {
        $today = date('Y-m-d');

        return $this->select('schedule_logs.*, schedules.title, schedules.schedule_type, schedules.reminder_mode')
            ->join('schedules', 'schedules.id = schedule_logs.schedule_id')
            ->where('schedule_logs.user_id', $userId)
            ->where('schedule_logs.status', 'pending')
            ->where('DATE(schedule_logs.scheduled_for)', $today)
            ->orderBy('schedule_logs.scheduled_for', 'ASC')
            ->findAll();
    }
}
