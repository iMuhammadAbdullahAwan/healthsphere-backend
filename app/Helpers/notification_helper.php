<?php

use App\Libraries\NotificationService;

if (!function_exists('notify')) {
    /**
     * Create and send a notification
     * 
     * @param int|array $userIds Single user ID or array of user IDs
     * @param int $createdBy User ID who created the notification
     * @param string $message Notification message
     * @param string $type Notification type (club_invitation, course_assignment, etc.)
     * @param string|null $link Optional link
     * @param int|null $relatedId Optional related entity ID
     * @return int|bool Notification ID on success, false on failure
     */
    function notify(
        $userIds,
        int $createdBy,
        string $message,
        string $type,
        ?string $link = null,
        ?int $relatedId = null
    ) {
        $notificationService = new NotificationService();

        // Convert single user ID to array
        if (is_int($userIds)) {
            $userIds = [$userIds];
        }

        // Remove creator from recipients
        $userIds = array_filter($userIds, function ($id) use ($createdBy) {
            return $id != $createdBy;
        });

        if (empty($userIds)) {
            return 0;
        }

        $data = [
            'user_ids'   => array_values($userIds), // Re-index array
            'created_by' => $createdBy,
            'message'    => $message,
            'type'       => $type,
            'link'       => $link,
            'related_id' => $relatedId,
        ];

        return $notificationService->createNotification($data);
    }
}

if (!function_exists('notifyScheduleReminder')) {
    /**
     * Send a schedule reminder notification
     * 
     * @param int $userId User ID to notify
     * @param int $scheduleId Schedule ID
     * @param string $scheduleType Type of schedule (medicine, food, water, etc.)
     * @param string $title Schedule title
     * @param string $message Custom message (optional)
     * @return int|bool Notification ID on success, false on failure
     */
    function notifyScheduleReminder(
        int $userId,
        int $scheduleId,
        string $scheduleType,
        string $title,
        string $message = null
    ) {
        $notificationService = new NotificationService();

        // Generate message if not provided
        if (!$message) {
            $message = match ($scheduleType) {
                'medicine' => "Time to take your medicine: {$title}",
                'food' => "Meal reminder: {$title}",
                'water' => "Time to drink water: {$title}",
                'running' => "Time for your activity: {$title}",
                'sleep' => "Sleep reminder: {$title}",
                'custom' => "Reminder: {$title}",
                default => "Schedule reminder: {$title}"
            };
        }

        $data = [
            'user_ids'   => [$userId],
            'created_by' => $userId, // System notification
            'message'    => $message,
            'type'       => 'schedule_reminder',
            'link'       => "/schedules/{$scheduleId}",
            'related_id' => $scheduleId,
        ];

        return $notificationService->createNotification($data);
    }
}
