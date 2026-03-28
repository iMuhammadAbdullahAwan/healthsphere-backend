<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Cron Job: Process queued background jobs
 * 
 * Usage: php spark queue:work
 * Recommended: Run as a daemon or via supervisor
 */
class QueueWorker extends BaseCommand
{
    protected $group       = 'HealthSphere';
    protected $name        = 'queue:work';
    protected $description = 'Process queued background jobs (emails, notifications, ML tasks)';
    protected $usage       = 'queue:work [--once] [--sleep=<seconds>]';
    protected $options     = [
        '--once'  => 'Process one job and exit',
        '--sleep' => 'Sleep duration between job checks (default: 3)',
    ];

    private bool $shouldRun = true;

    public function run(array $params)
    {
        $once = array_key_exists('once', $params);
        $sleep = (int) ($params['sleep'] ?? 3);

        CLI::write('Queue worker started...', 'green');

        // Handle graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function () {
                $this->shouldRun = false;
                CLI::write('Received SIGTERM, shutting down gracefully...', 'yellow');
            });
            pcntl_signal(SIGINT, function () {
                $this->shouldRun = false;
                CLI::write('Received SIGINT, shutting down gracefully...', 'yellow');
            });
        }

        $db = \Config\Database::connect();
        $processed = 0;

        while ($this->shouldRun) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            // Fetch next pending job
            $job = $db->table('job_queue')
                ->where('status', 'pending')
                ->where('run_at <=', date('Y-m-d H:i:s'))
                ->orderBy('priority', 'DESC')
                ->orderBy('created_at', 'ASC')
                ->get(1)
                ->getRowArray();

            if ($job) {
                $this->processJob($job, $db);
                $processed++;

                if ($once) {
                    break;
                }
            } else {
                if ($once) {
                    CLI::write('No jobs in queue.', 'yellow');
                    break;
                }
                sleep($sleep);
            }
        }

        CLI::write("Processed {$processed} jobs.", 'green');
    }

    /**
     * Process a single job
     */
    private function processJob(array $job, $db): void
    {
        $jobId = $job['id'];
        $jobType = $job['type'];
        $payload = json_decode($job['payload'], true);

        CLI::write("Processing job #{$jobId} ({$jobType})...", 'light_gray');

        // Mark as processing
        $db->table('job_queue')->where('id', $jobId)->update([
            'status'     => 'processing',
            'started_at' => date('Y-m-d H:i:s')
        ]);

        try {
            $result = match ($jobType) {
                'send_email'       => $this->handleEmailJob($payload),
                'send_notification' => $this->handleNotificationJob($payload),
                'ml_inference'     => $this->handleMLJob($payload),
                'generate_report'  => $this->handleReportJob($payload),
                default            => throw new \Exception("Unknown job type: {$jobType}")
            };

            // Mark as completed
            $db->table('job_queue')->where('id', $jobId)->update([
                'status'       => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
                'result'       => json_encode($result)
            ]);

            CLI::write("  → Job #{$jobId} completed.", 'green');
        } catch (\Throwable $e) {
            $attempts = ($job['attempts'] ?? 0) + 1;
            $maxAttempts = 3;

            $updateData = [
                'attempts'   => $attempts,
                'last_error' => $e->getMessage()
            ];

            if ($attempts >= $maxAttempts) {
                $updateData['status'] = 'failed';
                $updateData['failed_at'] = date('Y-m-d H:i:s');
                CLI::write("  → Job #{$jobId} failed permanently: " . $e->getMessage(), 'red');
            } else {
                $updateData['status'] = 'pending';
                $updateData['run_at'] = date('Y-m-d H:i:s', strtotime("+{$attempts} minutes"));
                CLI::write("  → Job #{$jobId} failed, retrying in {$attempts} min: " . $e->getMessage(), 'yellow');
            }

            $db->table('job_queue')->where('id', $jobId)->update($updateData);

            log_message('error', "Job #{$jobId} error: " . $e->getMessage());
        }
    }

    /**
     * Handle email sending job
     */
    private function handleEmailJob(array $payload): array
    {
        helper('email');

        $success = send_email(
            $payload['to'],
            $payload['subject'],
            $payload['template'],
            $payload['data'] ?? []
        );

        return ['sent' => $success];
    }

    /**
     * Handle notification job
     */
    private function handleNotificationJob(array $payload): array
    {
        $service = new \App\Libraries\NotificationService();
        $result = $service->createNotification($payload);

        return ['notification_id' => $result];
    }

    /**
     * Handle ML inference job
     */
    private function handleMLJob(array $payload): array
    {
        helper('ml');

        $type = $payload['type'] ?? 'food_detection';
        $data = $payload['data'] ?? [];

        $result = ml_inference($type, $data);

        return $result;
    }

    /**
     * Handle report generation job
     */
    private function handleReportJob(array $payload): array
    {
        $userId = $payload['user_id'];
        $reportType = $payload['report_type'] ?? 'weekly';

        // Generate report logic here
        // For now, return stub
        return [
            'user_id'     => $userId,
            'report_type' => $reportType,
            'generated'   => true,
            'file_path'   => null
        ];
    }
}
