<?php

namespace App\Libraries;

use App\Models\UserModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class NotificationSocket implements MessageComponentInterface
{
    protected $loop;
    protected $userClients;
    protected $pongResponses;
    protected $clients;

    public function __construct($loop)
    {
        $this->clients = new \SplObjectStorage;
        $this->loop = $loop;
        $this->userClients = [];
        $this->pongResponses = [];
        log_message('info', 'NotificationSocket initialized');
    }

    public function onOpen(ConnectionInterface $conn)
    {
        try {
            $uriQuery = $conn->httpRequest->getUri()->getQuery();
            parse_str($uriQuery, $queryParams);
            $internal_secret = $queryParams['internal_secret'] ?? null;

            // Internal connection for sending notifications
            $expectedSecret = getenv('WS_SECRET') ?: 'healthsphere_secret_2025';

            if ($internal_secret && $internal_secret === $expectedSecret) {
                $this->clients->attach($conn);
                $conn->isInternal = true;
                return;
            }

            // User connection - JWT authentication
            $access_token = $this->extractAccessToken($conn, $queryParams);

            if (!$access_token) {
                $this->respondError($conn, 'Access token not provided');
                return;
            }

            try {
                $jwtSecret = getenv('JWT_SECRET') ?: 'your-super-secret-key-change-this-in-production';
                $decoded = JWT::decode($access_token, new Key($jwtSecret, 'HS256'));

                if (!$this->isDbConnectionAlive()) {
                    $this->reconnectDb();
                }

                $userModel = new UserModel();
                // Support tokens with `uid` or legacy `data->user_id`
                $userIdFromToken = $decoded->uid ?? ($decoded->data->user_id ?? null);
                $user = $userModel->find($userIdFromToken);

                if (!$user) {
                    $this->respondError($conn, 'Invalid access token');
                    return;
                }

                $conn->user_id = $user['id'];
                $conn->user_email = $user['email'];
                $conn->isInternal = false;

                $this->userClients[$user['id']] = $conn;
                $this->clients->attach($conn);
                $this->pongResponses[$conn->resourceId] = true;

                $this->loop->addPeriodicTimer(30, function () use ($conn) {
                    $this->pingClient($conn);
                });

                $conn->send($this->formatResponse('connected', 'WebSocket connected', [
                    'user_id' => $user['id'],
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
            } catch (\Exception $e) {
                log_message('error', 'JWT error: ' . $e->getMessage());
                $this->respondError($conn, 'Invalid token');
            }
        } catch (\Throwable $e) {
            log_message('error', 'Connection error: ' . $e->getMessage());
            $this->respondError($conn, 'Connection failed');
        }
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        try {
            $data = json_decode($msg, true);
            if (!isset($data['type'])) {
                $this->respondError($from, 'Message type not specified');
                return;
            }

            switch ($data['type']) {
                case 'pong':
                    $this->pongResponses[$from->resourceId] = true;
                    break;

                case 'system_notification':
                    if (isset($from->isInternal) && $from->isInternal) {
                        $this->sendNotification($data);
                        // Don't close - let the client close the connection properly
                    } else {
                        $this->respondError($from, 'Unauthorized');
                    }
                    break;

                default:
                    $this->respondError($from, 'Invalid message type');
            }
        } catch (\Throwable $e) {
            log_message('error', 'Message error: ' . $e->getMessage());
            $this->respondError($from, 'Message failed');
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        unset($this->pongResponses[$conn->resourceId]);

        if (isset($conn->user_id)) {
            unset($this->userClients[$conn->user_id]);
        }

        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        log_message('error', "WebSocket error: " . $e->getMessage());
        $conn->send($this->formatResponse('error', $e->getMessage()));
        $conn->close();
    }

    protected function sendNotification(array $data)
    {
        $userId = $data['user_id'] ?? null;
        $notification = $data['notification'] ?? null;

        if (!$userId || !$notification) {
            log_message('error', 'Invalid notification data');
            return;
        }

        if (isset($this->userClients[$userId])) {
            $conn = $this->userClients[$userId];
            $conn->send($this->formatResponse('notification', 'New notification', $notification));
        }
    }

    protected function pingClient(ConnectionInterface $conn)
    {
        if (!$this->clients->contains($conn)) {
            return;
        }

        $conn->send($this->formatResponse('ping'));

        $this->loop->addTimer(5, function () use ($conn) {
            if (
                isset($this->pongResponses[$conn->resourceId]) &&
                !$this->pongResponses[$conn->resourceId]
            ) {
                $conn->close();
            } else {
                $this->pongResponses[$conn->resourceId] = false;
            }
        });
    }

    protected function formatResponse($type, $message = '', $data = [])
    {
        return json_encode([
            'status' => $type === 'error' ? 'error' : 'success',
            'type' => $type,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    protected function respondError(ConnectionInterface $conn, $message, $data = [])
    {
        $conn->send($this->formatResponse('error', $message, $data));
        $conn->close();
    }

    protected function isDbConnectionAlive()
    {
        try {
            $db = \Config\Database::connect();
            $db->query('SELECT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function reconnectDb()
    {
        try {
            \Config\Database::connect()->reconnect();
            log_message('info', 'Database reconnected');
        } catch (\Throwable $e) {
            log_message('error', 'DB reconnect failed: ' . $e->getMessage());
        }
    }

    protected function extractAccessToken(ConnectionInterface $conn, array $queryParams): ?string
    {
        // 1) Query param for non-cookie clients (mobile/native/cross-origin)
        $queryToken = $queryParams['access_token'] ?? null;
        if (is_string($queryToken) && trim($queryToken) !== '') {
            return trim($queryToken);
        }

        // 2) Authorization header: Bearer <token>
        $authorization = $conn->httpRequest->getHeader('Authorization')[0] ?? null;
        if (is_string($authorization) && stripos($authorization, 'Bearer ') === 0) {
            $headerToken = trim(substr($authorization, 7));
            if ($headerToken !== '') {
                return $headerToken;
            }
        }

        // 3) Cookie fallback
        $cookieHeader = $conn->httpRequest->getHeader('Cookie')[0] ?? '';
        parse_str(str_replace('; ', '&', $cookieHeader), $cookies);

        $cookieToken = $cookies['_healthsphere_access_token'] ?? ($cookies['access_token'] ?? null);
        if (is_string($cookieToken) && trim($cookieToken) !== '') {
            return trim($cookieToken);
        }

        return null;
    }
}
