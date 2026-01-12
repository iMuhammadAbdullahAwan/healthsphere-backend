<?php

namespace App\Filters;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Filters\FilterInterface;
use App\Models\UserModel;
use Config\Services;

/**
 * TokenFilter
 * 
 * JWT Authentication Filter for protected routes.
 * Validates JWT tokens from cookies and injects user data into request.
 * 
 * @package HealthSphere
 * @version 1.0.0
 */
class TokenFilter implements FilterInterface
{
    /**
     * User model instance
     *
     * @var UserModel
     */
    private UserModel $userModel;

    /**
     * Constructor - Initialize dependencies
     */
    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * Validate JWT token before request reaches controller
     *
     * @param RequestInterface|IncomingRequest $request
     * @param array|null                       $arguments
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Skip auth for preflight OPTIONS requests
        if ($request->getMethod() === 'options') {
            return;
        }

        $response = Services::response();

        // Try to get token from cookie first, then Authorization header
        $accessToken = $request->getCookie('_healthsphere_access_token');

        if (!$accessToken) {
            // Try Authorization header as fallback
            $authHeader = $request->getHeader('Authorization');
            if ($authHeader) {
                $authHeaderValue = $authHeader->getValue();
                if (preg_match('/Bearer\s+(.*)$/i', $authHeaderValue, $matches)) {
                    $accessToken = $matches[1];
                }
            }
        }

        if (!$accessToken) {
            return $response->setJSON([
                'data' => null,
                'message' => 'Unauthorized access. Missing authentication token.',
                'status' => 401
            ])->setStatusCode(401, 'Unauthorized access');
        }

        try {
            // Decode and validate JWT token
            $secretKey = getenv('JWT_SECRET');
            if (!$secretKey) {
                throw new \Exception('JWT secret not configured');
            }

            $decoded = JWT::decode($accessToken, new Key($secretKey, 'HS256'));

            // Check if token is expired
            if ($decoded->exp < time()) {
                return $response->setJSON([
                    'data' => null,
                    'message' => 'Token expired. Please login again.',
                    'status' => 401
                ])->setStatusCode(401, 'Token expired');
            }

            // Verify user exists
            $user = $this->userModel->find($decoded->uid);

            if (!$user) {
                return $response->setJSON([
                    'data' => null,
                    'message' => 'Invalid access token. User not found.',
                    'status' => 401
                ])->setStatusCode(401, 'Invalid token');
            }

            // Inject user data into request for use in controllers
            $currentPostData = $request->getPost();
            $currentPostData['current_user_id'] = $user['id'];
            $request->setGlobal('post', $currentPostData);
        } catch (\Firebase\JWT\ExpiredException $e) {
            return $response->setJSON([
                'data' => null,
                'message' => 'Token expired. Please login again.',
                'status' => 401
            ])->setStatusCode(401, 'Token expired');
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            return $response->setJSON([
                'data' => null,
                'message' => 'Invalid token signature.',
                'status' => 401
            ])->setStatusCode(401, 'Invalid signature');
        } catch (\Exception $e) {
            log_message('error', 'TokenFilter error: ' . $e->getMessage());
            return $response->setJSON([
                'data' => null,
                'message' => 'Unauthorized access. Invalid token.',
                'status' => 401
            ])->setStatusCode(401, 'Unauthorized access');
        }
    }

    /**
     * Executed after the controller method
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param mixed $arguments
     * @return void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action needed after request
    }
}
