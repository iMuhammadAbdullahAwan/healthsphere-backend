<?php

/**
 * General Helper Functions
 * 
 * Common utility functions used across the application.
 * 
 * @package HealthSphere
 */

if (!function_exists('logError')) {
    /**
     * Log error to database
     *
     * @param Throwable $e Exception or error object
     * @return void
     */
    function logError(Throwable $e)
    {
        $errorLogModel = new \App\Models\ErrorLogModel();
        $data = [
            'error_message' => $e->getMessage(),
            'stack_trace' => $e->getTraceAsString(),
            'file_name' => $e->getFile(),
            'method_name' => $e->getTrace()[0]['function'] ?? null,
            'line_number' => $e->getLine(),
            'additional_info' => $e->__toString(),
            'user_id' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        try {
            $errorLogModel->insert($data);
        } catch (\Exception $logException) {
            // If logging fails, write to file
            log_message('error', 'Failed to log error to database: ' . $e->getMessage());
        }
    }
}

if (!function_exists('validateRequest')) {
    /**
     * Validate request data against rules
     *
     * @param mixed $data     Data to validate
     * @param array $rules    Validation rules
     * @param array $messages Custom error messages
     * @return bool|array     True if valid, array of errors if invalid
     */
    function validateRequest($data, $rules, $messages = null)
    {
        $validator = \Config\Services::validation();
        $validator->reset(); // Ensure the validator is reset

        if ($messages) {
            $validator->setRules($rules, $messages);
        } else {
            $validator->setRules($rules);
        }

        // Convert object to array if needed
        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }

        if ($validator->run($data)) {
            return true;
        }

        return $validator->getErrors();
    }
}

if (!function_exists('sanitizeInput')) {
    /**
     * Sanitize user input
     *
     * @param string $input Input string
     * @return string Sanitized string
     */
    function sanitizeInput($input)
    {
        if (is_string($input)) {
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
        return $input;
    }
}

if (!function_exists('generateUniqueToken')) {
    /**
     * Generate a unique secure token
     *
     * @param int $length Token length in bytes
     * @return string Hexadecimal token
     */
    function generateUniqueToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
}

if (!function_exists('hashToken')) {
    /**
     * Hash a token for secure storage
     *
     * @param string $token Plain token
     * @return string Hashed token
     */
    function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}

if (!function_exists('getCurrentUserId')) {
    /**
     * Get current authenticated user ID from request
     *
     * @return int|null User ID or null if not authenticated
     */
    function getCurrentUserId(): ?int
    {
        $request = service('request');
        return $request->getPost('current_user_id') ?? null;
    }
}

if (!function_exists('isProduction')) {
    /**
     * Check if environment is production
     *
     * @return bool
     */
    function isProduction(): bool
    {
        return ENVIRONMENT === 'production';
    }
}

if (!function_exists('isDevelopment')) {
    /**
     * Check if environment is development
     *
     * @return bool
     */
    function isDevelopment(): bool
    {
        return ENVIRONMENT === 'development';
    }
}
