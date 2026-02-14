<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * FoodLogModel
 * 
 * Manages food log entries with AI analysis data
 * 
 * @package HealthSphere
 */
class FoodLogModel extends Model
{
    protected $table            = 'food_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'food_name',
        'image_path',
        'portion_size',
        'calories',
        'protein',
        'carbohydrates',
        'fat',
        'fiber',
        'sugar',
        'sodium',
        'other_nutrients',
        'confidence_score',
        'raw_analysis',
        'meal_type',
        'consumed_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'user_id' => 'int',
        'calories' => 'float',
        'protein' => 'float',
        'carbohydrates' => 'float',
        'fat' => 'float',
        'fiber' => 'float',
        'sugar' => 'float',
        'sodium' => 'float',
        'confidence_score' => 'int',
        'other_nutrients' => 'json',
        'raw_analysis' => 'json',
    ];

    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules = [
        'user_id' => 'required|integer',
        'food_name' => 'required|string|max_length[255]',
        'meal_type' => 'permit_empty|in_list[breakfast,lunch,dinner,snack]',
    ];

    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    /**
     * Get food logs for a user with filters
     * 
     * @param int $userId User ID
     * @param array $filters Optional filters (meal_type, start_date, end_date)
     * @return array
     */
    public function getUserFoodLogs(int $userId, array $filters = []): array
    {
        $builder = $this->where('user_id', $userId);

        // Apply filters
        if (!empty($filters['meal_type'])) {
            $builder->where('meal_type', $filters['meal_type']);
        }

        if (!empty($filters['start_date'])) {
            $builder->where('consumed_at >=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $builder->where('consumed_at <=', $filters['end_date']);
        }

        return $builder->orderBy('consumed_at', 'DESC')->findAll();
    }

    /**
     * Get nutrition summary for a user
     * 
     * @param int $userId User ID
     * @param string|null $startDate Start date
     * @param string|null $endDate End date
     * @return array
     */
    public function getNutritionSummary(int $userId, ?string $startDate = null, ?string $endDate = null): array
    {
        $builder = $this->select('
            COUNT(*) as total_meals,
            SUM(calories) as total_calories,
            SUM(protein) as total_protein,
            SUM(carbohydrates) as total_carbs,
            SUM(fat) as total_fat,
            SUM(fiber) as total_fiber,
            SUM(sugar) as total_sugar,
            SUM(sodium) as total_sodium,
            AVG(confidence_score) as avg_confidence
        ')
            ->where('user_id', $userId);

        if ($startDate) {
            $builder->where('consumed_at >=', $startDate);
        }

        if ($endDate) {
            $builder->where('consumed_at <=', $endDate);
        }

        return $builder->first() ?? [];
    }

    /**
     * Get daily nutrition breakdown
     * 
     * @param int $userId User ID
     * @param int $days Number of days to fetch (default 7)
     * @return array
     */
    public function getDailyBreakdown(int $userId, int $days = 7): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        return $this->select('
            DATE(consumed_at) as date,
            COUNT(*) as meal_count,
            SUM(calories) as daily_calories,
            SUM(protein) as daily_protein,
            SUM(carbohydrates) as daily_carbs,
            SUM(fat) as daily_fat
        ')
            ->where('user_id', $userId)
            ->where('consumed_at >=', $startDate)
            ->groupBy('DATE(consumed_at)')
            ->orderBy('date', 'DESC')
            ->findAll();
    }
}
