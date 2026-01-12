<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\NotificationModel;
use CodeIgniter\HTTP\ResponseInterface;

class NotificationController extends BaseController
{
    protected $notificationModel;

    public function __construct()
    {
        $this->notificationModel = new NotificationModel();
    }


    /**
     * Get all notifications for the current user
     * GET /api/notifications
     */
    public function index(): ResponseInterface
    {
        try {
            $filters = $this->request->getGet();
            $notifications = $this->notificationModel->getUserNotifications($this->current_user_id, $filters);

            return sendApiResponse($notifications, 'Notifications retrieved successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Get notifications error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve notifications', 500);
        }
    }

    /**
     * Get unread notification count
     * GET /api/notifications/unread-count
     */
    public function unreadCount(): ResponseInterface
    {
        try {
            $count = $this->notificationModel->getUnreadCount($this->current_user_id);

            return sendApiResponse(['count' => $count], 'Unread count retrieved successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Get unread count error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve unread count', 500);
        }
    }

    /**
     * Mark a specific notification as read
     * PATCH /api/notifications/(:num)/read
     */
    public function markAsRead(int $notificationId): ResponseInterface
    {
        try {
            if ($this->notificationModel->markAsRead($notificationId, $this->current_user_id)) {
                return sendApiResponse(null, 'Notification marked as read', 200);
            }

            return sendApiResponse(null, 'Failed to mark notification as read', 400);
        } catch (\Throwable $e) {
            log_message('error', 'Mark as read error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to mark notification as read', 500);
        }
    }

    /**
     * Mark all notifications as read for current user
     * PATCH /api/notifications/mark-all-read
     */
    public function markAllAsRead(): ResponseInterface
    {
        try {
            if ($this->notificationModel->markAllAsRead($this->current_user_id)) {
                return sendApiResponse(null, 'All notifications marked as read', 200);
            }

            return sendApiResponse(null, 'Failed to mark all notifications as read', 400);
        } catch (\Throwable $e) {
            log_message('error', 'Mark all as read error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to mark all notifications as read', 500);
        }
    }

    /**
     * Delete a specific notification
     * DELETE /api/notifications/(:num)
     */
    public function delete(int $notificationId): ResponseInterface
    {
        try {
            if ($this->notificationModel->deleteForUser($notificationId, $this->current_user_id)) {
                return sendApiResponse(null, 'Notification deleted successfully', 200);
            }

            return sendApiResponse(null, 'Failed to delete notification', 400);
        } catch (\Throwable $e) {
            log_message('error', 'Delete notification error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to delete notification', 500);
        }
    }

    /**
     * Get a specific notification
     * GET /api/notifications/(:num)
     */
    public function show(int $notificationId): ResponseInterface
    {
        try {
            $details = $this->notificationModel->getNotificationWithCreator($notificationId);

            if (!$details) {
                return sendApiResponse(null, 'Notification not found', 404);
            }

            return sendApiResponse($details, 'Notification retrieved successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Get notification error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve notification', 500);
        }
    }
}
