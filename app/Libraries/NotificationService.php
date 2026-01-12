<?php

namespace App\Libraries;

use App\Models\NotificationModel;
use App\Models\NotificationUserModel;
use App\Libraries\InternalWebsocket;

class NotificationService
{
    protected $notificationModel;
    protected $notificationUserModel;
    protected $websocket;

    public function __construct()
    {
        $this->notificationModel = new NotificationModel();
        $this->notificationUserModel = new NotificationUserModel();
        $this->websocket = new InternalWebsocket();
    }

    /**
     * Create and send a notification
     *
     * @param array $notificationData Notification data (user_ids, created_by, message, type, link, related_id)
     * @return int|bool Notification ID on success, false on failure
     */
    public function createNotification(array $notificationData)
    {

        $requiredFields = ['user_ids', 'created_by', 'message', 'type'];
        foreach ($requiredFields as $field) {
            if (!isset($notificationData[$field])) {
                log_message('error', "Missing required field: {$field}");
                return false;
            }
        }

        $userIds = is_array($notificationData['user_ids']) ? $notificationData['user_ids'] : [$notificationData['user_ids']];
        log_message('info', "Creating notification for " . count($userIds) . " users");

        // Create the notification record
        $notificationId = $this->notificationModel->insert([
            'created_by' => $notificationData['created_by'],
            'message' => $notificationData['message'],
            'type' => $notificationData['type'],
            'link' => $notificationData['link'] ?? null,
            'related_id' => $notificationData['related_id'] ?? null,
        ]);

        if (!$notificationId) {
            log_message('error', 'Failed to create notification record');
            return false;
        }

        // Create notification_users records and send real-time notifications
        $successCount = 0;
        foreach ($userIds as $userId) {
            // Insert into pivot table
            $pivotInserted = $this->notificationUserModel->insert([
                'notification_id' => $notificationId,
                'user_id' => $userId,
            ]);

            if ($pivotInserted) {
                $successCount++;

                // Send real-time notification
                $notification = $this->notificationModel->find($notificationId);
                if ($notification) {
                    $this->sendRealtimeNotification($userId, $notification);
                } else {
                    log_message('error', "Failed to fetch notification {$notificationId} for user {$userId}");
                }
            } else {
                log_message('error', "Failed to create pivot record for user {$userId}");
            }
        }
        return $notificationId;
    }

    /**
     * Send real-time notification through WebSocket
     *
     * @param int $userId User ID to send to
     * @param array $notification Notification data
     * @return bool True on success, false on failure
     */
    protected function sendRealtimeNotification(int $userId, array $notification): bool
    {
        try {
            $wsData = [
                'type' => 'system_notification',
                'user_id' => $userId,
                'notification' => $notification
            ];
            return $this->websocket->sendMessage($wsData);
        } catch (\Throwable $e) {
            log_message('error', "Failed to send real-time notification: " . $e->getMessage());
            return false;
        }
    }
}
