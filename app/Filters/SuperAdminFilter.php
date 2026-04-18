<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Filters\FilterInterface;
use App\Models\UserModel;
use Config\Services;

/**
 * SuperAdminFilter
 *
 * Ensures the authenticated user is a super_admin.
 */
class SuperAdminFilter implements FilterInterface
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
        $response = Services::response();

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
        if ($role !== 'super_admin') {
            return $response->setJSON([
                'data' => null,
                'message' => 'Forbidden: super admin access required',
                'status' => 403,
            ])->setStatusCode(403);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }
}
