<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 * 
 * @package HealthSphere
 * @version 1.0.0
 */
abstract class BaseController extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var array<string>
     */
    protected $helpers = ['general', 'response'];

    /**
     * Current authenticated user data
     *
     * @var array|null
     */
    protected $current_user = null;

    /**
     * Whether user is authenticated
     *
     * @var bool
     */
    protected $logged_in = false;

    /**
     * Current authenticated user ID
     *
     * @var int|null
     */
    protected $current_user_id = null;

    /**
     * Current authenticated user role
     *
     * @var string|null
     */
    protected $current_user_role = null;

    /**
     * Authenticated actor user ID (real account from token)
     *
     * @var int|null
     */
    protected $actor_user_id = null;

    /**
     * Authenticated actor role (role from token owner)
     *
     * @var string|null
     */
    protected $actor_user_role = null;

    /**
     * Whether request is delegated to another user context
     *
     * @var bool
     */
    protected $is_acting_as_user = false;

    /**
     * Check whether the current user has any of the provided roles
     *
     * @param array|string $roles
     * @return bool
     */
    protected function hasAnyRole($roles): bool
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }

        if (!$this->current_user_role) {
            return false;
        }

        return in_array($this->current_user_role, $roles, true);
    }

    /**
     * Initialize controller with request, response, and logger
     * Sets up authenticated user context if available
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param LoggerInterface   $logger
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        $this->setupAuthenticatedUser();
    }

    /**
     * Setup authenticated user data from token filter
     *
     * @return void
     */
    protected function setupAuthenticatedUser(): void
    {
        $userId = $this->request->getPost('current_user_id');
        $actorUserId = $this->request->getPost('actor_user_id');
        $actorRole = $this->request->getPost('actor_role');
        $isActingAs = (int)($this->request->getPost('is_acting_as_user') ?? 0) === 1;

        if (!$userId) {
            return;
        }

        try {
            $this->current_user_id = (int) $userId;
            $this->actor_user_id = $actorUserId ? (int)$actorUserId : $this->current_user_id;
            $this->is_acting_as_user = $isActingAs;

            $userModel = new \App\Models\UserModel();
            $user = $userModel->getUserPrivateDetails($this->current_user_id);

            if ($user) {
                $this->current_user = $user;
                $this->current_user_role = $user['role'] ?? 'user';
                $this->actor_user_role = $actorRole ?: $this->current_user_role;
                $this->logged_in = true;
            }
        } catch (\Exception $e) {
            log_message('error', 'Failed to setup authenticated user: ' . $e->getMessage());
        }
    }
}
