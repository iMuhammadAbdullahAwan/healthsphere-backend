<?php

/**
 * Response Helper
 * 
 * Global helper function for consistent API responses.
 * 
 * @package HealthSphere
 */

if (!function_exists('sendApiResponse')) {
    /**
     * Send standardized API response
     *
     * @param mixed       $data    Response data
     * @param string      $message Response message
     * @param int|null    $status  HTTP status code
     * @param string      $locale  Locale (default: 'en')
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    function sendApiResponse($data = null, string $message = '', ?int $status = null, $locale = 'en')
    {
        $responseHandler = new \App\Libraries\ResponseHandler();
        return $responseHandler->send($data, $message, $status, $locale);
    }
}
