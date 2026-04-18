<?php

namespace App\Models;

use CodeIgniter\Model;

class ExerciseLogModel extends Model
{
    protected $table            = 'exercise_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'exercise_name',
        'count',
        'duration_minutes',
        'calories_burned',
        'performed_at',
        'notes',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'user_id'          => 'required|integer',
        'exercise_name'    => 'required|string|max_length[255]',
        'count'            => 'permit_empty|string|max_length[100]',
        'duration_minutes' => 'permit_empty|integer',
        'calories_burned'  => 'permit_empty|numeric',
        'performed_at'     => 'required',
    ];

    /**
     * Get user logs paginated
     */
    public function getUserLogs(int $userId, int $limit = 20, int $offset = 0)
    {
        return $this->where('user_id', $userId)
                    ->orderBy('performed_at', 'DESC')
                    ->findAll($limit, $offset);
    }
}
