<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\StepSessionModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * StepsController
 * 
 * Handles pedometer step sessions and tracking preferences
 * 
 * @package HealthSphere
 */
class StepsController extends BaseController
{
    protected $stepSessionModel;
    protected $userModel;

    public function __construct()
    {
        $this->stepSessionModel = new StepSessionModel();
        $this->userModel = new UserModel();
    }

    /**
     * Get all step sessions (paginated)
     * GET /api/steps/sessions
     */
    public function index(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $page = $this->request->getGet('page') ?? 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;

            $sessions = $this->stepSessionModel->getUserSessions($this->current_user_id, $limit, $offset);

            return sendApiResponse($sessions, 'Step sessions retrieved successfully');
        } catch (\Throwable $e) {
            log_message('error', 'Get step sessions error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve sessions', 500);
        }
    }

    /**
     * Get a single step session
     * GET /api/steps/sessions/:id
     */
    public function show($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $session = $this->stepSessionModel->where('user_id', $this->current_user_id)->find($id);

            if (!$session) {
                return sendApiResponse(null, 'Session not found', 404);
            }

            return sendApiResponse($session, 'Session retrieved successfully');
        } catch (\Throwable $e) {
            log_message('error', 'Get step session error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve session', 500);
        }
    }

    /**
     * Save a completed step session
     * POST /api/steps/sessions
     */
    public function create(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $rules = [
                'steps'            => 'required|integer',
                'distanceKm'       => 'required|numeric',
                'durationSeconds'  => 'required|integer',
                'startedAt'        => 'required',
            ];

            if (!$this->validate($rules)) {
                return sendApiResponse(null, 'Validation failed', 400, $this->validator->getErrors());
            }

            $steps = (int)$this->request->getVar('steps');
            
            // Calculate calories if not provided (rough estimate: 0.04 kcal per step)
            $calories = $this->request->getVar('calories') ?? ($steps * 0.04);

            $data = [
                'user_id'          => $this->current_user_id,
                'steps'            => $steps,
                'distance_km'      => $this->request->getVar('distanceKm'),
                'duration_seconds' => $this->request->getVar('durationSeconds'),
                'calories'         => $calories,
                'started_at'       => $this->request->getVar('startedAt'),
            ];

            $id = $this->stepSessionModel->insert($data);

            if (!$id) {
                log_message('error', 'Step session save failed: ' . json_encode($this->stepSessionModel->errors()));
                return sendApiResponse(null, 'Failed to save session', 500);
            }

            return sendApiResponse(['id' => $id], 'Session saved successfully', 201);
        } catch (\Throwable $e) {
            log_message('error', 'Create step session error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to save session', 500);
        }
    }

    /**
     * Delete a step session
     * DELETE /api/steps/sessions/:id
     */
    public function delete($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $session = $this->stepSessionModel->where('user_id', $this->current_user_id)->find($id);

            if (!$session) {
                return sendApiResponse(null, 'Session not found', 404);
            }

            $this->stepSessionModel->delete($id);

            return sendApiResponse(null, 'Session deleted successfully');
        } catch (\Throwable $e) {
            log_message('error', 'Delete step session error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to delete session', 500);
        }
    }

    /**
     * Check if tracking is enabled and get daily goal
     * GET /api/steps/tracking/status
     */
    public function getStatus(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $user = $this->userModel->find($this->current_user_id);

            return sendApiResponse([
                'enabled' => (bool)($user['step_tracking_enabled'] ?? false),
                'goal'    => (int)($user['daily_step_goal'] ?? 10000),
            ], 'Tracking status retrieved successfully');
        } catch (\Throwable $e) {
            log_message('error', 'Get tracking status error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve status', 500);
        }
    }

    /**
     * Toggle tracking on/off and update daily goal
     * PATCH /api/steps/tracking
     */
    public function toggleTracking(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $json = $this->request->getJSON(true);
            $enabled = $json['enabled'] ?? null;
            $goal = $json['goal'] ?? null;

            $updateData = [];
            if ($enabled !== null) $updateData['step_tracking_enabled'] = $enabled ? 1 : 0;
            if ($goal !== null) $updateData['daily_step_goal'] = (int)$goal;

            if (empty($updateData)) {
                return sendApiResponse(null, 'No data provided for update', 400);
            }

            $this->userModel->update($this->current_user_id, $updateData);

            return sendApiResponse($updateData, 'Tracking updated successfully');
        } catch (\Throwable $e) {
            log_message('error', 'Toggle tracking error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to update tracking', 500);
        }
    }
}
