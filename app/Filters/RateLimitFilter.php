<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Rate Limiting Filter
 * 
 * Protects endpoints from brute-force attacks and abuse.
 * Uses file-based cache by default, can be configured for Redis.
 */
class RateLimitFilter implements FilterInterface
{
    /**
     * Rate limit configurations per route pattern
     */
    private array $limits = [
        'auth/login'           => ['requests' => 5,  'window' => 60],   // 5 per minute
        'auth/register'        => ['requests' => 3,  'window' => 60],   // 3 per minute
        'auth/forgot-password' => ['requests' => 3,  'window' => 300],  // 3 per 5 minutes
        'auth/send-otp'        => ['requests' => 3,  'window' => 300],  // 3 per 5 minutes
        'auth/verify-otp'      => ['requests' => 5,  'window' => 60],   // 5 per minute
        'default'              => ['requests' => 60, 'window' => 60],   // 60 per minute
    ];

    public function before(RequestInterface $request, $arguments = null): mixed
    {
        $cache = \Config\Services::cache();
        $ip = $request->getIPAddress();
        /** @var IncomingRequest $request */
        $path = trim($request->getUri()->getPath(), '/');

        // Find matching rate limit config
        $config = $this->limits['default'];
        foreach ($this->limits as $pattern => $limit) {
            if ($pattern !== 'default' && str_contains($path, $pattern)) {
                $config = $limit;
                break;
            }
        }

        $key = 'rate_limit_' . md5($ip . '_' . $path);
        $data = $cache->get($key);

        $now = time();

        if ($data === null) {
            $data = [
                'count'      => 1,
                'window_start' => $now,
            ];
        } else {
            // Check if window has expired
            if ($now - $data['window_start'] >= $config['window']) {
                // Reset window
                $data = [
                    'count'      => 1,
                    'window_start' => $now,
                ];
            } else {
                $data['count']++;
            }
        }

        // Check if limit exceeded
        if ($data['count'] > $config['requests']) {
            $retryAfter = $config['window'] - ($now - $data['window_start']);

            log_message('warning', "Rate limit exceeded for IP {$ip} on {$path}");

            return \Config\Services::response()
                ->setStatusCode(429)
                ->setHeader('Retry-After', (string) $retryAfter)
                ->setHeader('X-RateLimit-Limit', (string) $config['requests'])
                ->setHeader('X-RateLimit-Remaining', '0')
                ->setHeader('X-RateLimit-Reset', (string) ($data['window_start'] + $config['window']))
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Too many requests. Please try again later.',
                    'data'    => [
                        'retry_after' => $retryAfter,
                    ]
                ]);
        }

        // Save updated count
        $cache->save($key, $data, $config['window']);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        // Add rate limit headers to response
        $ip = $request->getIPAddress();
        /** @var IncomingRequest $request */
        $path = trim($request->getUri()->getPath(), '/');

        $config = $this->limits['default'];
        foreach ($this->limits as $pattern => $limit) {
            if ($pattern !== 'default' && str_contains($path, $pattern)) {
                $config = $limit;
                break;
            }
        }

        $key = 'rate_limit_' . md5($ip . '_' . $path);
        $cache = \Config\Services::cache();
        $data = $cache->get($key);

        if ($data) {
            $remaining = max(0, $config['requests'] - $data['count']);
            $reset = $data['window_start'] + $config['window'];

            $response->setHeader('X-RateLimit-Limit', (string) $config['requests']);
            $response->setHeader('X-RateLimit-Remaining', (string) $remaining);
            $response->setHeader('X-RateLimit-Reset', (string) $reset);
        }
    }
}
