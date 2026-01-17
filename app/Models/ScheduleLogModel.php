<?php

namespace App\Models;

use CodeIgniter\Model;

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
        'status'        => 'permit_empty|in_list[pending,completed,skipped,missed]',
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
    public function getUserHistory(int $userId, array $filters = [])
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

        return $builder->orderBy('schedule_logs.scheduled_for', 'DESC')->findAll();
    }

    /**
     * Mark a log entry as completed
     */
    public function markCompleted(int $logId, int $userId, ?string $notes = null)
    {
        $data = [
            'status'       => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
        ];

        if ($notes) {
            $data['notes'] = $notes;
        }

        return $this->where([
            'id'      => $logId,
            'user_id' => $userId,
        ])->set($data)->update();
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
