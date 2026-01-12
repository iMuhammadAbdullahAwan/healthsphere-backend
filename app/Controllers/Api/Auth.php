<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\OtpModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Auth Controller
 * 
 * Handles user authentication including registration, login,
 * token refresh, logout, and password reset.
 * 
 * @package HealthSphere
 * @version 1.0.0
 */
class Auth extends BaseController
{
    use ResponseTrait;

    /**
     * User model instance
     *
     * @var UserModel
     */
    private UserModel $userModel;

    /**
     * OTP model instance
     *
     * @var OtpModel
     */
    private OtpModel $otpModel;

    /**
     * Cookie configuration settings
     *
     * @var object
     */
    private object $cookieSettings;

    /**
     * Constructor - Initialize dependencies
     */
    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->otpModel = new OtpModel();
        $this->initializeCookieSettings();
    }

    /**
     * Initialize cookie settings from environment
     *
     * @return void
     */
    private function initializeCookieSettings(): void
    {
        $this->cookieSettings = (object) [
            'domain'   => env('COOKIE_DOMAIN', ''),
            'path'     => env('COOKIE_PATH', '/'),
            'prefix'   => env('COOKIE_PREFIX', ''),
            'secure'   => filter_var(env('COOKIE_SECURE', false), FILTER_VALIDATE_BOOLEAN),
            'httponly' => filter_var(env('COOKIE_HTTPONLY', true), FILTER_VALIDATE_BOOLEAN),
            'samesite' => env('COOKIE_SAMESITE', 'Lax'),
        ];
    }

    /**
     * Register new user
     * POST /api/auth/register
     *
     * @return ResponseInterface
     */
    public function register(): ResponseInterface
    {
        try {
            $request = $this->request->getJSON();

            // Validate input
            $rules = [
                'email' => [
                    'label'  => 'Email',
                    'rules'  => 'required|valid_email|is_unique[users.email]',
                    'errors' => [
                        'required'    => 'Email address is required.',
                        'valid_email' => 'Please provide a valid email address.',
                        'is_unique'   => 'This email address is already registered. Please login or use a different email.'
                    ]
                ],
                'password' => [
                    'label'  => 'Password',
                    'rules'  => 'required|min_length[8]|max_length[255]|regex_match[/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]/]',
                    'errors' => [
                        'required'     => 'Password is required.',
                        'min_length'   => 'Password must be at least 8 characters long.',
                        'max_length'   => 'Password cannot exceed 255 characters.',
                        'regex_match'  => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character (@$!%*?&#).'
                    ]
                ],
                'full_name' => [
                    'label'  => 'Full Name',
                    'rules'  => 'required|min_length[2]|max_length[255]|alpha_space',
                    'errors' => [
                        'required'   => 'Full name is required.',
                        'min_length' => 'Full name must be at least 2 characters.',
                        'max_length' => 'Full name cannot exceed 255 characters.',
                        'alpha_space' => 'Full name can only contain letters and spaces.'
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
                    'rules'  => 'permit_empty|min_length[10]|max_length[20]|regex_match[/^\+?[0-9\s\-\(\)]+$/]',
                    'errors' => [
                        'min_length'  => 'Phone number must be at least 10 characters.',
                        'max_length'  => 'Phone number cannot exceed 20 characters.',
                        'regex_match' => 'Phone number can only contain numbers, spaces, dashes, parentheses, and plus sign.'
                    ]
                ]
            ];

            $validation = validateRequest($request, $rules);
            if ($validation !== true) {
                return sendApiResponse(['errors' => $validation], 'Validation failed. Please check your input.', 422);
            }

            $data = [
                'email' => $request->email,
                'password' => $request->password,
                'full_name' => $request->full_name,
                'date_of_birth' => $request->date_of_birth ?? null,
                'gender' => $request->gender ?? null,
                'phone' => $request->phone ?? null,
                'role' => 'user', // Default role
            ];

            $userId = $this->userModel->insert($data);

            if (!$userId) {
                // Get detailed error from model
                $errors = $this->userModel->errors();
                log_message('error', 'User registration failed: ' . json_encode($errors));
                log_message('error', 'Registration data: ' . json_encode($data));

                $errorMessage = !empty($errors) ? implode(', ', $errors) : 'Failed to create user';
                return sendApiResponse(null, $errorMessage, 500);
            }

            $user = $this->userModel->getUserPublicDetails($userId);

            // Generate JWT tokens
            $accessToken = $this->generateAccessToken($user);
            $refreshToken = $this->generateRefreshToken($user['id']);

            // Store refresh token in database
            $this->userModel->updateRefreshToken(
                $user['id'],
                $refreshToken,
                getenv('JWT_REFRESH_EXPIRY') ?: 172800 // 2 days
            );

            $responseData = [
                'user' => $user,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => getenv('JWT_ACCESS_EXPIRY') ?: 3600,
            ];

            return sendApiResponse($responseData, 'User registered successfully', 201)
                ->setCookie(
                    '_healthsphere_access_token',
                    $accessToken,
                    getenv('JWT_ACCESS_EXPIRY') ?: 3600,
                    $this->cookieSettings->domain,
                    $this->cookieSettings->path,
                    $this->cookieSettings->prefix,
                    $this->cookieSettings->secure,
                    $this->cookieSettings->httponly,
                    $this->cookieSettings->samesite
                )
                ->setCookie(
                    '_healthsphere_refresh_token',
                    $refreshToken,
                    getenv('JWT_REFRESH_EXPIRY') ?: 172800,
                    $this->cookieSettings->domain,
                    $this->cookieSettings->path,
                    $this->cookieSettings->prefix,
                    $this->cookieSettings->secure,
                    $this->cookieSettings->httponly,
                    $this->cookieSettings->samesite
                );
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Registration failed. Please try again.');
        }
    }

    /**
     * Login user
     * POST /api/auth/login
     *
     * @return ResponseInterface
     */
    public function login(): ResponseInterface
    {
        try {
            $request = $this->request->getJSON();

            // Validate input
            $rules = [
                'email' => [
                    'label'  => 'Email',
                    'rules'  => 'required|valid_email',
                    'errors' => [
                        'required'    => 'Email address is required.',
                        'valid_email' => 'Please provide a valid email address.'
                    ]
                ],
                'password' => [
                    'label'  => 'Password',
                    'rules'  => 'required',
                    'errors' => [
                        'required' => 'Password is required.'
                    ]
                ]
            ];

            $validation = validateRequest($request, $rules);
            if ($validation !== true) {
                return sendApiResponse(['errors' => $validation], 'Validation failed. Please check your credentials.', 422);
            }

            $email = $request->email;
            $password = $request->password;

            // Check if user exists first
            $user = $this->userModel->findByEmail($email);

            if (!$user) {
                return sendApiResponse(null, 'Email is not registered.', 401);
            }

            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                return sendApiResponse(
                    null,
                    'Invalid password. You can reset your password by clicking on the forget password button.',
                    403
                );
            }

            // Update last login
            $this->userModel->updateLastLogin($user['id']);

            // Get public user details
            $publicUser = $this->userModel->getUserPublicDetails($user['id']);

            // Generate JWT tokens
            $accessToken = $this->generateAccessToken($publicUser);
            $refreshToken = $this->generateRefreshToken($user['id']);

            // Store refresh token
            $this->userModel->updateRefreshToken(
                $user['id'],
                $refreshToken,
                getenv('JWT_REFRESH_EXPIRY') ?: 172800
            );

            $responseData = [
                'user' => $publicUser,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => getenv('JWT_ACCESS_EXPIRY') ?: 3600,
            ];

            return sendApiResponse($responseData, 'Login successful', 200)
                ->setCookie(
                    '_healthsphere_access_token',
                    $accessToken,
                    getenv('JWT_ACCESS_EXPIRY') ?: 3600,
                    $this->cookieSettings->domain,
                    $this->cookieSettings->path,
                    $this->cookieSettings->prefix,
                    $this->cookieSettings->secure,
                    $this->cookieSettings->httponly,
                    $this->cookieSettings->samesite
                )
                ->setCookie(
                    '_healthsphere_refresh_token',
                    $refreshToken,
                    getenv('JWT_REFRESH_EXPIRY') ?: 172800,
                    $this->cookieSettings->domain,
                    $this->cookieSettings->path,
                    $this->cookieSettings->prefix,
                    $this->cookieSettings->secure,
                    $this->cookieSettings->httponly,
                    $this->cookieSettings->samesite
                );
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Login failed. Please try again.');
        }
    }

    /**
     * Refresh access token
     * POST /api/auth/refresh
     *
     * @return ResponseInterface
     */
    public function refresh(): ResponseInterface
    {
        try {
            $refreshToken = $this->request->getCookie('_healthsphere_refresh_token')
                ?? $this->request->getJSON()->refresh_token ?? null;

            if (!$refreshToken) {
                return sendApiResponse(null, 'Refresh token required', 400);
            }

            // Validate refresh token
            $secretKey = getenv('JWT_SECRET');

            try {
                $decoded = JWT::decode($refreshToken, new \Firebase\JWT\Key($secretKey, 'HS256'));
            } catch (\Exception $e) {
                return sendApiResponse(null, 'Invalid refresh token', 401);
            }

            // Verify token in database
            $user = $this->userModel->where('id', $decoded->uid)
                ->where('refresh_token', $refreshToken)
                ->where('refresh_token_expires_at >', date('Y-m-d H:i:s'))
                ->first();

            if (!$user) {
                return sendApiResponse(null, 'Invalid or expired refresh token', 401);
            }

            // Get public user details for token
            $publicUser = $this->userModel->getUserPublicDetails($user['id']);

            // Generate new access token
            $accessToken = $this->generateAccessToken($publicUser);

            $responseData = [
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => getenv('JWT_ACCESS_EXPIRY') ?: 3600,
            ];

            return sendApiResponse($responseData, 'Token refreshed successfully', 200)
                ->setCookie(
                    '_healthsphere_access_token',
                    $accessToken,
                    getenv('JWT_ACCESS_EXPIRY') ?: 3600,
                    $this->cookieSettings->domain,
                    $this->cookieSettings->path,
                    $this->cookieSettings->prefix,
                    $this->cookieSettings->secure,
                    $this->cookieSettings->httponly,
                    $this->cookieSettings->samesite
                );
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Token refresh failed. Please try again.');
        }
    }

    /**
     * Logout user
     * POST /api/auth/logout
     *
     * @return ResponseInterface
     */
    public function logout(): ResponseInterface
    {
        try {
            $userId = $this->current_user_id;

            if (!$userId) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            // Clear refresh token in database
            $this->userModel->clearRefreshToken($userId);

            // Clear cookies
            return sendApiResponse(null, 'Logged out successfully', 200)
                ->deleteCookie('_healthsphere_access_token')
                ->deleteCookie('_healthsphere_refresh_token');
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Logout failed. Please try again.');
        }
    }

    /**
     * Forgot password - send reset link
     * POST /api/auth/forgot-password
     *
     * @return ResponseInterface
     */
    public function forgotPassword(): ResponseInterface
    {
        try {
            $request = $this->request->getJSON();

            // Validate input
            $rules = [
                'email' => [
                    'label'  => 'Email',
                    'rules'  => 'required|valid_email',
                    'errors' => [
                        'required'    => 'Email address is required.',
                        'valid_email' => 'Please provide a valid email address.'
                    ]
                ]
            ];

            $validation = validateRequest($request, $rules);
            if ($validation !== true) {
                return sendApiResponse(['errors' => $validation], 'Validation failed. Please provide a valid email address.', 422);
            }

            $email = $request->email;

            $token = $this->userModel->createPasswordResetToken($email);

            if (!$token) {
                // Don't reveal if email exists for security
                return sendApiResponse(null, 'If email exists, reset link will be sent', 200);
            }

            // Send email with reset link
            $resetLink = base_url("reset-password?token={$token}");

            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $sent = send_email($email, 'HealthSphere Password Reset', 'emails/reset_password', [
                    'resetLink' => $resetLink,
                ]);

                if (!$sent) {
                    log_message('error', "Failed to send reset email to {$email}");
                }
            } else {
                log_message('info', "Password reset token created for non-email target: {$email}");
            }

            return sendApiResponse(null, 'If email exists, reset link will be sent', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Password reset request failed. Please try again.');
        }
    }

    /**
     * Send OTP to email or phone
     * POST /api/auth/send-otp
     */
    public function sendOtp(): ResponseInterface
    {
        try {
            $request = $this->request->getJSON();

            $rules = [
                'target' => [
                    'label' => 'Target',
                    'rules' => 'required',
                    'errors' => ['required' => 'Target (email or phone) is required.']
                ],
                'type' => [
                    'label' => 'Type',
                    'rules' => 'required|in_list[login,verification,reset]',
                    'errors' => ['required' => 'OTP type is required.']
                ]
            ];

            $validation = validateRequest($request, $rules);
            if ($validation !== true) {
                return sendApiResponse(['errors' => $validation], 'Validation failed.', 422);
            }

            $target = $request->target;
            $type = $request->type;

            // If type is reset, ensure user exists
            if ($type === 'reset') {
                $user = $this->userModel->findByEmail($target);
                if (!$user) {
                    return sendApiResponse(null, 'If the account exists, an OTP will be sent.', 200);
                }
                $userId = $user['id'];
            } else {
                $userId = null;
            }

            // Generate OTP code
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Store OTP
            $this->otpModel->createOtp($target, $code, $userId, intval(getenv('OTP_TTL') ?: 300));

            // Send email if target is an email address
            if (filter_var($target, FILTER_VALIDATE_EMAIL)) {
                // Render and send email
                $sent = send_email($target, 'Your HealthSphere OTP', 'emails/otp', [
                    'code' => $code,
                    'ttl' => intval(getenv('OTP_TTL') ?: 300),
                ]);

                if (!$sent) {
                    log_message('error', "Failed to send OTP email to {$target}");
                }
            } else {
                // For phone numbers, SMS integration should be added (Twilio, etc.)
                log_message('info', "OTP for phone {$target}: {$code}");
            }

            return sendApiResponse(null, 'OTP sent (check your email or SMS).', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to send OTP.');
        }
    }

    /**
     * Verify OTP
     * POST /api/auth/verify-otp
     */
    public function verifyOtp(): ResponseInterface
    {
        try {
            $request = $this->request->getJSON();

            $rules = [
                'target' => ['rules' => 'required'],
                'code' => ['rules' => 'required|min_length[4]|max_length[8]'],
            ];

            $validation = validateRequest($request, $rules);
            if ($validation !== true) {
                return sendApiResponse(['errors' => $validation], 'Validation failed.', 422);
            }

            $target = $request->target;
            $code = $request->code;

            $otp = $this->otpModel->verifyOtp($target, $code);

            if (!$otp) {
                return sendApiResponse(null, 'Invalid or expired OTP', 400);
            }

            // Mark used
            $this->otpModel->markUsed($otp['id']);

            return sendApiResponse(null, 'OTP verified successfully', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to verify OTP.');
        }
    }

    /**
     * Login with OTP
     * POST /api/auth/login-otp
     */
    public function loginWithOtp(): ResponseInterface
    {
        try {
            $request = $this->request->getJSON();

            $rules = [
                'target' => ['rules' => 'required'],
                'code' => ['rules' => 'required|min_length[4]|max_length[8]'],
            ];

            $validation = validateRequest($request, $rules);
            if ($validation !== true) {
                return sendApiResponse(['errors' => $validation], 'Validation failed.', 422);
            }

            $target = $request->target;
            $code = $request->code;

            $otp = $this->otpModel->verifyOtp($target, $code);
            if (!$otp) {
                return sendApiResponse(null, 'Invalid or expired OTP', 400);
            }

            // If OTP linked to user, use that user; otherwise try find by email
            $user = null;
            if (!empty($otp['user_id'])) {
                $user = $this->userModel->find((int) $otp['user_id']);
            } else {
                $user = $this->userModel->findByEmail($target);
            }

            if (!$user) {
                return sendApiResponse(null, 'Account not found for OTP target', 404);
            }

            // Mark OTP used
            $this->otpModel->markUsed($otp['id']);

            // Update last login
            $this->userModel->updateLastLogin($user['id']);

            // Generate tokens
            $publicUser = $this->userModel->getUserPublicDetails($user['id']);
            $accessToken = $this->generateAccessToken($publicUser);
            $refreshToken = $this->generateRefreshToken($user['id']);

            // Store refresh token
            $this->userModel->updateRefreshToken(
                $user['id'],
                $refreshToken,
                getenv('JWT_REFRESH_EXPIRY') ?: 172800
            );

            $responseData = [
                'user' => $publicUser,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => getenv('JWT_ACCESS_EXPIRY') ?: 3600,
            ];

            return sendApiResponse($responseData, 'Login via OTP successful', 200)
                ->setCookie(
                    '_healthsphere_access_token',
                    $accessToken,
                    getenv('JWT_ACCESS_EXPIRY') ?: 3600,
                    $this->cookieSettings->domain,
                    $this->cookieSettings->path,
                    $this->cookieSettings->prefix,
                    $this->cookieSettings->secure,
                    $this->cookieSettings->httponly,
                    $this->cookieSettings->samesite
                )
                ->setCookie(
                    '_healthsphere_refresh_token',
                    $refreshToken,
                    getenv('JWT_REFRESH_EXPIRY') ?: 172800,
                    $this->cookieSettings->domain,
                    $this->cookieSettings->path,
                    $this->cookieSettings->prefix,
                    $this->cookieSettings->secure,
                    $this->cookieSettings->httponly,
                    $this->cookieSettings->samesite
                );
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('OTP login failed. Please try again.');
        }
    }

    /**
     * Reset password
     * POST /api/auth/reset-password
     *
     * @return ResponseInterface
     */
    public function resetPassword(): ResponseInterface
    {
        try {
            $request = $this->request->getJSON();

            $rules = [
                'token' => [
                    'label'  => 'Reset Token',
                    'rules'  => 'required|min_length[32]',
                    'errors' => [
                        'required'   => 'Reset token is required.',
                        'min_length' => 'Invalid reset token format.'
                    ]
                ],
                'new_password' => [
                    'label'  => 'New Password',
                    'rules'  => 'required|min_length[8]|max_length[255]|regex_match[/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]/]',
                    'errors' => [
                        'required'     => 'New password is required.',
                        'min_length'   => 'New password must be at least 8 characters long.',
                        'max_length'   => 'New password cannot exceed 255 characters.',
                        'regex_match'  => 'New password must contain at least one uppercase letter, one lowercase letter, one number, and one special character (@$!%*?&#).'
                    ]
                ]
            ];

            $validation = validateRequest($request, $rules);
            if ($validation !== true) {
                return sendApiResponse(['errors' => $validation], 'Validation failed. Please check your input.', 422);
            }

            $token = $request->token;
            $newPassword = $request->new_password;

            // Verify token
            $user = $this->userModel->verifyPasswordResetToken($token);

            if (!$user) {
                return sendApiResponse(null, 'Invalid or expired reset token', 400);
            }

            // Reset password
            $success = $this->userModel->resetPassword($user['id'], $newPassword);

            if (!$success) {
                return sendApiResponse(null, 'Failed to reset password', 500);
            }

            // Clear refresh tokens (force re-login)
            $this->userModel->clearRefreshToken($user['id']);

            return sendApiResponse(null, 'Password reset successfully', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Password reset failed. Please try again.');
        }
    }

    /**
     * Generate access token
     *
     * @param array $user
     * @return string
     */
    private function generateAccessToken(array $user): string
    {
        $payload = [
            'iat' => time(),
            'exp' => time() + (getenv('JWT_ACCESS_EXPIRY') ?: 3600),
            'uid' => $user['id'],
        ];

        $secretKey = getenv('JWT_SECRET');
        return JWT::encode($payload, $secretKey, 'HS256');
    }

    /**
     * Generate refresh token
     *
     * @param int $userId
     * @return string
     */
    private function generateRefreshToken(int $userId): string
    {
        $payload = [
            'iat' => time(),
            'exp' => time() + (getenv('JWT_REFRESH_EXPIRY') ?: 172800),
            'uid' => $userId,
            'type' => 'refresh',
        ];

        $secretKey = getenv('JWT_SECRET');
        return JWT::encode($payload, $secretKey, 'HS256');
    }
}
