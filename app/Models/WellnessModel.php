<?php

namespace App\Models;

use CodeIgniter\Model;

class WellnessModel extends Model
{
    protected $table            = 'wellness_scores';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'overall_score',
        'status',
        'breakdown',
        'ai_insight',
        'score_date'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'user_id'       => 'int',
        'overall_score' => 'int',
        'breakdown'     => 'json-array',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Map numerical score to health status
     */
    public function getStatusFromScore(int $score): string
    {
        if ($score >= 80) return 'optimal';
        if ($score >= 60) return 'fair';
        if ($score >= 40) return 'guarded';
        return 'critical';
    }
}
