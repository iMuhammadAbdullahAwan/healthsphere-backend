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
