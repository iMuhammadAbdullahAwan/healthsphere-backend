<?php

namespace App\Models;

use CodeIgniter\Model;

class ScheduleHistoryModel extends Model
{
    protected $table            = 'schedule_history';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'original_log_id',
        'schedule_id',
        'user_id',
        'scheduled_for',
        'status',
        'notes',
        'notified_at',
        'completed_at',
        'schedule_snapshot',
        'archived_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Decode JSON snapshot field for a single history row.
     */
    private function decodeSnapshotRow(array $row): array
    {
        if (isset($row['schedule_snapshot']) && is_string($row['schedule_snapshot']) && $row['schedule_snapshot'] !== '') {
            $decoded = json_decode($row['schedule_snapshot'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $row['schedule_snapshot'] = $decoded;
            }
        }

        return $row;
    }

    /**
     * Get user's history entries with optional filters and pagination.
     */
    public function getUserHistory(int $userId, array $filters = [], int $page = 1, int $perPage = 20)
    {
        $builder = $this->select('schedule_history.*, schedules.title, schedules.schedule_type')
            ->join('schedules', 'schedules.id = schedule_history.schedule_id')
            ->where('schedule_history.user_id', $userId);

        if (!empty($filters['schedule_type'])) {
            $builder->where('schedules.schedule_type', $filters['schedule_type']);
        }

        if (!empty($filters['status'])) {
            $builder->where('schedule_history.status', $filters['status']);
        }

        if (!empty($filters['start_date'])) {
            $builder->where('schedule_history.scheduled_for >=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $builder->where('schedule_history.scheduled_for <=', $filters['end_date']);
        }

        $countBuilder = clone $builder;
        $total = (int) $countBuilder->countAllResults();

        $offset = max(0, ($page - 1) * $perPage);
        $items = $builder->orderBy('schedule_history.scheduled_for', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        foreach ($items as $i => $item) {
            $items[$i] = $this->decodeSnapshotRow($item);
        }

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
}
