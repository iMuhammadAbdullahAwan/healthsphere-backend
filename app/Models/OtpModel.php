<?php

namespace App\Models;

use CodeIgniter\Model;

class OtpModel extends Model
{
    protected $table = 'otps';
    protected $primaryKey = 'id';
    protected $allowedFields = ['user_id', 'target', 'code', 'expires_at', 'used', 'created_at'];
    public $useTimestamps = false;

    public function createOtp(string $target, string $code, ?int $userId = null, int $ttlSeconds = 300): int
    {
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

        $data = [
            'user_id' => $userId,
            'target' => $target,
            'code' => $code,
            'expires_at' => $expiresAt,
            'used' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->insert($data);
        return (int) $this->getInsertID();
    }

    public function verifyOtp(string $target, string $code): ?array
    {
        $now = date('Y-m-d H:i:s');

        $otp = $this->where('target', $target)
            ->where('code', $code)
            ->where('used', 0)
            ->where('expires_at >=', $now)
            ->orderBy('id', 'DESC')
            ->first();

        return $otp ?: null;
    }

    public function markUsed(int $id): bool
    {
        return (bool) $this->update($id, ['used' => 1]);
    }
}
