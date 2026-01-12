<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Response;

/**
 * ResponseHandler Library
 * 
 * Provides consistent API response formatting across the application.
 * Based on ClientRamp response pattern.
 * 
 * @package HealthSphere
 * @author  HealthSphere Development Team
 * @version 1.0.0
 */
class ResponseHandler extends Response
{
    protected $response;
    protected $request;

    public function __construct()
    {
        $this->response = service('response');
        $this->request = service('request');
    }

    /**
     * Send standardized API response
     *
     * @param mixed       $data    Response data payload
     * @param string      $message Human-readable message
     * @param int|null    $status  HTTP status code
     * @param string      $locale  Locale for internationalization
     * @return Response
     */
    public function send($data = null, string $message = '', ?int $status = null, $locale = "en")
    {
        if ($data === null && $status === null) {
            $status = 404;
            $output = null;
        } elseif ($data === null && is_numeric($status)) {
            $output = null;
        } else {
            $status ??= 200;
            $output = $data;
        }

        // Clean the message to prevent newline issues in HTTP headers
        $cleanMessage = str_replace(["\r\n", "\r", "\n", "\t"], ' ', trim($message));

        $responseData = [
            'data' => $output,
            'message' => $cleanMessage,
            'status' => $status,
        ];
        // Add CORS headers - Get origin from request
        $origin = $this->request->getHeaderLine('Origin') ?: '*';
        $this->response->setHeader('Access-Control-Allow-Origin', $origin);
        $this->response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        $this->response->setHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization');
        $this->response->setHeader('Access-Control-Max-Age', '7200');


        return $this->response->setJSON($responseData)->setStatusCode($status, $cleanMessage);
    }
}



