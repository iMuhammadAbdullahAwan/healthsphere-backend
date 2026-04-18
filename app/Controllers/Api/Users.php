<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Users Controller
 *
 * Handles user profile management operations including:
 * - Profile retrieval and updates
 * - Profile image management
 * - Account deletion
 *
 * @package    HealthSphere
 * @subpackage Controllers\Api
 * @category   Users
 * @author     HealthSphere Team
 * @version    1.0.0
 */
class Users extends BaseController
{
    use ResponseTrait;

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
     * Admin: List users with optional search and pagination
     * GET /api/admin/users
     *
     * @return ResponseInterface
     */
    public function adminListUsers(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$this->hasAnyRole(['user_admin', 'super_admin'])) {
                return sendApiResponse(null, 'Forbidden', 403);
            }

            $page = (int) ($this->request->getGet('page') ?? 1);
            $limit = (int) ($this->request->getGet('limit') ?? 25);
            $q = $this->request->getGet('q') ?? '';

            $offset = max(0, ($page - 1) * $limit);

            // Only super_admin can list users
            if ($this->current_user_role !== 'super_admin') {
                return sendApiResponse(null, 'Only super admin can list users', 403);
            }

            // super_admin can see all users except other super_admins (for safety)
            $builder = $this->userModel->whereIn('role', ['user', 'user_admin']);

            if (!empty($q)) {
                $builder = $builder->groupStart()
                    ->like('full_name', $q)
                    ->orLike('email', $q)
                    ->groupEnd();
            }

            $users = $builder->select(['id', 'email', 'full_name', 'role', 'profile_img', 'created_at', 'last_login'])
                ->findAll($limit, $offset);

            $countBuilder = $this->userModel->whereIn('role', ['user', 'user_admin']);
            if (!empty($q)) {
                $countBuilder = $countBuilder->groupStart()
                    ->like('full_name', $q)
                    ->orLike('email', $q)
                    ->groupEnd();
            }
            $total = $countBuilder->countAllResults(false);

