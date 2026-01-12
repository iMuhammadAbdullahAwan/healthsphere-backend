<?php

namespace App\Libraries;

use WebSocket\Client;

class InternalWebsocket
{
    private $host;
    private $port;
    private $secret;

    public function __construct()
    {
        $this->host = getenv('WS_HOST') ?: 'ws://localhost';
        $this->port = getenv('WS_PORT') ?: 8084;
        $this->secret = getenv('WS_SECRET') ?: 'healthsphere_secret_2025';
    }

    /**
     * Send message to WebSocket server
     *
     * @param array $message Message data to send
     * @return bool True on success, false on failure
     */
    public function sendMessage(array $message): bool
    {
        try {
            $wsUrl = $this->host . ":" . $this->port . "?internal_secret=" . $this->secret;
            log_message('info', "InternalWebsocket::sendMessage - Connecting to: {$wsUrl}");
            log_message('debug', "Message payload: " . json_encode($message));

            $context = stream_context_create();
            stream_context_set_option($context, 'ssl', 'verify_peer', false);
            stream_context_set_option($context, 'ssl', 'verify_peer_name', false);

            $client = new Client($wsUrl, [
                'context' => $context,
                'timeout' => 5
            ]);

            log_message('info', "WebSocket client connected, sending message...");
            $client->text(json_encode($message));

            log_message('info', "Message sent, closing connection...");
            $client->close();

            log_message('info', "WebSocket message sent successfully");
            return true;
        } catch (\Throwable $e) {
            log_message('error', "WebSocket connection failed: " . $e->getMessage());
            log_message('error', "Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }
}
