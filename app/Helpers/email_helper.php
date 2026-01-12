<?php

use CodeIgniter\Config\Services;

if (!function_exists('send_email')) {
    /**
     * Simple wrapper to send templated emails using CodeIgniter Email service.
     * Returns true on success, false on failure.
     */
    function send_email(string $to, string $subject, string $view, array $data = []): bool
    {
        $email = Services::email();

        $from = getenv('SMTP_FROM') ?: 'no-reply@localhost';
        $fromName = getenv('SMTP_FROM_NAME') ?: 'HealthSphere';

        $email->setFrom($from, $fromName);
        $email->setTo($to);
        $email->setSubject($subject);

        // Render view
        $message = view($view, $data);
        $email->setMessage($message);

        try {
            return $email->send();
        } catch (\Throwable $e) {
            log_message('error', 'Email send failed: ' . $e->getMessage());
            return false;
        }
    }
}
