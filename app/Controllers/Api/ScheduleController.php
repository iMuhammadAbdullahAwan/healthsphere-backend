<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ScheduleModel;
use App\Models\ScheduleLogModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Schedule Controller
 *
 * Handles schedule management operations including:
 * - Creating and managing various types of schedules (medicine, food, water, running, sleep, custom)
 * - Retrieving today's and upcoming schedules
 * - Schedule status management
 *
 * @package    HealthSphere
 * @subpackage Controllers\Api
 * @category   Schedule
 * @version    1.0.0
 */
class ScheduleController extends BaseController
{
    /**
     * Schedule model instance
     *
     * @var ScheduleModel
     */
    protected $scheduleModel;

    /**
     * Schedule log model instance
     *
     * @var ScheduleLogModel
     */
    protected $scheduleLogModel;

    /**
     * Constructor - Initialize dependencies
     */
    public function __construct()
    {
        $this->scheduleModel = new ScheduleModel();
        $this->scheduleLogModel = new ScheduleLogModel();
    }

    /**
     * Get all schedules for authenticated user
     * GET /api/schedules
     *
     * @return ResponseInterface
     */
    public function index(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            // Check for filter parameter
            $filter = $this->request->getGet('filter');

            // Handle special filters
            switch ($filter) {
                case 'today':
                    $schedules = $this->scheduleModel->getTodaySchedules($this->current_user_id);
                    return sendApiResponse($schedules, 'Today\'s schedules retrieved successfully', 200);

                case 'upcoming':
                    $days = $this->request->getGet('days') ?? 7;
                    $schedules = $this->scheduleModel->getUpcomingSchedules($this->current_user_id, (int)$days);
                    return sendApiResponse($schedules, 'Upcoming schedules retrieved successfully', 200);

                case 'history':
                    $historyFilters = [
                        'schedule_type' => $this->request->getGet('type'),
                        'status'        => $this->request->getGet('status'),
                        'start_date'    => $this->request->getGet('start_date'),
                        'end_date'      => $this->request->getGet('end_date'),
                    ];
                    $history = $this->scheduleLogModel->getUserHistory($this->current_user_id, $historyFilters);
                    return sendApiResponse($history, 'Schedule history retrieved successfully', 200);

                default:
                    // Get all schedules with filters
                    $filters = [
                        'schedule_type' => $this->request->getGet('type'),
                        'status'        => $this->request->getGet('status'),
                        'start_date'    => $this->request->getGet('start_date'),
                        'end_date'      => $this->request->getGet('end_date'),
                    ];
                    $schedules = $this->scheduleModel->getUserSchedules($this->current_user_id, $filters);
                    return sendApiResponse($schedules, 'Schedules retrieved successfully', 200);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Get schedules error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve schedules', 500);
        }
    }

