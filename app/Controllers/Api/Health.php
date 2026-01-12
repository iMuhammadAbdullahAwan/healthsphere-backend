<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;

/**
 * Health Controller
 *
 * Handles API health check and status endpoints.
 *
 * @package    HealthSphere
 * @subpackage Controllers\Api
 * @category   Health
 * @author     HealthSphere Team
 * @version    1.0.0
 */
class Health extends BaseController
{
    /**
     * Get API health status
     *
     * Returns comprehensive health information including:
     * - Service status
     * - Database connectivity
     * - Cache status
     * - Environment information
     *
     * @return ResponseInterface JSON response with health data
     */
    public function index(): ResponseInterface
    {
        try {
            $healthData = [
                'service'     => 'HealthSphere API',
                'version'     => '1.0.0',
                'environment' => ENVIRONMENT,
                'database'    => $this->getDatabaseStatus(),
                'cache'       => $this->getCacheStatus(),
                'websocket'   => $this->getWebSocketStatus(),
                'queue'       => $this->getQueueStatus(),
                'timestamp'   => date('Y-m-d H:i:s'),
            ];

            return sendApiResponse($healthData, 'Service is running', 200);
        } catch (\Exception $e) {
            log_message('error', 'Health check failed: ' . $e->getMessage());

            return sendApiResponse(null, 'Health check failed', 500);
        }
    }

    /**
     * Get database connection status
     *
     * @return string 'connected' or 'disconnected'
     */
    private function getDatabaseStatus(): string
    {
        try {
            $db = Database::connect();
            $db->initialize();

            return $db->connID !== false ? 'connected' : 'disconnected';
        } catch (\Exception $e) {
            log_message('error', 'Database health check failed: ' . $e->getMessage());

            return 'disconnected';
        }
    }

    /**
     * Get cache system status
     *
     * @return string 'active' or 'inactive'
     */
    private function getCacheStatus(): string
    {
        try {
            return cache()->isSupported() ? 'active' : 'inactive';
        } catch (\Exception $e) {
            log_message('error', 'Cache health check failed: ' . $e->getMessage());

            return 'inactive';
        }
    }

    /**
     * Get WebSocket server status
     *
     * @return array Status info
     */
    private function getWebSocketStatus(): array
    {
        $host = getenv('WS_HOST') ?: 'ws://localhost';
        $port = getenv('WS_PORT') ?: 8084;

        try {
            $socket = @fsockopen(str_replace(['ws://', 'wss://'], '', $host), (int) $port, $errno, $errstr, 2);

            if ($socket) {
                fclose($socket);
                return ['status' => 'running', 'port' => $port];
            }

            return ['status' => 'stopped', 'port' => $port];
        } catch (\Throwable $e) {
            return ['status' => 'unknown', 'error' => $e->getMessage()];
        }
    }

    /**
     * Get job queue status
     *
     * @return array Queue statistics
     */
    private function getQueueStatus(): array
    {
        try {
            $db = \Config\Database::connect();

            $pending = $db->table('job_queue')->where('status', 'pending')->countAllResults();
            $processing = $db->table('job_queue')->where('status', 'processing')->countAllResults();
            $failed = $db->table('job_queue')->where('status', 'failed')->countAllResults();

            return [
                'status'     => 'active',
                'pending'    => $pending,
                'processing' => $processing,
                'failed'     => $failed,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'unknown', 'error' => $e->getMessage()];
        }
    }
}
