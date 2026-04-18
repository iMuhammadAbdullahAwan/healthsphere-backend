<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ScheduleModel;
use App\Models\ScheduleLogModel;
use App\Models\ScheduleHistoryModel;
use App\Libraries\NotificationService;
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
     * Schedule history model
     *
     * @var ScheduleHistoryModel
     */
    protected $scheduleHistoryModel;

    /**
     * Constructor - Initialize dependencies
     */
    public function __construct()
    {
        $this->scheduleModel = new ScheduleModel();
        $this->scheduleLogModel = new ScheduleLogModel();
        $this->scheduleHistoryModel = new ScheduleHistoryModel();
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

            // Pagination params
            $page = (int) ($this->request->getGet('page') ?? 1);
            $perPage = (int) ($this->request->getGet('per_page') ?? 20);

            // Check for filter parameter
            $filter = $this->request->getGet('filter');

            // Handle special filters
            switch ($filter) {
                case 'today':
                    $results = $this->scheduleModel->getTodaySchedules($this->current_user_id, $page, $perPage);
                    return sendApiResponse([
                        'schedules' => $results['data'],
                        'pagination' => [
                            'total' => $results['meta']['total'],
                            'page' => (int)$results['meta']['page'],
                            'limit' => (int)$results['meta']['per_page'],
                            'pages' => $results['meta']['total_pages']
                        ]
                    ], 'Today\'s schedules retrieved successfully', 200);

                case 'upcoming':
                    $days = $this->request->getGet('days') ?? 7;
                    $results = $this->scheduleModel->getUpcomingSchedules($this->current_user_id, (int)$days, $page, $perPage);
                    return sendApiResponse([
                        'schedules' => $results['data'],
                        'pagination' => [
                            'total' => $results['meta']['total'],
                            'page' => (int)$results['meta']['page'],
                            'limit' => (int)$results['meta']['per_page'],
                            'pages' => $results['meta']['total_pages']
                        ]
                    ], 'Upcoming schedules retrieved successfully', 200);

                case 'history':
                    $historyFilters = [
                        'schedule_type' => $this->request->getGet('type'),
                        'status'        => $this->request->getGet('status'),
                        'start_date'    => $this->request->getGet('start_date'),
                        'end_date'      => $this->request->getGet('end_date'),
                        'q'             => $this->request->getGet('search'), // search in notes/snapshot
                    ];
                    $results = $this->scheduleHistoryModel->getUserHistory($this->current_user_id, $historyFilters, $page, $perPage);
                    return sendApiResponse([
                        'history' => $results['data'],
                        'pagination' => [
                            'total' => $results['meta']['total'],
                            'page' => (int)$results['meta']['page'],
                            'limit' => (int)$results['meta']['per_page'],
                            'pages' => $results['meta']['total_pages']
                        ]
                    ], 'Schedule history retrieved successfully', 200);

                default:
                    // Get all schedules with filters
                    $filters = [
                        'schedule_type' => $this->request->getGet('type'),
                        'status'        => $this->request->getGet('status'),
                        'start_date'    => $this->request->getGet('start_date'),
                        'end_date'      => $this->request->getGet('end_date'),
                        'q'             => $this->request->getGet('search') ?? $this->request->getGet('q'), // support both
                    ];
                    $results = $this->scheduleModel->getUserSchedules($this->current_user_id, $filters, $page, $perPage);
                    return sendApiResponse([
                        'schedules' => $results['data'],
                        'pagination' => [
                            'total' => $results['meta']['total'],
                            'page' => (int)$results['meta']['page'],
                            'limit' => (int)$results['meta']['per_page'],
                            'pages' => $results['meta']['total_pages']
                        ]
                    ], 'Schedules retrieved successfully', 200);
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

            $page = (int) ($this->request->getGet('page') ?? 1);
            $perPage = (int) ($this->request->getGet('per_page') ?? 20);

            $schedules = $this->scheduleModel->getTodaySchedules($this->current_user_id, $page, $perPage);

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
            $page = (int) ($this->request->getGet('page') ?? 1);
            $perPage = (int) ($this->request->getGet('per_page') ?? 20);

            $schedules = $this->scheduleModel->getUpcomingSchedules($this->current_user_id, (int)$days, $page, $perPage);

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

            // Support both JSON bodies and multipart/form-data uploads.
            // For multipart uploads, the client should send a `payload` form field containing the JSON body,
            // and an `image` file field for the image file.
            $data = null;
            $file = $this->request->getFile('image');

            if ($this->request->getPost() || ($file && $file->isValid())) {
                $payload = $this->request->getPost('payload') ?? null;
                if ($payload) {
                    $data = json_decode($payload, true);
                    if (!is_array($data)) {
                        return sendApiResponse(null, 'Invalid JSON in payload field', 400);
                    }
                } else {
                    $data = $this->request->getPost();
                }
            } else {
                $data = $this->request->getJSON(true);
            }

            if (!$data || !is_array($data)) {
                return sendApiResponse(null, 'Invalid request data', 400);
            }

            // Add user_id to data
            $data['user_id'] = $this->current_user_id;

            // Set defaults
            $data['status'] = $data['status'] ?? 'active';
            $data['reminder_enabled'] = $data['reminder_enabled'] ?? true;
            $data['reminder_mode'] = $data['reminder_mode'] ?? 'notification';

            // Validate required fields based on schedule type
            // If any detail fields were provided as JSON strings in form-data, decode them into arrays
            $detailFields = [
                'medicine_details',
                'food_details',
                'water_details',
                'running_details',
                'sleep_details',
                'custom_details'
            ];
            foreach ($detailFields as $df) {
                if (isset($data[$df]) && is_string($data[$df])) {
                    $decoded = json_decode($data[$df], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $data[$df] = $decoded;
                    } else {
                        return sendApiResponse(null, "Invalid JSON for {$df}", 400);
                    }
                }
            }

            $validationError = $this->validateTypeSpecificData($data);
            if ($validationError) {
                return sendApiResponse(null, $validationError, 400);
            }

            // Insert schedule
            // Handle uploaded image file (multipart/form-data). If a file is provided move it to public/uploads/schedules/
            if ($file && $file->isValid() && !$file->hasMoved()) {
                $uploadPath = rtrim(FCPATH, '\\/') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'schedules';
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                $newName = $file->getRandomName();
                try {
                    $file->move($uploadPath, $newName);
                    // Store a web-servable relative path so frontend can request it: uploads/schedules/<name>
                    $data['image'] = 'uploads/schedules/' . $newName;
                } catch (\Throwable $e) {
                    log_message('error', 'Schedule image upload failed: ' . $e->getMessage());
                    return sendApiResponse(null, 'Failed to upload image', 500);
                }
            } else {
                // Allow image field (optional) if present in JSON or form-data; normalize empty to null
                if (isset($data['image']) && empty($data['image'])) {
                    $data['image'] = null;
                }
            }

            $scheduleId = $this->scheduleModel->insert($data);

            if (!$scheduleId) {
                $errors = $this->scheduleModel->errors();
                $errorMessage = !empty($errors) ? implode(', ', $errors) : 'Failed to create schedule';
                return sendApiResponse(null, $errorMessage, 400);
            }

            // Get the created schedule
            $schedule = $this->scheduleModel->find($scheduleId);

            // Send real-time notification about the new schedule
            try {
                $notificationService = new NotificationService();
                $notificationService->createNotification([
                    'user_ids'   => $this->current_user_id,
                    'created_by' => $this->current_user_id,
                    'message'    => "New schedule created: {$schedule['title']}",
                    'type'       => 'schedule_created',
                    'link'       => '/schedules/' . $schedule['id'],
                    'related_id' => $schedule['id'],
                ]);
            } catch (\Throwable $e) {
                log_message('error', 'Failed to send schedule created notification: ' . $e->getMessage());
            }
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

            // Support both JSON bodies and multipart/form-data uploads for updates as well.
            $data = null;
            $file = $this->request->getFile('image');

            if ($this->request->getPost() || ($file && $file->isValid())) {
                $payload = $this->request->getPost('payload') ?? null;
                if ($payload) {
                    $data = json_decode($payload, true);
                    if (!is_array($data)) {
                        return sendApiResponse(null, 'Invalid JSON in payload field', 400);
                    }
                } else {
                    $data = $this->request->getPost();
                }
            } else {
                $data = $this->request->getJSON(true);
            }

            if (!$data || !is_array($data)) {
                return sendApiResponse(null, 'Invalid request data', 400);
            }

            // Prevent changing user_id
            unset($data['user_id']);

            // Handle uploaded image file for update (multipart/form-data)
            if ($file && $file->isValid() && !$file->hasMoved()) {
                $uploadPath = rtrim(FCPATH, '\\/') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'schedules';
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                $newName = $file->getRandomName();
                try {
                    $file->move($uploadPath, $newName);
                    $data['image'] = 'uploads/schedules/' . $newName;
                } catch (\Throwable $e) {
                    log_message('error', 'Schedule image upload failed on update: ' . $e->getMessage());
                    return sendApiResponse(null, 'Failed to upload image', 500);
                }
            }

            // Validate type-specific data if schedule_type is being changed
            if (isset($data['schedule_type'])) {
                $validationError = $this->validateTypeSpecificData($data);
                if ($validationError) {
                    return sendApiResponse(null, $validationError, 400);
                }
            }

            // If any detail fields were provided as JSON strings in form-data for update, decode them
            $detailFields = [
                'medicine_details',
                'food_details',
                'water_details',
                'running_details',
                'sleep_details',
                'custom_details'
            ];
            foreach ($detailFields as $df) {
                if (isset($data[$df]) && is_string($data[$df])) {
                    $decoded = json_decode($data[$df], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $data[$df] = $decoded;
                    } else {
                        return sendApiResponse(null, "Invalid JSON for {$df}", 400);
                    }
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
     * Cancel a schedule or a single occurrence
     * POST /api/schedules/{id}/cancel
     * body: { scope: 'one'|'all', date: 'YYYY-MM-DD' }
     */
    public function cancel($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$id) {
                return sendApiResponse(null, 'Schedule ID is required', 400);
            }

            $data = $this->request->getJSON(true) ?? [];
            $scope = $data['scope'] ?? 'all';

            if ($scope === 'one') {
                $date = $data['date'] ?? null;
                if (!$date) {
                    return sendApiResponse(null, 'Date is required when scope=one', 400);
                }

                // find the log for that date
                $log = $this->scheduleLogModel->where(['schedule_id' => (int)$id, 'user_id' => $this->current_user_id])
                    ->where('DATE(scheduled_for)', $date)
                    ->first();

                if (!$log) {
                    $hist = $this->scheduleHistoryModel->where(['schedule_id' => (int)$id, 'user_id' => $this->current_user_id])
                        ->where('DATE(scheduled_for)', $date)
                        ->where('status', 'canceled')
                        ->first();

                    if ($hist) {
                        return sendApiResponse($hist, 'Occurrence already canceled (archived)', 200);
                    }

                    return sendApiResponse(null, 'Occurrence not found', 404);
                }

                // Archive the single log into schedule_history
                $schedule = $this->scheduleModel->find($log['schedule_id']);
                $historyData = [
                    'original_log_id' => $log['id'],
                    'schedule_id' => $log['schedule_id'],
                    'user_id' => $log['user_id'],
                    'scheduled_for' => $log['scheduled_for'],
                    'status' => 'canceled',
                    'notes' => $log['notes'] ?? null,
                    'notified_at' => $log['notified_at'] ?? null,
                    'completed_at' => $log['completed_at'] ?? null,
                    'schedule_snapshot' => $schedule ? json_encode($schedule) : null,
                    'archived_at' => date('Y-m-d H:i:s'),
                ];

                $this->scheduleHistoryModel->insert($historyData);
                $histId = $this->scheduleHistoryModel->insertID();

                // delete original log
                $this->scheduleLogModel->delete($log['id']);

                $archived = $this->scheduleHistoryModel->find($histId);
                return sendApiResponse($archived, 'Occurrence canceled and archived', 200);
            }

            // scope = all => cancel entire schedule
            $updated = $this->scheduleModel->updateStatus((int)$id, $this->current_user_id, 'canceled');
            if (!$updated) {
                return sendApiResponse(null, 'Failed to cancel schedule or not found', 400);
            }

            return sendApiResponse(null, 'Schedule canceled successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Cancel schedule error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to cancel schedule', 500);
        }
    }

    /**
     * Un-cancel (redo) a schedule or a single occurrence
     * POST /api/schedules/{id}/uncancel
     * body: { scope: 'one'|'all', date: 'YYYY-MM-DD' }
     */
    public function uncancel($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$id) {
                return sendApiResponse(null, 'Schedule ID is required', 400);
            }

            $data = $this->request->getJSON(true) ?? [];
            $scope = $data['scope'] ?? 'all';

            if ($scope === 'one') {
                $date = $data['date'] ?? null;
                if (!$date) {
                    return sendApiResponse(null, 'Date is required when scope=one', 400);
                }
                $log = $this->scheduleLogModel->where(['schedule_id' => (int)$id, 'user_id' => $this->current_user_id])
                    ->where('DATE(scheduled_for)', $date)
                    ->first();

                if ($log) {
                    // log exists; set status back to pending
                    $this->scheduleLogModel->update($log['id'], [
                        'status' => 'pending',
                        'notified_at' => null,
                        'completed_at' => null,
                    ]);

                    // Remove any matching archived canceled history for this occurrence
                    $histRows = $this->scheduleHistoryModel
                        ->where(['schedule_id' => (int)$id, 'user_id' => $this->current_user_id])
                        ->where('DATE(scheduled_for)', $date)
                        ->where('status', 'canceled')
                        ->findAll();
                    foreach ($histRows as $h) {
                        $this->scheduleHistoryModel->delete($h['id']);
                    }

                    $restored = $this->scheduleLogModel->find($log['id']);
                    return sendApiResponse($restored, 'Occurrence uncanceled', 200);
                }

                // Try to restore from history
                $hist = $this->scheduleHistoryModel->where(['schedule_id' => (int)$id, 'user_id' => $this->current_user_id])
                    ->where('DATE(scheduled_for)', $date)
                    ->where('status', 'canceled')
                    ->first();

                if (!$hist) {
                    return sendApiResponse(null, 'Occurrence not found', 404);
                }

                $insert = [
                    'schedule_id' => $hist['schedule_id'],
                    'user_id' => $hist['user_id'],
                    'scheduled_for' => $hist['scheduled_for'],
                    'status' => 'pending',
                    'notes' => null,
                    'notified_at' => null,
                ];

                $this->scheduleLogModel->insert($insert);
                $newId = $this->scheduleLogModel->insertID();

                // Remove matching archived canceled history for this occurrence
                $histRows = $this->scheduleHistoryModel
                    ->where(['schedule_id' => (int)$id, 'user_id' => $this->current_user_id])
                    ->where('DATE(scheduled_for)', $date)
                    ->where('status', 'canceled')
                    ->findAll();
                foreach ($histRows as $h) {
                    $this->scheduleHistoryModel->delete($h['id']);
                }

                $restored = $this->scheduleLogModel->find($newId);
                return sendApiResponse($restored, 'Occurrence uncanceled and restored', 200);
            }

            // scope = all => resume schedule
            $updated = $this->scheduleModel->updateStatus((int)$id, $this->current_user_id, 'active');
            if (!$updated) {
                return sendApiResponse(null, 'Failed to uncancel schedule or not found', 400);
            }

            // Restore archived canceled/completed occurrences for this schedule, then remove history rows.
            $histRows = $this->scheduleHistoryModel
                ->where(['schedule_id' => (int)$id, 'user_id' => $this->current_user_id])
                ->whereIn('status', ['canceled', 'completed'])
                ->findAll();

            foreach ($histRows as $h) {
                $existing = $this->scheduleLogModel
                    ->where('schedule_id', $h['schedule_id'])
                    ->where('user_id', $h['user_id'])
                    ->where('scheduled_for', $h['scheduled_for'])
                    ->first();

                if (!$existing) {
                    $this->scheduleLogModel->insert([
                        'schedule_id' => $h['schedule_id'],
                        'user_id' => $h['user_id'],
                        'scheduled_for' => $h['scheduled_for'],
                        'status' => 'pending',
                        'notes' => null,
                        'notified_at' => null,
                    ]);
                }

                $this->scheduleHistoryModel->delete($h['id']);
            }

            return sendApiResponse(null, 'Schedule uncanceled successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Uncancel schedule error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to uncancel schedule', 500);
        }
    }

    /**
     * Mark a schedule (one occurrence or whole schedule) as done/completed
     * POST /api/schedules/{id}/done
     * body: { scope: 'one'|'all', date: 'YYYY-MM-DD' }
     */
    public function done($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$id) {
                return sendApiResponse(null, 'Schedule ID is required', 400);
            }

            $data = $this->request->getJSON(true) ?? [];
            $scope = $data['scope'] ?? 'all';

            if ($scope === 'one') {
                $date = $data['date'] ?? null;
                if (!$date) {
                    return sendApiResponse(null, 'Date is required when scope=one', 400);
                }

                $log = $this->scheduleLogModel->where(['schedule_id' => (int)$id, 'user_id' => $this->current_user_id])
                    ->where('DATE(scheduled_for)', $date)
                    ->first();

                if (!$log) {
                    $hist = $this->scheduleHistoryModel->where(['schedule_id' => (int)$id, 'user_id' => $this->current_user_id])
                        ->where('DATE(scheduled_for)', $date)
                        ->where('status', 'completed')
                        ->first();

                    if ($hist) {
                        return sendApiResponse($hist, 'Occurrence already completed (archived)', 200);
                    }

                    return sendApiResponse(null, 'Occurrence not found', 404);
                }

                $schedule = $this->scheduleModel->find($log['schedule_id']);
                $historyData = [
                    'original_log_id' => $log['id'],
                    'schedule_id' => $log['schedule_id'],
                    'user_id' => $log['user_id'],
                    'scheduled_for' => $log['scheduled_for'],
                    'status' => 'completed',
                    'notes' => $log['notes'] ?? null,
                    'notified_at' => $log['notified_at'] ?? null,
                    'completed_at' => date('Y-m-d H:i:s'),
                    'schedule_snapshot' => $schedule ? json_encode($schedule) : null,
                    'archived_at' => date('Y-m-d H:i:s'),
                ];

                $this->scheduleHistoryModel->insert($historyData);
                $histId = $this->scheduleHistoryModel->insertID();

                $this->scheduleLogModel->delete($log['id']);

                $archived = $this->scheduleHistoryModel->find($histId);
                return sendApiResponse($archived, 'Occurrence marked done and archived', 200);
            }

            // scope = all => mark schedule completed
            $updated = $this->scheduleModel->updateStatus((int)$id, $this->current_user_id, 'completed');
            if (!$updated) {
                return sendApiResponse(null, 'Failed to mark schedule done or not found', 400);
            }

            return sendApiResponse(null, 'Schedule marked completed successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Done schedule error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to mark schedule done', 500);
        }
    }

    /**
     * Undo a done/completed schedule (one occurrence or whole schedule)
     * POST /api/schedules/{id}/undone
     * body: { scope: 'one'|'all', date: 'YYYY-MM-DD' }
     */
    public function undone($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$id) {
                return sendApiResponse(null, 'Schedule ID is required', 400);
            }

            $data = $this->request->getJSON(true) ?? [];
            $scope = $data['scope'] ?? 'all';

            if ($scope === 'one') {
                $date = $data['date'] ?? null;
                if (!$date) {
                    return sendApiResponse(null, 'Date is required when scope=one', 400);
                }

                $log = $this->scheduleLogModel->where(['schedule_id' => (int)$id, 'user_id' => $this->current_user_id])
                    ->where('DATE(scheduled_for)', $date)
                    ->first();

                if ($log) {
                    // If a log already exists, set it back to pending
                    $this->scheduleLogModel->update($log['id'], [
                        'status' => 'pending',
                        'completed_at' => null,
                        'notes' => null,
                        'notified_at' => null,
                    ]);

                    // Remove any matching archived completed history for this occurrence
                    $histRows = $this->scheduleHistoryModel
                        ->where(['schedule_id' => (int)$id, 'user_id' => $this->current_user_id])
                        ->where('DATE(scheduled_for)', $date)
                        ->where('status', 'completed')
                        ->findAll();
                    foreach ($histRows as $h) {
                        $this->scheduleHistoryModel->delete($h['id']);
                    }

                    $restored = $this->scheduleLogModel->find($log['id']);
                    return sendApiResponse($restored, 'Occurrence restored to pending', 200);
                }

                // Try to restore from history
                $hist = $this->scheduleHistoryModel->where(['schedule_id' => (int)$id, 'user_id' => $this->current_user_id])
                    ->where('DATE(scheduled_for)', $date)
                    ->where('status', 'completed')
                    ->first();

                if (!$hist) {
                    return sendApiResponse(null, 'Occurrence not found', 404);
                }

                $insert = [
                    'schedule_id' => $hist['schedule_id'],
                    'user_id' => $hist['user_id'],
                    'scheduled_for' => $hist['scheduled_for'],
                    'status' => 'pending',
                    'notes' => null,
                    'notified_at' => null,
                ];

                $this->scheduleLogModel->insert($insert);
                $newId = $this->scheduleLogModel->insertID();

                // Remove matching archived completed history for this occurrence
                $histRows = $this->scheduleHistoryModel
                    ->where(['schedule_id' => (int)$id, 'user_id' => $this->current_user_id])
                    ->where('DATE(scheduled_for)', $date)
                    ->where('status', 'completed')
                    ->findAll();
                foreach ($histRows as $h) {
                    $this->scheduleHistoryModel->delete($h['id']);
                }

                $restored = $this->scheduleLogModel->find($newId);
                return sendApiResponse($restored, 'Occurrence restored from history to pending', 200);
            }

            // scope = all => set schedule back to active
            $updated = $this->scheduleModel->updateStatus((int)$id, $this->current_user_id, 'active');
            if (!$updated) {
                return sendApiResponse(null, 'Failed to undo completed schedule or not found', 400);
            }

            // Restore archived completed occurrences for this schedule, then remove history rows.
            $histRows = $this->scheduleHistoryModel
                ->where(['schedule_id' => (int)$id, 'user_id' => $this->current_user_id])
                ->where('status', 'completed')
                ->findAll();

            foreach ($histRows as $h) {
                $existing = $this->scheduleLogModel
                    ->where('schedule_id', $h['schedule_id'])
                    ->where('user_id', $h['user_id'])
                    ->where('scheduled_for', $h['scheduled_for'])
                    ->first();

                if (!$existing) {
                    $this->scheduleLogModel->insert([
                        'schedule_id' => $h['schedule_id'],
                        'user_id' => $h['user_id'],
                        'scheduled_for' => $h['scheduled_for'],
                        'status' => 'pending',
                        'notes' => null,
                        'notified_at' => null,
                    ]);
                }

                $this->scheduleHistoryModel->delete($h['id']);
            }

            return sendApiResponse(null, 'Schedule restored to active', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Undone schedule error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to restore schedule', 500);
        }
    }

    /**
     * Undo a completed log (set back to pending)
     * POST /api/schedules/logs/{logId}/undo
     */
    public function undoLog($logId = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$logId) {
                return sendApiResponse(null, 'Log ID is required', 400);
            }

            // direct restore: if log exists and marked completed, revert; otherwise restore from history
            $log = $this->scheduleLogModel->where(['id' => (int)$logId, 'user_id' => $this->current_user_id])->first();

            if ($log) {
                // if exists, update back to pending
                $this->scheduleLogModel->update($log['id'], [
                    'status' => 'pending',
                    'completed_at' => null,
                    'notes' => null,
                ]);

                $updated = $this->scheduleLogModel->find($log['id']);
                return sendApiResponse($updated, 'Log undone to pending', 200);
            }

            // try restore from history
            $hist = $this->scheduleHistoryModel->where(['original_log_id' => (int)$logId, 'user_id' => $this->current_user_id])->first();
            if (!$hist) {
                return sendApiResponse(null, 'Failed to undo or log not found', 400);
            }

            $insert = [
                'schedule_id' => $hist['schedule_id'],
                'user_id' => $hist['user_id'],
                'scheduled_for' => $hist['scheduled_for'],
                'status' => 'pending',
                'notes' => null,
                'notified_at' => null,
            ];

            $this->scheduleLogModel->insert($insert);
            $newId = $this->scheduleLogModel->insertID();
            $this->scheduleHistoryModel->delete($hist['id']);

            $restored = $this->scheduleLogModel->find($newId);
            return sendApiResponse($restored, 'Log restored to pending', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Undo schedule log error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to undo schedule log', 500);
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

            // operate directly: archive log into schedule_history and remove original
            $log = $this->scheduleLogModel->where(['id' => (int)$logId, 'user_id' => $this->current_user_id])->first();

            if (!$log) {
                // maybe already archived
                $hist = $this->scheduleHistoryModel->where(['original_log_id' => (int)$logId, 'user_id' => $this->current_user_id])->first();
                if ($hist) {
                    return sendApiResponse($hist, 'Occurrence already archived', 200);
                }

                return sendApiResponse(null, 'Log not found', 404);
            }

            $schedule = $this->scheduleModel->find($log['schedule_id']);
            $historyData = [
                'original_log_id' => $log['id'],
                'schedule_id' => $log['schedule_id'],
                'user_id' => $log['user_id'],
                'scheduled_for' => $log['scheduled_for'],
                'status' => 'completed',
                'notes' => $notes ?? $log['notes'] ?? null,
                'notified_at' => $log['notified_at'] ?? null,
                'completed_at' => date('Y-m-d H:i:s'),
                'schedule_snapshot' => $schedule ? json_encode($schedule) : null,
                'archived_at' => date('Y-m-d H:i:s'),
            ];

            $this->scheduleHistoryModel->insert($historyData);
            $histId = $this->scheduleHistoryModel->insertID();

            $this->scheduleLogModel->delete($log['id']);

            $archived = $this->scheduleHistoryModel->find($histId);
            return sendApiResponse($archived, 'Schedule occurrence completed and archived', 200);
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

            $page = (int) ($this->request->getGet('page') ?? 1);
            $perPage = (int) ($this->request->getGet('per_page') ?? 20);

            $history = $this->scheduleHistoryModel->getUserHistory($this->current_user_id, $filters, $page, $perPage);

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
