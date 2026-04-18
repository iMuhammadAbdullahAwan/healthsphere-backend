<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * UserModel
 * 
 * Manages user data with authentication, profile management,
 * and password reset functionality.
 * 
 * @package HealthSphere
 * @version 1.0.0
 */
class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'email',
        'password',
        'password_hash',
        'full_name',
        'date_of_birth',
        'gender',
        'phone',
        'profile_img',
        'subscription_tier',
        'subscription_expires_at',
        'role',
        'refresh_token',
        'refresh_token_expires_at',
        'password_reset_token',
        'password_reset_expires_at',
        'email_verified_at',
        'last_login',
        'step_tracking_enabled',
        'daily_step_goal',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    // Validation rules
    protected $validationRules = [
        'email' => [
            'label'  => 'Email',
            'rules'  => 'required|valid_email|is_unique[users.email,id,{id}]',
            'errors' => [
                'required'    => 'Email address is required.',
                'valid_email' => 'Please provide a valid email address.',
                'is_unique'   => 'This email address is already registered.'
            ]
        ],
        'full_name' => [
            'label'  => 'Full Name',
            'rules'  => 'permit_empty|min_length[2]|max_length[255]',
            'errors' => [
                'min_length' => 'Full name must be at least 2 characters.',
                'max_length' => 'Full name cannot exceed 255 characters.'
            ]
        ],
        'date_of_birth' => [
            'label'  => 'Date of Birth',
            'rules'  => 'permit_empty|valid_date[Y-m-d]',
            'errors' => [
                'valid_date' => 'Date of birth must be a valid date in YYYY-MM-DD format.'
            ]
        ],
        'gender' => [
            'label'  => 'Gender',
            'rules'  => 'permit_empty|in_list[male,female,other]',
            'errors' => [
                'in_list' => 'Gender must be one of: male, female, or other.'
            ]
        ],
        'phone' => [
            'label'  => 'Phone Number',
            'rules'  => 'permit_empty|min_length[10]|max_length[20]',
            'errors' => [
                'min_length' => 'Phone number must be at least 10 characters.',
                'max_length' => 'Phone number cannot exceed 20 characters.'
            ]
        ]
    ];

    protected $validationMessages = [];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = ['hashPassword'];
    protected $beforeUpdate = ['hashPassword'];

    /**
     * Hash password before saving
     */
    protected function hashPassword(array $data)
    {
        if (isset($data['data']['password'])) {
            $data['data']['password_hash'] = password_hash(
                $data['data']['password'],
                PASSWORD_BCRYPT,
                ['cost' => 12]
            );
            unset($data['data']['password']);
        }

        return $data;
    }

    /**
     * Find a user by email
     *
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->where('email', $email)->first();
    }

    /**
     * Verify user password
     *
     * @param string $email
     * @param string $password
     * @return bool
     */
    public function verifyPassword(string $email, string $password): bool
    {
        $user = $this->where('email', $email)->first();

        if (!$user) {
            return false;
        }

        return password_verify($password, $user['password_hash']);
    }

    /**
     * Update last login timestamp
     *
     * @param int $userId
     * @return bool
     */
    public function updateLastLogin(int $userId): bool
    {
        return $this->update($userId, ['last_login' => date('Y-m-d H:i:s')]);
    }

    /**
     * Update refresh token
     *
     * @param int    $userId
     * @param string $token
     * @param int    $expiresIn Seconds until expiration
     * @return bool
     */
    public function updateRefreshToken(int $userId, string $token, int $expiresIn): bool
    {
        return $this->update($userId, [
            'refresh_token' => $token,
            'refresh_token_expires_at' => date('Y-m-d H:i:s', time() + $expiresIn),
        ]);
    }

    /**
     * Clear refresh token
     *
     * @param int $userId
     * @return bool
     */
    public function clearRefreshToken(int $userId): bool
    {
        return $this->update($userId, [
            'refresh_token' => null,
            'refresh_token_expires_at' => null,
        ]);
    }

    /**
     * Create password reset token
     *
     * @param string $email
     * @return string|null Plain token to send via email
     */
    public function createPasswordResetToken(string $email): ?string
    {
        $user = $this->where('email', $email)->first();

        if (!$user) {
            return null;
        }

        $token = bin2hex(random_bytes(32));

        $this->update($user['id'], [
            'password_reset_token' => hash('sha256', $token),
            'password_reset_expires_at' => date('Y-m-d H:i:s', time() + 3600), // 1 hour
        ]);

        return $token; // Return plain token to send via email
    }

    /**
     * Verify password reset token
     *
     * @param string $token
     * @return array|null User data if valid
     */
    public function verifyPasswordResetToken(string $token): ?array
    {
        $hashedToken = hash('sha256', $token);

        $user = $this->where('password_reset_token', $hashedToken)
            ->where('password_reset_expires_at >', date('Y-m-d H:i:s'))
            ->first();

        return $user;
    }

    /**
     * Reset password
     *
     * @param int    $userId
     * @param string $newPassword
     * @return bool
     */
    public function resetPassword(int $userId, string $newPassword): bool
    {
        return $this->update($userId, [
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
            'password_reset_token' => null,
            'password_reset_expires_at' => null,
        ]);
    }

    /**
     * Get user private details (with all fields)
     *
     * @param int $userId
     * @return array|null
     */
    public function getUserPrivateDetails(int $userId): ?array
    {
        $user = $this->find($userId);

        if (!$user) {
            return null;
        }

        // Remove sensitive fields even for private details
        unset($user['password_hash']);
        unset($user['password_reset_token']);

        return $user;
    }

    /**
     * Get user public details (safe for API responses)
     *
     * @param int $userId
     * @return array|null
     */
    public function getUserPublicDetails(int $userId): ?array
    {
        $user = $this->find($userId);

        if (!$user) {
            return null;
        }

        // Remove sensitive fields
        unset(
            $user['password_hash'],
            $user['refresh_token'],
            $user['refresh_token_expires_at'],
            $user['password_reset_token'],
            $user['password_reset_expires_at'],
            $user['deleted_at']
        );

        return $user;
    }

    /**
     * Update user profile
     *
     * @param int   $userId
     * @param array $data
     * @return bool
     */
    public function updateUser(int $userId, array $data): bool
    {
        // List of fields that can be updated via profile
        $allowedFields = [
            'full_name',
            'date_of_birth',
            'gender',
            'phone',
            'profile_img',
        ];

        // Filter data to only allowed fields
        $updateData = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (empty($updateData)) {
            return false;
        }

        return $this->update($userId, $updateData);
    }
}
