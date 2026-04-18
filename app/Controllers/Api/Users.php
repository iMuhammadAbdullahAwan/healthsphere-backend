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
            $roleFilter = $this->request->getGet('role') ?? null;

            $offset = max(0, ($page - 1) * $limit);

            // super_admin can list user_admin + user, user_admin can only list assigned users with role=user.
            if ($this->current_user_role === 'super_admin') {
                $builder = $this->userModel->whereIn('role', ['user', 'user_admin']);
                if (!empty($roleFilter) && in_array($roleFilter, ['user', 'user_admin'], true)) {
                    $builder->where('role', $roleFilter);
                }
            } else {
                $builder = $this->userModel
                    ->where('role', 'user')
                    ->where('managed_by_admin_id', $this->current_user_id);
            }

            if (!empty($q)) {
                $builder = $builder->groupStart()
                    ->like('full_name', $q)
                    ->orLike('email', $q)
                    ->groupEnd();
            }

            $users = $builder->select(['id', 'email', 'full_name', 'role', 'profile_img', 'managed_by_admin_id', 'created_at', 'last_login'])
                ->findAll($limit, $offset);

            if ($this->current_user_role === 'super_admin') {
                $countBuilder = $this->userModel->whereIn('role', ['user', 'user_admin']);
                if (!empty($roleFilter) && in_array($roleFilter, ['user', 'user_admin'], true)) {
                    $countBuilder->where('role', $roleFilter);
                }
            } else {
                $countBuilder = $this->userModel
                    ->where('role', 'user')
                    ->where('managed_by_admin_id', $this->current_user_id);
            }

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
                'scope' => $this->current_user_role === 'super_admin' ? 'platform' : 'assigned_users',
            ], 'Users retrieved', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to list users.');
        }
    }

    /**
     * Admin: List all user_admin accounts with assigned user counts
     * GET /api/admin/user-admins
     */
    public function adminListUserAdmins(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$this->hasAnyRole(['super_admin'])) {
                return sendApiResponse(null, 'Only super admin can list user admins', 403);
            }

            $db = \Config\Database::connect();
            $q = trim((string)($this->request->getGet('q') ?? ''));

            $builder = $db->table('users ua')
                ->select('ua.id, ua.email, ua.full_name, ua.role, ua.created_at, ua.last_login, COUNT(u.id) AS assigned_users')
                ->join('users u', 'u.managed_by_admin_id = ua.id AND u.deleted_at IS NULL AND u.role = "user"', 'left')
                ->where('ua.deleted_at', null)
                ->where('ua.role', 'user_admin');

            if ($q !== '') {
                $builder->groupStart()
                    ->like('ua.full_name', $q)
                    ->orLike('ua.email', $q)
                    ->groupEnd();
            }

            $items = $builder
                ->groupBy('ua.id')
                ->orderBy('ua.created_at', 'DESC')
                ->get()
                ->getResultArray();

            return sendApiResponse(['user_admins' => $items], 'User admins retrieved', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to list user admins.');
        }
    }

    /**
     * Admin: List unassigned users
     * GET /api/admin/users/unassigned
     */
    public function adminListUnassignedUsers(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$this->hasAnyRole(['super_admin'])) {
                return sendApiResponse(null, 'Only super admin can list unassigned users', 403);
            }

            $page = (int)($this->request->getGet('page') ?? 1);
            $limit = (int)($this->request->getGet('limit') ?? 25);
            $q = trim((string)($this->request->getGet('q') ?? ''));
            $offset = max(0, ($page - 1) * $limit);

            $builder = $this->userModel
                ->where('deleted_at', null)
                ->where('role', 'user')
                ->where('managed_by_admin_id', null);

            if ($q !== '') {
                $builder->groupStart()
                    ->like('full_name', $q)
                    ->orLike('email', $q)
                    ->groupEnd();
            }

            $items = $builder
                ->select(['id', 'email', 'full_name', 'role', 'created_at', 'last_login'])
                ->orderBy('created_at', 'DESC')
                ->findAll($limit, $offset);

            $countBuilder = $this->userModel
                ->where('deleted_at', null)
                ->where('role', 'user')
                ->where('managed_by_admin_id', null);

            if ($q !== '') {
                $countBuilder->groupStart()
                    ->like('full_name', $q)
                    ->orLike('email', $q)
                    ->groupEnd();
            }

            $total = (int)$countBuilder->countAllResults(false);

            return sendApiResponse([
                'users' => $items,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ], 'Unassigned users retrieved', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to list unassigned users.');
        }
    }

    /**
     * Admin: Get managed user profile
     * GET /api/admin/users/{id}/profile
     */
    public function adminGetManagedUserProfile($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$this->hasAnyRole(['user_admin', 'super_admin'])) {
                return sendApiResponse(null, 'Forbidden', 403);
            }

            $targetId = (int)$id;
            if (!$targetId) {
                return sendApiResponse(null, 'User id is required', 400);
            }

            $targetUser = $this->userModel->find($targetId);
            if (!$targetUser) {
                return sendApiResponse(null, 'User not found', 404);
            }

            if (($targetUser['role'] ?? 'user') !== 'user') {
                return sendApiResponse(null, 'Only users with role user can be managed via this endpoint', 422);
            }

            if ($this->current_user_role === 'user_admin' && (int)($targetUser['managed_by_admin_id'] ?? 0) !== (int)$this->current_user_id) {
                return sendApiResponse(null, 'User is not assigned to this user admin', 403);
            }

            $profile = $this->userModel->getUserPublicDetails($targetId);
            return sendApiResponse($profile, 'Managed user profile retrieved successfully', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to retrieve managed user profile.');
        }
    }

    /**
     * Admin: Update managed user profile
     * PUT /api/admin/users/{id}/profile
     */
    public function adminUpdateManagedUserProfile($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$this->hasAnyRole(['user_admin', 'super_admin'])) {
                return sendApiResponse(null, 'Forbidden', 403);
            }

            $targetId = (int)$id;
            if (!$targetId) {
                return sendApiResponse(null, 'User id is required', 400);
            }

            $targetUser = $this->userModel->find($targetId);
            if (!$targetUser) {
                return sendApiResponse(null, 'User not found', 404);
            }

            if (($targetUser['role'] ?? 'user') !== 'user') {
                return sendApiResponse(null, 'Only users with role user can be managed via this endpoint', 422);
            }

            if ($this->current_user_role === 'user_admin' && (int)($targetUser['managed_by_admin_id'] ?? 0) !== (int)$this->current_user_id) {
                return sendApiResponse(null, 'User is not assigned to this user admin', 403);
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

            $success = $this->userModel->updateUser($targetId, $updateData);
            if (!$success) {
                return sendApiResponse(null, 'Failed to update profile', 500);
            }

            $profile = $this->userModel->getUserPublicDetails($targetId);
            return sendApiResponse($profile, 'Managed user profile updated successfully', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to update managed user profile.');
        }
    }

    /**
     * Admin: Upload managed user profile image
     * POST /api/admin/users/{id}/profile/image
     */
    public function adminUploadManagedUserProfileImage($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$this->hasAnyRole(['user_admin', 'super_admin'])) {
                return sendApiResponse(null, 'Forbidden', 403);
            }

            $targetId = (int)$id;
            if (!$targetId) {
                return sendApiResponse(null, 'User id is required', 400);
            }

            $targetUser = $this->userModel->find($targetId);
            if (!$targetUser) {
                return sendApiResponse(null, 'User not found', 404);
            }

            if (($targetUser['role'] ?? 'user') !== 'user') {
                return sendApiResponse(null, 'Only users with role user can be managed via this endpoint', 422);
            }

            if ($this->current_user_role === 'user_admin' && (int)($targetUser['managed_by_admin_id'] ?? 0) !== (int)$this->current_user_id) {
                return sendApiResponse(null, 'User is not assigned to this user admin', 403);
            }

            $file = $this->request->getFile('profile_img');
            if (!$file || !$file->isValid()) {
                return sendApiResponse(null, 'No valid image file uploaded', 400);
            }

            $validationRules = [
                'profile_img' => [
                    'label' => 'Profile Image',
                    'rules' => 'uploaded[profile_img]|is_image[profile_img]|mime_in[profile_img,image/jpg,image/jpeg,image/png,image/gif]|max_size[profile_img,2048]|max_dims[profile_img,4096,4096]',
                ]
            ];

            if (!$this->validate($validationRules)) {
                return sendApiResponse(
                    ['errors' => $this->validator->getErrors()],
                    'Image validation failed. Please check your file and try again.',
                    422
                );
            }

            $newName = $targetId . '_' . time() . '.' . $file->getExtension();
            $uploadPath = rtrim(FCPATH, '\\/') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles';
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            $file->move($uploadPath, $newName);

            $imagePath = 'uploads/profiles/' . $newName;
            $this->userModel->update($targetId, ['profile_img' => $imagePath]);

            $profile = $this->userModel->getUserPublicDetails($targetId);
            return sendApiResponse($profile, 'Managed user profile image uploaded successfully', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to upload managed user profile image.');
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

            $db = \Config\Database::connect();

            $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
            $monthAgo = date('Y-m-d H:i:s', strtotime('-30 days'));

            // user_admin only gets assigned-user analytics
            if ($this->current_user_role === 'user_admin') {
                $assignedTotal = (int)$db->table('users')
                    ->where('deleted_at', null)
                    ->where('role', 'user')
                    ->where('managed_by_admin_id', $this->current_user_id)
                    ->countAllResults();

                $assignedNewThisWeek = (int)$db->table('users')
                    ->where('deleted_at', null)
                    ->where('role', 'user')
                    ->where('managed_by_admin_id', $this->current_user_id)
                    ->where('created_at >=', $weekAgo)
                    ->countAllResults();

                $assignedActiveThisWeek = (int)$db->table('users')
                    ->where('deleted_at', null)
                    ->where('role', 'user')
                    ->where('managed_by_admin_id', $this->current_user_id)
                    ->where('last_login >=', $weekAgo)
                    ->countAllResults();

                $recentAssignedUsers = $db->table('users')
                    ->select('id, email, full_name, role, created_at, last_login')
                    ->where('deleted_at', null)
                    ->where('role', 'user')
                    ->where('managed_by_admin_id', $this->current_user_id)
                    ->orderBy('created_at', 'DESC')
                    ->limit(10)
                    ->get()
                    ->getResultArray();

                return sendApiResponse([
                    'scope' => 'assigned_users',
                    'users' => [
                        'assigned_total' => $assignedTotal,
                        'assigned_new_this_week' => $assignedNewThisWeek,
                        'assigned_active_this_week' => $assignedActiveThisWeek,
                    ],
                    'recent_users' => $recentAssignedUsers,
                ], 'Assigned user analytics retrieved successfully', 200);
            }

            // User and admin metrics
            $totalUsers = (int)$db->table('users')->where('deleted_at', null)->countAllResults();
            $usersByRole = [
                'user' => (int)$db->table('users')->where('deleted_at', null)->where('role', 'user')->countAllResults(),
                'user_admin' => (int)$db->table('users')->where('deleted_at', null)->where('role', 'user_admin')->countAllResults(),
                'super_admin' => (int)$db->table('users')->where('deleted_at', null)->where('role', 'super_admin')->countAllResults(),
            ];

            $newUsersThisWeek = (int)$db->table('users')
                ->where('deleted_at', null)
                ->where('created_at >=', $weekAgo)
                ->countAllResults();

            $newUsersThisMonth = (int)$db->table('users')
                ->where('deleted_at', null)
                ->where('created_at >=', $monthAgo)
                ->countAllResults();

            $activeUsersThisWeek = (int)$db->table('users')
                ->where('deleted_at', null)
                ->where('last_login >=', $weekAgo)
                ->countAllResults();

            $verifiedUsers = (int)$db->table('users')
                ->where('deleted_at', null)
                ->where('email_verified_at IS NOT NULL', null, false)
                ->countAllResults();

            $assignedUsers = (int)$db->table('users')
                ->where('deleted_at', null)
                ->where('role', 'user')
                ->where('managed_by_admin_id IS NOT NULL', null, false)
                ->countAllResults();

            $unassignedUsers = (int)$db->table('users')
                ->where('deleted_at', null)
                ->where('role', 'user')
                ->where('managed_by_admin_id', null)
                ->countAllResults();

            $adminAssignmentLoad = $db->table('users u')
                ->select('u.id, u.full_name, u.email, COUNT(au.id) as assigned_users')
                ->join('users au', 'au.managed_by_admin_id = u.id AND au.deleted_at IS NULL AND au.role = "user"', 'left')
                ->where('u.deleted_at', null)
                ->where('u.role', 'user_admin')
                ->groupBy('u.id')
                ->orderBy('assigned_users', 'DESC')
                ->get()
                ->getResultArray();

            $recentUsers = $db->table('users')
                ->select('id, email, full_name, role, managed_by_admin_id, created_at, last_login')
                ->where('deleted_at', null)
                ->orderBy('created_at', 'DESC')
                ->limit(10)
                ->get()
                ->getResultArray();

            // Platform-wide usage metrics
            $scheduleTotals = [
                'total' => (int)$db->table('schedules')->countAllResults(),
                'active' => (int)$db->table('schedules')->where('status', 'active')->countAllResults(),
                'paused' => (int)$db->table('schedules')->where('status', 'paused')->countAllResults(),
                'completed' => (int)$db->table('schedules')->where('status', 'completed')->countAllResults(),
                'canceled' => (int)$db->table('schedules')->where('status', 'canceled')->countAllResults(),
            ];

            $scheduleLogs = [
                'active_logs' => (int)$db->table('schedule_logs')->countAllResults(),
                'pending' => (int)$db->table('schedule_logs')->where('status', 'pending')->countAllResults(),
                'completed' => (int)$db->table('schedule_logs')->where('status', 'completed')->countAllResults(),
                'canceled' => (int)$db->table('schedule_logs')->where('status', 'canceled')->countAllResults(),
                'history_total' => (int)$db->table('schedule_history')->countAllResults(),
            ];

            $foodLogsTotal = (int)$db->table('food_logs')->where('deleted_at', null)->countAllResults();
            $foodLogsThisMonth = (int)$db->table('food_logs')
                ->where('deleted_at', null)
                ->where('created_at >=', $monthAgo)
                ->countAllResults();

            $exerciseLogsTotal = (int)$db->table('exercise_logs')->countAllResults();
            $stepSessionsTotal = (int)$db->table('step_sessions')->countAllResults();

            $notificationTotals = [
                'notifications' => (int)$db->table('notifications')->countAllResults(),
                'deliveries' => (int)$db->table('notification_users')->countAllResults(),
                'unread_deliveries' => (int)$db->table('notification_users')->where('is_read', 0)->countAllResults(),
            ];

            return sendApiResponse([
                'scope' => 'platform',
                'users' => [
                    'total' => $totalUsers,
                    'verified' => $verifiedUsers,
                    'new_this_week' => $newUsersThisWeek,
                    'new_this_month' => $newUsersThisMonth,
                    'active_this_week' => $activeUsersThisWeek,
                    'assigned' => $assignedUsers,
                    'unassigned' => $unassignedUsers,
                    'by_role' => $usersByRole,
                ],
                'user_admin_assignment_load' => $adminAssignmentLoad,
                'platform' => [
                    'schedules' => $scheduleTotals,
                    'schedule_logs' => $scheduleLogs,
                    'food_logs' => [
                        'total' => $foodLogsTotal,
                        'this_month' => $foodLogsThisMonth,
                    ],
                    'exercise_logs' => [
                        'total' => $exerciseLogsTotal,
                    ],
                    'step_sessions' => [
                        'total' => $stepSessionsTotal,
                    ],
                    'notifications' => $notificationTotals,
                ],
                'recent_users' => $recentUsers,
            ], 'Platform analytics retrieved successfully', 200);
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

            $updateData = ['role' => $role];

            // Super admin may assign/unassign manager while updating role.
            if (array_key_exists('managed_by_admin_id', $request)) {
                $managedByAdminId = $request['managed_by_admin_id'];
                if ($managedByAdminId === null || $managedByAdminId === '' || (int)$managedByAdminId === 0) {
                    $updateData['managed_by_admin_id'] = null;
                } else {
                    $manager = $this->userModel->find((int)$managedByAdminId);
                    if (!$manager || ($manager['role'] ?? null) !== 'user_admin') {
                        return sendApiResponse(null, 'managed_by_admin_id must reference a user_admin account', 422);
                    }
                    $updateData['managed_by_admin_id'] = (int)$managedByAdminId;
                }
            }

            // Non-user roles are not assigned under user_admin.
            if ($role !== 'user') {
                $updateData['managed_by_admin_id'] = null;
            }

            $this->userModel->update($targetId, $updateData);

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

            // user_admin can delete only assigned users; super_admin can delete all except another super_admin.
            if (!$this->hasAnyRole(['user_admin', 'super_admin'])) {
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

            if ($this->current_user_role === 'user_admin') {
                if ($targetRole !== 'user') {
                    return sendApiResponse(null, 'User admin can only delete assigned users with role user', 403);
                }

                if ((int)($user['managed_by_admin_id'] ?? 0) !== (int)$this->current_user_id) {
                    return sendApiResponse(null, 'User is not assigned to this user admin', 403);
                }
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
     * Admin: Assign a user to a user_admin
     * PUT /api/admin/users/{id}/assign
     * Body: { "user_admin_id": 2, "promote_to_user_admin": true }
     */
    public function adminAssignUser($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$this->hasAnyRole(['super_admin'])) {
                return sendApiResponse(null, 'Only super admin can assign users to user admins', 403);
            }

            $targetId = (int)$id;
            if (!$targetId) {
                return sendApiResponse(null, 'User id is required', 400);
            }

            $request = $this->request->getJSON(true) ?? [];
            $userAdminId = (int)($request['user_admin_id'] ?? 0);
            $promoteToUserAdmin = (bool)($request['promote_to_user_admin'] ?? false);
            if (!$userAdminId) {
                return sendApiResponse(null, 'user_admin_id is required', 422);
            }

            $targetUser = $this->userModel->find($targetId);
            if (!$targetUser) {
                return sendApiResponse(null, 'Target user not found', 404);
            }

            if (($targetUser['role'] ?? null) !== 'user') {
                return sendApiResponse(null, 'Only users with role=user can be assigned to a user admin', 422);
            }

            if ($userAdminId === $targetId) {
                return sendApiResponse(null, 'A user cannot be assigned to themselves as user admin', 422);
            }

            $userAdmin = $this->userModel->find($userAdminId);
            if (!$userAdmin) {
                return sendApiResponse(null, 'user_admin_id does not reference an existing user account', 422);
            }

            $managerRole = $userAdmin['role'] ?? null;
            if ($managerRole !== 'user_admin') {
                if ($managerRole === 'user' && $promoteToUserAdmin) {
                    $this->userModel->update($userAdminId, ['role' => 'user_admin', 'managed_by_admin_id' => null]);
                    $userAdmin = $this->userModel->find($userAdminId);
                    $managerRole = $userAdmin['role'] ?? null;
                }

                if ($managerRole !== 'user_admin') {
                    return sendApiResponse(
                        null,
                        'user_admin_id must reference an existing user_admin account. Set promote_to_user_admin=true to promote a regular user first.',
                        422
                    );
                }
            }

            $this->userModel->update($targetId, ['managed_by_admin_id' => $userAdminId]);
            $updated = $this->userModel->getUserPublicDetails($targetId);

            return sendApiResponse($updated, 'User assigned to user admin successfully', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to assign user.');
        }
    }

    /**
     * Admin: Unassign a user from a user_admin
     * DELETE /api/admin/users/{id}/assign
     */
    public function adminUnassignUser($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$this->hasAnyRole(['super_admin'])) {
                return sendApiResponse(null, 'Only super admin can unassign users', 403);
            }

            $targetId = (int)$id;
            if (!$targetId) {
                return sendApiResponse(null, 'User id is required', 400);
            }

            $targetUser = $this->userModel->find($targetId);
            if (!$targetUser) {
                return sendApiResponse(null, 'Target user not found', 404);
            }

            if (($targetUser['role'] ?? null) !== 'user') {
                return sendApiResponse(null, 'Only users with role=user can be unassigned', 422);
            }

            $this->userModel->update($targetId, ['managed_by_admin_id' => null]);
            $updated = $this->userModel->getUserPublicDetails($targetId);

            return sendApiResponse($updated, 'User unassigned successfully', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to unassign user.');
        }
    }

    /**
     * User: Get own user_admin assignment details
     * GET /api/users/assignment
     */
    public function getMyAssignment(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if ($this->is_acting_as_user) {
                return sendApiResponse(null, 'Delegated requests cannot read assignment details via self endpoint', 403);
            }

            $user = $this->userModel->find($this->current_user_id);
            if (!$user) {
                return sendApiResponse(null, 'User not found', 404);
            }

            $managedByAdminId = (int)($user['managed_by_admin_id'] ?? 0);
            $assignedAdmin = null;

            if ($managedByAdminId > 0) {
                $assignedAdmin = $this->userModel
                    ->select(['id', 'email', 'full_name', 'role', 'profile_img'])
                    ->find($managedByAdminId);
            }

            return sendApiResponse([
                'user_id' => (int)$user['id'],
                'user_role' => $user['role'] ?? 'user',
                'managed_by_admin_id' => $managedByAdminId > 0 ? $managedByAdminId : null,
                'assigned_user_admin' => $assignedAdmin,
            ], 'Assignment retrieved successfully', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to retrieve assignment.');
        }
    }

    /**
     * User: Assign own user_admin
     * PUT /api/users/assignment
     * Body: { "user_admin_id": 12, "promote_to_user_admin": true }
     */
    public function assignMyUserAdmin(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            // Prevent assignment mutation through delegated context.
            if ($this->is_acting_as_user) {
                return sendApiResponse(null, 'Delegated requests cannot change user assignment', 403);
            }

            $user = $this->userModel->find($this->current_user_id);
            if (!$user) {
                return sendApiResponse(null, 'User not found', 404);
            }

            if (($user['role'] ?? 'user') !== 'user') {
                return sendApiResponse(null, 'Only users with role user can self-assign to a user admin', 403);
            }

            $request = $this->request->getJSON(true) ?? [];
            $userAdminId = (int)($request['user_admin_id'] ?? 0);
            $promoteToUserAdmin = !array_key_exists('promote_to_user_admin', $request)
                ? true
                : (bool)$request['promote_to_user_admin'];
            if ($userAdminId <= 0) {
                return sendApiResponse(null, 'user_admin_id is required', 422);
            }

            if ($userAdminId === (int)$user['id']) {
                return sendApiResponse(null, 'User cannot assign themselves as user admin', 422);
            }

            $userAdmin = $this->userModel->find($userAdminId);
            if (!$userAdmin) {
                return sendApiResponse(null, 'user_admin_id does not reference an existing user account', 422);
            }

            $managerRole = $userAdmin['role'] ?? null;
            if ($managerRole !== 'user_admin') {
                if ($managerRole === 'user' && $promoteToUserAdmin) {
                    $this->userModel->update($userAdminId, ['role' => 'user_admin', 'managed_by_admin_id' => null]);
                    $userAdmin = $this->userModel->find($userAdminId);
                    $managerRole = $userAdmin['role'] ?? null;
                }

                if ($managerRole !== 'user_admin') {
                    return sendApiResponse(
                        null,
                        'user_admin_id must reference a user_admin account, or a regular user with promote_to_user_admin=true.',
                        422
                    );
                }
            }

            if ((int)($user['managed_by_admin_id'] ?? 0) === $userAdminId) {
                $assignedAdmin = $this->userModel
                    ->select(['id', 'email', 'full_name', 'role', 'profile_img'])
                    ->find($userAdminId);

                return sendApiResponse([
                    'user_id' => (int)$user['id'],
                    'managed_by_admin_id' => $userAdminId,
                    'assigned_user_admin' => $assignedAdmin,
                ], 'User is already assigned to this user admin', 200);
            }

            $this->userModel->update((int)$user['id'], ['managed_by_admin_id' => $userAdminId]);

            $updatedUser = $this->userModel->find((int)$user['id']);
            $assignedAdmin = $this->userModel
                ->select(['id', 'email', 'full_name', 'role', 'profile_img'])
                ->find($userAdminId);

            return sendApiResponse([
                'user_id' => (int)$updatedUser['id'],
                'managed_by_admin_id' => (int)$updatedUser['managed_by_admin_id'],
                'assigned_user_admin' => $assignedAdmin,
            ], 'User assigned to user admin successfully', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to assign user admin.');
        }
    }

    /**
     * User: Unassign own user_admin
     * DELETE /api/users/assignment
     */
    public function unassignMyUserAdmin(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if ($this->is_acting_as_user) {
                return sendApiResponse(null, 'Delegated requests cannot change assignment via self endpoint', 403);
            }

            $user = $this->userModel->find($this->current_user_id);
            if (!$user) {
                return sendApiResponse(null, 'User not found', 404);
            }

            if (($user['role'] ?? 'user') !== 'user') {
                return sendApiResponse(null, 'Only users with role user can self-unassign from user admin', 403);
            }

            if (empty($user['managed_by_admin_id'])) {
                return sendApiResponse(null, 'User is already unassigned', 200);
            }

            $this->userModel->update((int)$user['id'], ['managed_by_admin_id' => null]);
            $updated = $this->userModel->getUserPublicDetails((int)$user['id']);

            return sendApiResponse([
                'user' => $updated,
                'unassigned' => true,
            ], 'User unassigned from user admin successfully', 200);
        } catch (\Throwable $e) {
            logError($e);
            return $this->failServerError('Failed to unassign from user admin.');
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

            if ($this->is_acting_as_user) {
                return sendApiResponse(null, 'Use admin managed profile endpoints to access delegated user profiles', 403);
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

            if ($this->is_acting_as_user) {
                return sendApiResponse(null, 'Use admin managed profile endpoints to update delegated user profiles', 403);
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

            if ($this->is_acting_as_user) {
                return sendApiResponse(null, 'Delegated requests cannot delete account via self endpoint', 403);
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

            if ($this->is_acting_as_user) {
                return sendApiResponse(null, 'Use admin managed profile image endpoint for delegated users', 403);
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
