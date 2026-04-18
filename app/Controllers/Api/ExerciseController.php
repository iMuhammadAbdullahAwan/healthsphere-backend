<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ExerciseLogModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ExerciseController
 * 
 * Handles exercise logging and history
 * 
 * @package HealthSphere
 */
class ExerciseController extends BaseController
{
    protected $exerciseLogModel;

    public function __construct()
    {
        $this->exerciseLogModel = new ExerciseLogModel();
    }

    /**
     * Get all exercise logs with search and pagination
     * GET /api/exercises?search=push&page=1&limit=20
     */
    public function index(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $search = $this->request->getVar('search');
            $page = $this->request->getVar('page') ?? 1;
            $limit = $this->request->getVar('limit') ?? 20;
            $offset = ($page - 1) * $limit;

            $query = $this->exerciseLogModel->where('user_id', $this->current_user_id);

            if ($search) {
                $query->like('exercise_name', $search);
            }

            $total = (clone $query)->countAllResults();
            $logs = $query->orderBy('performed_at', 'DESC')->findAll($limit, $offset);

            return sendApiResponse([
                'logs' => $logs,
                'pagination' => [
                    'total' => $total,
                    'page' => (int)$page,
                    'limit' => (int)$limit,
                    'pages' => ceil($total / $limit)
                ]
            ], 'Exercise logs retrieved successfully');
        } catch (\Throwable $e) {
            log_message('error', 'Get exercise logs error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve exercise logs', 500);
        }
    }

    /**
     * Create a new exercise log
     * POST /api/exercises
     */
    public function create(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $rules = [
                'exercise_name'    => 'required|string|max_length[255]',
                'count'            => 'permit_empty|string',
                'duration_minutes' => 'permit_empty|integer',
                'performed_at'     => 'required',
            ];

            if (!$this->validate($rules)) {
                return sendApiResponse(null, 'Validation failed', 400, $this->validator->getErrors());
            }

            $data = [
                'user_id'          => $this->current_user_id,
                'exercise_name'    => $this->request->getVar('exercise_name'),
                'count'            => $this->request->getVar('count'),
                'duration_minutes' => $this->request->getVar('duration_minutes'),
                'calories_burned'  => $this->request->getVar('calories_burned'),
                'performed_at'     => $this->request->getVar('performed_at'),
                'notes'            => $this->request->getVar('notes'),
            ];

            $id = $this->exerciseLogModel->insert($data);

            if (!$id) {
                log_message('error', 'Exercise log save failed: ' . json_encode($this->exerciseLogModel->errors()));
                return sendApiResponse(null, 'Failed to save exercise log', 500);
            }

            $createdLog = $this->exerciseLogModel->find($id);

            return sendApiResponse($createdLog, 'Exercise logged successfully', 201);
        } catch (\Throwable $e) {
            log_message('error', 'Create exercise log error: ' . $e->getMessage());
            return sendApiResponse(null, 'An error occurred while logging the exercise', 500);
        }
    }

    /**
     * Delete an exercise log
     * DELETE /api/exercises/:id
     */
    public function delete($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $log = $this->exerciseLogModel->where('user_id', $this->current_user_id)->find($id);

            if (!$log) {
                return sendApiResponse(null, 'Exercise log not found', 404);
            }

            $this->exerciseLogModel->delete($id);

            return sendApiResponse(null, 'Exercise log deleted successfully');
        } catch (\Throwable $e) {
            log_message('error', 'Delete exercise log error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to delete exercise log', 500);
        }
    }
}
