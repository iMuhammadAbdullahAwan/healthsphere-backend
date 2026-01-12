<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Retry Failed Queue Jobs
 * 
 * Usage: php spark queue:retry-failed [--limit=<number>]
 */
class RetryFailedJobs extends BaseCommand
{
    protected $group       = 'HealthSphere';
    protected $name        = 'queue:retry-failed';
    protected $description = 'Retry all failed jobs in the queue';
    protected $usage       = 'queue:retry-failed [--limit=<number>] [--job=<id>]';
    protected $options     = [
        '--limit' => 'Maximum number of jobs to retry (default: all)',
        '--job'   => 'Retry specific job ID',
    ];

    public function run(array $params)
    {
        helper('queue');

        $specificJobId = $params['job'] ?? null;
        $limit = (int) ($params['limit'] ?? 0);

        CLI::write('Retrying failed jobs...', 'yellow');

        if ($specificJobId) {
            // Retry specific job
            if (retry_failed_job((int) $specificJobId)) {
                CLI::write("  ✓ Retried job #{$specificJobId}", 'green');
            } else {
                CLI::write("  ✗ Could not retry job #{$specificJobId}", 'red');
            }
            return;
        }

        // Get failed jobs
        $failedJobs = get_failed_jobs($limit ?: 1000);

        if (empty($failedJobs)) {
            CLI::write('No failed jobs found.', 'light_gray');
            return;
        }

        $retried = 0;
        foreach ($failedJobs as $job) {
            if (retry_failed_job((int) $job['id'])) {
                $retried++;
                CLI::write("  → Retried: {$job['type']} (ID: {$job['id']})", 'light_gray');

                if ($limit > 0 && $retried >= $limit) {
                    break;
                }
            }
        }

        CLI::write("Retried {$retried} failed job(s).", 'green');
    }
}
