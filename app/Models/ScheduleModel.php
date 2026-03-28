<?php

namespace App\Models;

use CodeIgniter\Model;

class ScheduleModel extends Model
{
    protected $table            = 'schedules';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'schedule_type',
        'title',
        'description',
        'start_date',
        'start_time',
        'repeat_type',
        'repeat_days',
        'end_condition',
        'end_date',
        'max_occurrences',
        'reminder_enabled',
        'reminder_mode',
        'voice_command_text',
        'status',
        'medicine_details',
        'food_details',
        'water_details',
        'running_details',
        'sleep_details',
        'custom_details',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'user_id'        => 'required|integer',
        'schedule_type'  => 'required|in_list[medicine,food,water,running,sleep,custom]',
        'title'          => 'required|string|max_length[255]',
        'description'    => 'permit_empty|string',
        'start_date'     => 'required|valid_date[Y-m-d]',
        'start_time'     => 'required|regex_match[/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/]',
        'repeat_type'    => 'required|in_list[once,daily,weekly,custom_days]',
        'end_condition'  => 'required|in_list[never,on_date,after_occurrences]',
        'reminder_enabled' => 'permit_empty|in_list[0,1]',
        'reminder_mode'  => 'permit_empty|in_list[notification,voice,both]',
        'status'         => 'permit_empty|in_list[active,paused,completed]',
    ];

    protected $validationMessages = [
        'user_id' => [
            'required' => 'User ID is required',
            'integer'  => 'User ID must be an integer',
        ],
        'schedule_type' => [
            'required' => 'Schedule type is required',
            'in_list'  => 'Invalid schedule type',
        ],
        'title' => [
            'required'   => 'Title is required',
            'max_length' => 'Title cannot exceed 255 characters',
        ],
        'start_date' => [
            'required'   => 'Start date is required',
            'valid_date' => 'Start date must be in YYYY-MM-DD format',
        ],
        'start_time' => [
            'required'     => 'Start time is required',
            'regex_match'  => 'Start time must be in HH:mm format',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['jsonEncodeFields'];
    protected $beforeUpdate   = ['jsonEncodeFields'];
    protected $afterFind      = ['jsonDecodeFields'];

    /**
     * JSON encode specific fields before insert/update
     */
    protected function jsonEncodeFields(array $data)
    {
        $jsonFields = [
            'repeat_days',
            'medicine_details',
            'food_details',
            'water_details',
            'running_details',
            'sleep_details',
            'custom_details',
        ];

        foreach ($jsonFields as $field) {
            if (isset($data['data'][$field]) && is_array($data['data'][$field])) {
                $data['data'][$field] = json_encode($data['data'][$field]);
            }
        }

        return $data;
    }

    /**
     * JSON decode specific fields after retrieval
     */
    protected function jsonDecodeFields(array $data)
    {
        $jsonFields = [
            'repeat_days',
            'medicine_details',
            'food_details',
            'water_details',
            'running_details',
            'sleep_details',
            'custom_details',
        ];

        if (isset($data['data'])) {
            // Single record
            foreach ($jsonFields as $field) {
                if (isset($data['data'][$field]) && is_string($data['data'][$field])) {
                    $data['data'][$field] = json_decode($data['data'][$field], true);
                }
            }
        } elseif (isset($data['id'])) {
            // When using find() directly
            foreach ($jsonFields as $field) {
                if (isset($data[$field]) && is_string($data[$field])) {
                    $data[$field] = json_decode($data[$field], true);
                }
            }
        }

        return $data;
    }

    /**
     * Decode JSON fields for a single row array (from query builder)
     */
    protected function decodeJsonFieldsRow(array $row): array
    {
        $jsonFields = [
            'repeat_days',
            'medicine_details',
            'food_details',
            'water_details',
            'running_details',
            'sleep_details',
            'custom_details',
        ];

        foreach ($jsonFields as $field) {
            if (isset($row[$field]) && is_string($row[$field]) && $row[$field] !== '') {
                $decoded = json_decode($row[$field], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row[$field] = $decoded;
                }
            }
        }

        return $row;
    }

    /**
     * Decode JSON fields for an array of rows
     */
    protected function decodeJsonFieldsArray(array $rows): array
    {
        foreach ($rows as $i => $row) {
            $rows[$i] = $this->decodeJsonFieldsRow($row);
        }

        return $rows;
    }

    /**
     * Get all schedules for a specific user with optional filters and pagination
     */
    public function getUserSchedules(int $userId, array $filters = [], int $page = 1, int $perPage = 20)
    {
        $builder = $this->builder();
        $builder->where('user_id', $userId);

        // Filter by schedule type
        if (!empty($filters['schedule_type'])) {
            $builder->where('schedule_type', $filters['schedule_type']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $builder->where('status', $filters['status']);
        } else {
            // Default: exclude completed schedules
            $builder->whereIn('status', ['active', 'paused']);
        }

        // Filter by date range
        if (!empty($filters['start_date'])) {
            $builder->where('start_date >=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $builder->where('start_date <=', $filters['end_date']);
        }

        // Count total
        $countBuilder = clone $builder;
        $total = (int) $countBuilder->countAllResults();

        // Apply ordering and pagination
        $offset = max(0, ($page - 1) * $perPage);
        $items = $builder->orderBy('start_date', 'ASC')
            ->orderBy('start_time', 'ASC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        $items = $this->decodeJsonFieldsArray($items);

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
     * Get a specific schedule for a user
     */
    public function getUserSchedule(int $scheduleId, int $userId)
    {
        return $this->where([
            'id'      => $scheduleId,
            'user_id' => $userId,
        ])->first();
    }

    /**
     * Get today's schedules for a user
     */
    public function getTodaySchedules(int $userId, int $page = 1, int $perPage = 20)
    {
        $today = date('Y-m-d');
        $builder = $this->builder()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->groupStart()
            ->where('start_date <=', $today)
            ->groupStart()
            ->where('end_condition', 'never')
            ->orWhere('end_date >=', $today)
            ->orWhere('end_date IS NULL')
            ->groupEnd()
            ->groupEnd();

        $countBuilder = clone $builder;
        $total = (int) $countBuilder->countAllResults();

        $offset = max(0, ($page - 1) * $perPage);
        $items = $builder->orderBy('start_time', 'ASC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        $items = $this->decodeJsonFieldsArray($items);

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
     * Get upcoming schedules (next 7 days)
     */
    public function getUpcomingSchedules(int $userId, int $days = 7, int $page = 1, int $perPage = 20)
    {
        $today = date('Y-m-d');
        $futureDate = date('Y-m-d', strtotime("+{$days} days"));
        $builder = $this->builder()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('start_date >=', $today)
            ->where('start_date <=', $futureDate);

        $countBuilder = clone $builder;
        $total = (int) $countBuilder->countAllResults();

        $offset = max(0, ($page - 1) * $perPage);
        $items = $builder->orderBy('start_date', 'ASC')
            ->orderBy('start_time', 'ASC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        $items = $this->decodeJsonFieldsArray($items);

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
     * Update schedule status
     */
    public function updateStatus(int $scheduleId, int $userId, string $status)
    {
        if (!in_array($status, ['active', 'paused', 'completed'])) {
            return false;
        }

        return $this->where([
            'id'      => $scheduleId,
            'user_id' => $userId,
        ])->set('status', $status)->update();
    }

    /**
     * Delete user schedule
     */
    public function deleteUserSchedule(int $scheduleId, int $userId)
    {
        return $this->where([
            'id'      => $scheduleId,
            'user_id' => $userId,
        ])->delete();
    }

    /**
     * Count active schedules by type for a user
     */
    public function countByType(int $userId)
    {
        return $this->select('schedule_type, COUNT(*) as count')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->groupBy('schedule_type')
            ->findAll();
    }
}
