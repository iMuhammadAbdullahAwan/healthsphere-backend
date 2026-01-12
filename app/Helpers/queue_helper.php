<?php

/**
 * Queue Helper Functions
 * 
 * Provides simple interface to queue background jobs
 */

if (!function_exists('queue_job')) {
    /**
     * Add a job to the queue
     *
     * @param string      $type     Job type (send_email, send_notification, ml_inference, etc.)
     * @param array       $payload  Job data
     * @param int         $priority Priority (higher = more urgent)
     * @param string|null $runAt    When to run (null = immediately)
     * @return int Job ID
     */
    function queue_job(string $type, array $payload, int $priority = 0, ?string $runAt = null): int
    {
        $db = \Config\Database::connect();

        $db->table('job_queue')->insert([
            'type'       => $type,
            'payload'    => json_encode($payload),
            'status'     => 'pending',
            'priority'   => $priority,
            'run_at'     => $runAt ?? date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $db->insertID();
    }
}

if (!function_exists('queue_email')) {
    /**
     * Queue an email for background sending
     *
     * @param string $to       Recipient email
     * @param string $subject  Email subject
     * @param string $template View template name
     * @param array  $data     Template data
     * @param int    $priority Priority (default: 5)
     * @return int Job ID
     */
    function queue_email(string $to, string $subject, string $template, array $data = [], int $priority = 5): int
    {
        return queue_job('send_email', [
            'to'       => $to,
            'subject'  => $subject,
            'template' => $template,
            'data'     => $data,
        ], $priority);
    }
}

if (!function_exists('queue_notification')) {
    /**
     * Queue a notification for background sending
     *
     * @param array $userIds   Target user IDs
     * @param string $message  Notification message
     * @param string $type     Notification type
     * @param int   $createdBy Creator user ID (0 = system)
     * @param array $extra     Additional data (link, related_id)
     * @return int Job ID
     */
    function queue_notification(array $userIds, string $message, string $type, int $createdBy = 0, array $extra = []): int
    {
        return queue_job('send_notification', array_merge([
            'user_ids'   => $userIds,
            'message'    => $message,
            'type'       => $type,
            'created_by' => $createdBy,
        ], $extra), 10); // High priority for notifications
    }
}

if (!function_exists('get_pending_jobs_count')) {
    /**
     * Get count of pending jobs
     *
     * @param string|null $type Filter by job type
     * @return int Count
     */
    function get_pending_jobs_count(?string $type = null): int
    {
        $db = \Config\Database::connect();

        $builder = $db->table('job_queue')->where('status', 'pending');

        if ($type) {
            $builder->where('type', $type);
        }

        return $builder->countAllResults();
    }
}

if (!function_exists('get_failed_jobs')) {
    /**
     * Get list of failed jobs
     *
     * @param int $limit Max results
     * @return array Failed jobs
     */
    function get_failed_jobs(int $limit = 50): array
    {
        $db = \Config\Database::connect();

        return $db->table('job_queue')
            ->where('status', 'failed')
            ->orderBy('failed_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }
}

if (!function_exists('retry_failed_job')) {
    /**
     * Retry a failed job
     *
     * @param int $jobId Job ID
     * @return bool Success
     */
    function retry_failed_job(int $jobId): bool
    {
        $db = \Config\Database::connect();

        return $db->table('job_queue')
            ->where('id', $jobId)
            ->where('status', 'failed')
            ->update([
                'status'     => 'pending',
                'attempts'   => 0,
                'last_error' => null,
                'failed_at'  => null,
                'run_at'     => date('Y-m-d H:i:s'),
            ]);
    }
}