    /**
     * Get today's schedules
     * GET /api/schedules/today
     *
     * @return ResponseInterface
     */
    public function today(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $schedules = $this->scheduleModel->getTodaySchedules($this->current_user_id);

            return sendApiResponse($schedules, 'Today\'s schedules retrieved successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Get today schedules error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve today\'s schedules', 500);
        }
    }

    /**
     * Get upcoming schedules
     * GET /api/schedules/upcoming
     *
     * @return ResponseInterface
     */
    public function upcoming(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $days = $this->request->getGet('days') ?? 7;

            $schedules = $this->scheduleModel->getUpcomingSchedules($this->current_user_id, (int)$days);

            return sendApiResponse($schedules, 'Upcoming schedules retrieved successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Get upcoming schedules error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve upcoming schedules', 500);
        }
    }

    /**
     * Get schedule statistics
     * GET /api/schedules/stats?type=completion for completion stats
     *
     * @return ResponseInterface
     */
    public function stats(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            // Check if completion stats requested
            $type = $this->request->getGet('type');

            if ($type === 'completion') {
                $scheduleType = $this->request->getGet('schedule_type');
                $startDate = $this->request->getGet('start_date');
                $endDate = $this->request->getGet('end_date');

                $stats = $this->scheduleLogModel->getCompletionStats(
                    $this->current_user_id,
                    $scheduleType,
                    $startDate,
                    $endDate
                );

                return sendApiResponse($stats, 'Completion statistics retrieved successfully', 200);
            }

            // Default: return count by type
            $stats = $this->scheduleModel->countByType($this->current_user_id);

            return sendApiResponse($stats, 'Schedule statistics retrieved successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Get schedule stats error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve schedule statistics', 500);
        }
    }

    /**
     * Get a specific schedule
     * GET /api/schedules/{id}
     *
     * @param int|null $id Schedule ID
     * @return ResponseInterface
     */
    public function show($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$id) {
                return sendApiResponse(null, 'Schedule ID is required', 400);
            }

            $schedule = $this->scheduleModel->getUserSchedule((int)$id, $this->current_user_id);

            if (!$schedule) {
                return sendApiResponse(null, 'Schedule not found', 404);
            }

            return sendApiResponse($schedule, 'Schedule retrieved successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Get schedule error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve schedule', 500);
        }
    }

    /**
     * Create a new schedule
     * POST /api/schedules
     *
     * @return ResponseInterface
     */
    public function create(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $data = $this->request->getJSON(true);

            if (!$data) {
                return sendApiResponse(null, 'Invalid JSON data', 400);
            }

            // Add user_id to data
            $data['user_id'] = $this->current_user_id;

            // Set defaults
            $data['status'] = $data['status'] ?? 'active';
            $data['reminder_enabled'] = $data['reminder_enabled'] ?? true;
            $data['reminder_mode'] = $data['reminder_mode'] ?? 'notification';

            // Validate required fields based on schedule type
            $validationError = $this->validateTypeSpecificData($data);
            if ($validationError) {
                return sendApiResponse(null, $validationError, 400);
            }

            // Insert schedule
            $scheduleId = $this->scheduleModel->insert($data);

            if (!$scheduleId) {
                $errors = $this->scheduleModel->errors();
                $errorMessage = !empty($errors) ? implode(', ', $errors) : 'Failed to create schedule';
                return sendApiResponse(null, $errorMessage, 400);
            }

            // Get the created schedule
            $schedule = $this->scheduleModel->find($scheduleId);

            return sendApiResponse($schedule, 'Schedule created successfully', 201);
        } catch (\Throwable $e) {
            log_message('error', 'Create schedule error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to create schedule', 500);
        }
    }

    /**
     * Update a schedule
     * PUT /api/schedules/{id}
     *
     * @param int|null $id Schedule ID
     * @return ResponseInterface
     */
    public function update($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$id) {
                return sendApiResponse(null, 'Schedule ID is required', 400);
            }

            // Check if schedule exists and belongs to user
            $existingSchedule = $this->scheduleModel->getUserSchedule((int)$id, $this->current_user_id);
            if (!$existingSchedule) {
                return sendApiResponse(null, 'Schedule not found', 404);
            }

            $data = $this->request->getJSON(true);
            if (!$data) {
                return sendApiResponse(null, 'Invalid JSON data', 400);
            }

            // Prevent changing user_id
            unset($data['user_id']);

            // Validate type-specific data if schedule_type is being changed
            if (isset($data['schedule_type'])) {
                $validationError = $this->validateTypeSpecificData($data);
                if ($validationError) {
                    return sendApiResponse(null, $validationError, 400);
                }
            }

            // Update schedule
            $updated = $this->scheduleModel->update($id, $data);

            if (!$updated) {
                $errors = $this->scheduleModel->errors();
                $errorMessage = !empty($errors) ? implode(', ', $errors) : 'Failed to update schedule';
                return sendApiResponse(null, $errorMessage, 400);
            }

            // Get updated schedule
            $schedule = $this->scheduleModel->find($id);

            return sendApiResponse($schedule, 'Schedule updated successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Update schedule error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to update schedule', 500);
        }
    }

    /**
     * Update schedule status (pause, resume, complete)
     * PATCH /api/schedules/{id}/status
     *
     * @param int|null $id Schedule ID
     * @return ResponseInterface
     */
    public function updateStatus($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$id) {
                return sendApiResponse(null, 'Schedule ID is required', 400);
            }

            $data = $this->request->getJSON(true);

            if (!isset($data['status'])) {
                return sendApiResponse(null, 'Status is required', 400);
            }

            $updated = $this->scheduleModel->updateStatus((int)$id, $this->current_user_id, $data['status']);

            if (!$updated) {
                return sendApiResponse(null, 'Failed to update status or schedule not found', 400);
            }

            $schedule = $this->scheduleModel->find($id);

            return sendApiResponse($schedule, 'Schedule status updated successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Update schedule status error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to update schedule status', 500);
        }
    }

    /**
     * Delete a schedule
     * DELETE /api/schedules/{id}
     *
     * @param int|null $id Schedule ID
     * @return ResponseInterface
     */
    public function delete($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$id) {
                return sendApiResponse(null, 'Schedule ID is required', 400);
            }

            $deleted = $this->scheduleModel->deleteUserSchedule((int)$id, $this->current_user_id);

            if (!$deleted) {
                return sendApiResponse(null, 'Schedule not found or already deleted', 404);
            }

            return sendApiResponse(null, 'Schedule deleted successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Delete schedule error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to delete schedule', 500);
        }
    }

    /**
     * Validate type-specific data based on schedule_type
     */
    private function validateTypeSpecificData(array $data)
    {
        $scheduleType = $data['schedule_type'] ?? null;

        if (!$scheduleType) {
            return 'Schedule type is required';
        }

        switch ($scheduleType) {
            case 'medicine':
                if (empty($data['medicine_details']['medicine_name'])) {
                    return 'Medicine name is required for medicine schedules';
                }
                break;

            case 'food':
                if (empty($data['food_details']['meal_type'])) {
                    return 'Meal type is required for food schedules';
                }
                if (!in_array($data['food_details']['meal_type'], ['breakfast', 'lunch', 'dinner', 'snack'])) {
                    return 'Invalid meal type';
                }
                break;

            case 'running':
                if (empty($data['running_details']['activity_type'])) {
                    return 'Activity type is required for running schedules';
                }
                if (!in_array($data['running_details']['activity_type'], ['walk', 'jog', 'run'])) {
                    return 'Invalid activity type';
                }
                break;

            case 'sleep':
                if (empty($data['sleep_details']['sleep_time']) || empty($data['sleep_details']['wake_time'])) {
                    return 'Sleep time and wake time are required for sleep schedules';
                }
                break;

            case 'custom':
                if (empty($data['custom_details']['label'])) {
                    return 'Label is required for custom schedules';
                }
                break;

            case 'water':
                // Water details are optional
                break;

            default:
                return 'Invalid schedule type';
        }

        return null; // No validation error
    }

    /**
     * Get completion history/logs for a schedule
     * GET /api/schedules/{id}/logs
     *
     * @param int|null $id Schedule ID
     * @return ResponseInterface
     */
    public function getLogs($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$id) {
                return sendApiResponse(null, 'Schedule ID is required', 400);
            }

            // Verify schedule belongs to user
            $schedule = $this->scheduleModel->getUserSchedule((int)$id, $this->current_user_id);
            if (!$schedule) {
                return sendApiResponse(null, 'Schedule not found', 404);
            }

            $filters = [
                'status'     => $this->request->getGet('status'),
                'start_date' => $this->request->getGet('start_date'),
                'end_date'   => $this->request->getGet('end_date'),
            ];

            $logs = $this->scheduleLogModel->getScheduleLogs((int)$id, $this->current_user_id, $filters);

            return sendApiResponse($logs, 'Schedule logs retrieved successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Get schedule logs error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve schedule logs', 500);
        }
    }

    /**
     * Mark a schedule occurrence as completed
     * POST /api/schedules/logs/{logId}/complete
     *
     * @param int|null $logId Log ID
     * @return ResponseInterface
     */
    public function completeLog($logId = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$logId) {
                return sendApiResponse(null, 'Log ID is required', 400);
            }

            $data = $this->request->getJSON(true);
            $notes = $data['notes'] ?? null;

            $updated = $this->scheduleLogModel->markCompleted((int)$logId, $this->current_user_id, $notes);

            if (!$updated) {
                return sendApiResponse(null, 'Failed to mark as completed or log not found', 400);
            }

            $log = $this->scheduleLogModel->find($logId);

            return sendApiResponse($log, 'Schedule marked as completed', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Complete schedule log error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to mark schedule as completed', 500);
        }
    }

    /**
     * Get user's completion history across all schedules
     * GET /api/schedules/history
     *
     * @return ResponseInterface
     */
    public function history(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $filters = [
                'schedule_type' => $this->request->getGet('type'),
                'status'        => $this->request->getGet('status'),
                'start_date'    => $this->request->getGet('start_date'),
                'end_date'      => $this->request->getGet('end_date'),
            ];

            $history = $this->scheduleLogModel->getUserHistory($this->current_user_id, $filters);

            return sendApiResponse($history, 'Schedule history retrieved successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Get schedule history error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve schedule history', 500);
        }
    }

    /**
     * Get completion statistics
     * GET /api/schedules/completion-stats
     *
     * @return ResponseInterface
     */
    public function completionStats(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $scheduleType = $this->request->getGet('type');
            $startDate = $this->request->getGet('start_date');
            $endDate = $this->request->getGet('end_date');

            $stats = $this->scheduleLogModel->getCompletionStats(
                $this->current_user_id,
                $scheduleType,
                $startDate,
                $endDate
            );

            return sendApiResponse($stats, 'Completion statistics retrieved successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Get completion stats error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve completion statistics', 500);
        }
    }
}
