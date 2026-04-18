<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * StepSessionModel
 * 
 * Manages recorded step sessions from the pedometer
 * 
 * @package HealthSphere
 */
class StepSessionModel extends Model
{
    protected $table            = 'step_sessions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'steps',
        'distance_km',
        'duration_seconds',
        'calories',
        'started_at',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'user_id'          => 'required|integer',
        'steps'            => 'required|integer',
        'distance_km'      => 'required|numeric',
        'duration_seconds' => 'required|integer',
        'calories'         => 'permit_empty|numeric',
        'started_at'       => 'required',
    ];

    /**
     * Get user sessions paginated
     */
    public function getUserSessions(int $userId, int $limit = 20, int $offset = 0)
    {
        return $this->where('user_id', $userId)
                    ->orderBy('started_at', 'DESC')
                    ->findAll($limit, $offset);
    }
}
