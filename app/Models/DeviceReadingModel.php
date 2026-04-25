<?php

namespace App\Models;

use CodeIgniter\Model;

class DeviceReadingModel extends Model
{
    protected $table            = 'device_readings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'device_name',
        'reading_value',
        'reading_unit',
        'image_path',
        'status',
        'recorded_at'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'user_id'       => 'int',
        'reading_value' => 'float',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    /**
     * Determine status based on device name and value
     * 
     * @param string $deviceName
     * @param float $value
     * @return string
     */
    public function calculateStatus(string $deviceName, float $value): string
    {
        $name = strtolower($deviceName);

        // Blood Glucose (mg/dL)
        if (str_contains($name, 'glucose') || str_contains($name, 'sugar')) {
            if ($value < 70) return 'low';
            if ($value > 140) return 'high';
            return 'normal';
        }

        // Blood Pressure (assuming Systolic for now if single value)
        if (str_contains($name, 'pressure') || str_contains($name, 'bp')) {
            if ($value < 90) return 'low';
            if ($value > 120) return 'high';
            return 'normal';
        }

        // Oxygen Saturation (SpO2)
        if (str_contains($name, 'oxygen') || str_contains($name, 'spo2')) {
            if ($value < 95) return 'low';
            return 'normal';
        }

        return 'normal';
    }
}