            return sendApiResponse([
                'users' => $users,
                'total' => (int) $total,
                'page' => $page,
                'limit' => $limit,
            ], 'Users retrieved', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to list users.');
        }
    }

    /**
     * Admin: Get dashboard analytics / stats
     * GET /api/admin/analytics
     *
     * @return ResponseInterface
     */
    public function adminAnalytics(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$this->hasAnyRole(['user_admin', 'super_admin'])) {
                return sendApiResponse(null, 'Forbidden', 403);
            }

            // Total users
            $totalUsers = $this->userModel->countAllResults(false);

            // Users by role
            $usersByRole = [
                'user' => $this->userModel->where('role', 'user')->countAllResults(false),
                'user_admin' => $this->userModel->where('role', 'user_admin')->countAllResults(false),
                'super_admin' => $this->userModel->where('role', 'super_admin')->countAllResults(false),
            ];

            // New users this week
            $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
            $newUsersThisWeek = $this->userModel->where('created_at >=', $weekAgo)->countAllResults(false);

            // New users this month
            $monthAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
            $newUsersThisMonth = $this->userModel->where('created_at >=', $monthAgo)->countAllResults(false);

            // Active users (logged in within last 7 days)
            $activeUsers = $this->userModel->where('last_login >=', $weekAgo)->countAllResults(false);

            // Get recent signups (last 10)
            $recentUsers = $this->userModel
                ->select(['id', 'email', 'full_name', 'created_at'])
                ->orderBy('created_at', 'DESC')
                ->limit(10)
                ->findAll();

            return sendApiResponse([
                'total_users' => (int) $totalUsers,
                'users_by_role' => $usersByRole,
                'new_users_this_week' => (int) $newUsersThisWeek,
                'new_users_this_month' => (int) $newUsersThisMonth,
                'active_users_this_week' => (int) $activeUsers,
                'recent_signups' => $recentUsers,
            ], 'Analytics retrieved', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to get analytics.');
        }
    }

    /**
     * Admin: Update a user's role
     * PUT /api/admin/users/{id}/role
     *
     * @param int $id
     * @return ResponseInterface
     */
    public function adminUpdateRole($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$this->hasAnyRole(['user_admin', 'super_admin'])) {
                return sendApiResponse(null, 'Forbidden', 403);
            }

            $targetId = (int) $id;
            if (!$targetId) {
                return sendApiResponse(null, 'User id is required', 400);
            }

            $request = $this->request->getJSON(true);
            $role = $request['role'] ?? null;

            $allowedRoles = ['user', 'user_admin', 'super_admin'];
            if (!$role || !in_array($role, $allowedRoles, true)) {
                return sendApiResponse(null, 'Invalid role', 422);
            }

            // Only super_admin can assign or remove super_admin
            if ($role === 'super_admin' && !$this->hasAnyRole(['super_admin'])) {
                return sendApiResponse(null, 'Only super admin can assign super_admin role', 403);
            }

            // Prevent self demotion
            if ($targetId === $this->current_user_id && $this->current_user_role === 'super_admin' && $role !== 'super_admin') {
                return sendApiResponse(null, 'Super admin cannot demote themselves', 403);
            }

            $user = $this->userModel->find($targetId);
            if (!$user) {
                return sendApiResponse(null, 'User not found', 404);
            }

            $targetRole = $user['role'] ?? 'user';

            // Only super_admin can modify roles
            if ($this->current_user_role !== 'super_admin') {
                return sendApiResponse(null, 'Only super admin can modify user roles', 403);
            }

            // super_admin cannot modify other super_admins
            if ($this->current_user_role === 'super_admin' && $targetRole === 'super_admin' && $targetId !== $this->current_user_id) {
                return sendApiResponse(null, 'Cannot modify another super admin', 403);
            }

            $this->userModel->update($targetId, ['role' => $role]);

            $updated = $this->userModel->getUserPublicDetails($targetId);

            return sendApiResponse($updated, 'User role updated', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to update user role.');
        }
    }

    /**
     * Admin: Delete a user (soft delete)
     * DELETE /api/admin/users/{id}
     *
     * @param int $id
     * @return ResponseInterface
     */
    public function adminDeleteUser($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            // Only super_admin can delete users
            if (!$this->hasAnyRole(['super_admin'])) {
                return sendApiResponse(null, 'Forbidden', 403);
            }

            $targetId = (int) $id;
            if (!$targetId) {
                return sendApiResponse(null, 'User id is required', 400);
            }

            // Prevent deleting self
            if ($targetId === $this->current_user_id) {
                return sendApiResponse(null, 'Cannot delete your own account', 403);
            }

            $user = $this->userModel->find($targetId);
            if (!$user) {
                return sendApiResponse(null, 'User not found', 404);
            }

            $targetRole = $user['role'] ?? 'user';

            // Only super_admin can delete users
            if ($this->current_user_role !== 'super_admin') {
                return sendApiResponse(null, 'Only super admin can delete users', 403);
            }

            // super_admin cannot delete other super_admins (safety measure)
            if ($this->current_user_role === 'super_admin' && $targetRole === 'super_admin') {
                return sendApiResponse(null, 'Cannot delete another super admin account', 403);
            }

            $this->userModel->delete($targetId);

            return sendApiResponse(null, 'User deleted', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to delete user.');
        }
    }

    /**
     * Get user profile
     * GET /api/users/profile
     *
     * @return ResponseInterface
     */
    public function getProfile(): ResponseInterface
    {
        try {
            $userId = $this->current_user_id;

            if (!$userId) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $user = $this->userModel->getUserPublicDetails($userId);

            if (!$user) {
                return sendApiResponse(null, 'User not found', 404);
            }

            return sendApiResponse($user, 'Profile retrieved successfully', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to retrieve profile. Please try again.');
        }
    }

    /**
     * Update user profile
     * PUT /api/users/profile
     *
     * @return ResponseInterface
     */
    public function updateProfile(): ResponseInterface
    {
        try {
            $userId = $this->current_user_id;

            if (!$userId) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $request = $this->request->getJSON();

            $rules = [
                'full_name' => [
                    'label'  => 'Full Name',
                    'rules'  => 'permit_empty|min_length[2]|max_length[255]|alpha_space',
                    'errors' => [
                        'min_length'  => 'Full name must be at least 2 characters.',
                        'max_length'  => 'Full name cannot exceed 255 characters.',
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

            $updateData = [];

            if (isset($request->full_name)) {
                $updateData['full_name'] = $request->full_name;
            }
            if (isset($request->date_of_birth)) {
                $updateData['date_of_birth'] = $request->date_of_birth;
            }
            if (isset($request->gender)) {
                $updateData['gender'] = $request->gender;
            }
            if (isset($request->phone)) {
                $updateData['phone'] = $request->phone;
            }

            if (empty($updateData)) {
                return sendApiResponse(null, 'No fields to update', 400);
            }

            $success = $this->userModel->updateUser($userId, $updateData);

            if (!$success) {
                return sendApiResponse(null, 'Failed to update profile', 500);
            }

            $user = $this->userModel->getUserPublicDetails($userId);

            return sendApiResponse($user, 'Profile updated successfully', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to update profile. Please try again.');
        }
    }

    /**
     * Delete user account
     * DELETE /api/users/account
     *
     * @return ResponseInterface
     */
    public function deleteAccount(): ResponseInterface
    {
        try {
            $userId = $this->current_user_id;

            if (!$userId) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $request = $this->request->getJSON();

            // Validate input
            $rules = [
                'password' => [
                    'label'  => 'Password',
                    'rules'  => 'required',
                    'errors' => [
                        'required' => 'Password confirmation is required to delete your account.'
                    ]
                ]
            ];

            $validation = validateRequest($request, $rules);
            if ($validation !== true) {
                return sendApiResponse(['errors' => $validation], 'Validation failed.', 422);
            }

            $password = $request->password;

            // Verify password
            $user = $this->userModel->find($userId);
            if (!password_verify($password, $user['password_hash'])) {
                return sendApiResponse(null, 'Invalid password', 403);
            }

            // Soft delete
            $success = $this->userModel->delete($userId);

            if (!$success) {
                return sendApiResponse(null, 'Failed to delete account', 500);
            }

            // Clear cookies
            return sendApiResponse(null, 'Account deleted successfully', 200)
                ->deleteCookie('_healthsphere_access_token')
                ->deleteCookie('_healthsphere_refresh_token');
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to delete account. Please try again.');
        }
    }

    /**
     * Upload profile image
     * POST /api/users/profile/image
     *
     * @return ResponseInterface
     */
    public function uploadProfileImage(): ResponseInterface
    {
        try {
            $userId = $this->current_user_id;

            if (!$userId) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $file = $this->request->getFile('profile_img');

            if (!$file || !$file->isValid()) {
                return sendApiResponse(null, 'No valid image file uploaded', 400);
            }

            // Validate file
            $validationRules = [
                'profile_img' => [
                    'label' => 'Profile Image',
                    'rules' => 'uploaded[profile_img]|is_image[profile_img]|mime_in[profile_img,image/jpg,image/jpeg,image/png,image/gif]|max_size[profile_img,2048]|max_dims[profile_img,4096,4096]',
                    'errors' => [
                        'uploaded'  => 'Please select an image file to upload.',
                        'is_image'  => 'The file must be a valid image.',
                        'mime_in'   => 'Only JPG, JPEG, PNG, and GIF images are allowed.',
                        'max_size'  => 'Image size cannot exceed 2MB.',
                        'max_dims'  => 'Image dimensions cannot exceed 4096x4096 pixels.'
                    ]
                ]
            ];

            if (!$this->validate($validationRules)) {
                return sendApiResponse(
                    ['errors' => $this->validator->getErrors()],
                    'Image validation failed. Please check your file and try again.',
                    422
                );
            }

            // Generate unique filename
            $newName = $userId . '_' . time() . '.' . $file->getExtension();

            // Move file to public/uploads/profiles directory
            $uploadPath = rtrim(FCPATH, '\\/') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles';
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            $file->move($uploadPath, $newName);

            // Update user profile
            $imagePath = 'uploads/profiles/' . $newName;
            $this->userModel->update($userId, ['profile_img' => $imagePath]);

            $user = $this->userModel->getUserPublicDetails($userId);

            return sendApiResponse($user, 'Profile image uploaded successfully', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to upload image. Please try again.');
        }
    }
}
