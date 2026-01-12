<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Filters\FilterInterface;
use App\Models\UserModel;
use Config\Services;

/**
 * AdminFilter
 *
 * Ensures the authenticated user has admin privileges before allowing access
 */
class AdminFilter implements FilterInterface
{
    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * @param RequestInterface|IncomingRequest $request
     * @param array|null $arguments
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Use the response service to return JSON errors
        $response = Services::response();

        // Read current user id injected by TokenFilter
        $currentUserId = null;
        if ($request instanceof IncomingRequest) {
            $currentUserId = $request->getPost('current_user_id');
        }

        if (!$currentUserId) {
            return $response->setJSON([
                'data' => null,
                'message' => 'Unauthorized: missing user context',
                'status' => 401,
            ])->setStatusCode(401);
        }

        $user = $this->userModel->find((int) $currentUserId);
        if (!$user) {
            return $response->setJSON([
                'data' => null,
                'message' => 'Unauthorized: user not found',
                'status' => 401,
            ])->setStatusCode(401);
        }

        $role = $user['role'] ?? 'user';
        if (!in_array($role, ['user_admin', 'super_admin'], true)) {
            return $response->setJSON([
                'data' => null,
                'message' => 'Forbidden: admin access required',
                'status' => 403,
            ])->setStatusCode(403);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }
}
