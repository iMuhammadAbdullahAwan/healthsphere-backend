<?php

/**
 * ML Service Integration Helper
 * 
 * Provides functions to communicate with external ML/AI services
 * for food detection, posture analysis, and diet recommendations.
 */

if (!function_exists('ml_inference')) {
    /**
     * Send inference request to ML service
     *
     * @param string $type    Inference type: food_detection, posture_analysis, diet_recommendation
     * @param array  $data    Request data (image_base64, user_profile, etc.)
     * @param array  $options Additional options (timeout, async)
     * @return array Response from ML service
     */
    function ml_inference(string $type, array $data, array $options = []): array
    {
        $mlHost = getenv('ML_SERVICE_HOST') ?: 'http://localhost:8000';
        $mlApiKey = getenv('ML_SERVICE_API_KEY') ?: '';
        $timeout = $options['timeout'] ?? 30;

        $endpoints = [
            'food_detection'      => '/api/food/detect',
            'posture_analysis'    => '/api/posture/analyze',
            'diet_recommendation' => '/api/diet/recommend',
            'exercise_correction' => '/api/exercise/correct',
            'device_guide'        => '/api/device/guide',
        ];

        if (!isset($endpoints[$type])) {
            throw new \InvalidArgumentException("Unknown ML inference type: {$type}");
        }

        $url = rtrim($mlHost, '/') . $endpoints[$type];

        $client = \Config\Services::curlrequest([
            'timeout' => $timeout,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer {$mlApiKey}",
                'X-Request-ID'  => uniqid('ml_', true),
            ]
        ]);

        try {
            $response = $client->post($url, [
                'json' => $data,
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);

            if ($statusCode >= 200 && $statusCode < 300) {
                return [
                    'success' => true,
                    'data'    => $body['data'] ?? $body,
                    'latency' => $body['latency_ms'] ?? null,
                ];
            }

            log_message('error', "ML service error ({$statusCode}): " . json_encode($body));
            return [
                'success' => false,
                'error'   => $body['error'] ?? 'ML service request failed',
            ];
        } catch (\Throwable $e) {
            log_message('error', "ML service connection failed: " . $e->getMessage());
            return [
                'success' => false,
                'error'   => 'ML service unavailable: ' . $e->getMessage(),
            ];
        }
    }
}

if (!function_exists('queue_ml_job')) {
    /**
     * Queue an ML inference job for background processing
     *
     * @param string $type     Inference type
     * @param array  $data     Request data
     * @param int    $priority Job priority (higher = more urgent)
     * @return int Job ID
     */
    function queue_ml_job(string $type, array $data, int $priority = 0): int
    {
        $db = \Config\Database::connect();

        $db->table('job_queue')->insert([
            'type'       => 'ml_inference',
            'payload'    => json_encode(['type' => $type, 'data' => $data]),
            'status'     => 'pending',
            'priority'   => $priority,
            'run_at'     => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $db->insertID();
    }
}

if (!function_exists('detect_food')) {
    /**
     * Detect food items in an image
     *
     * @param string $imageBase64 Base64 encoded image
     * @param array  $options     Additional options
     * @return array Detected food items with nutritional info
     */
    function detect_food(string $imageBase64, array $options = []): array
    {
        $result = ml_inference('food_detection', [
            'image'   => $imageBase64,
            'options' => $options,
        ]);

        if (!$result['success']) {
            // Return mock data in development
            if (getenv('CI_ENVIRONMENT') === 'development') {
                return [
                    'success' => true,
                    'data'    => [
                        'items' => [
                            [
                                'name'       => 'Apple',
                                'confidence' => 0.92,
                                'calories'   => 95,
                                'protein'    => 0.5,
                                'carbs'      => 25,
                                'fat'        => 0.3,
                                'fiber'      => 4.4,
                            ]
                        ],
                        'total_calories' => 95,
                    ],
                    'mock' => true,
                ];
            }
        }

        return $result;
    }
}

if (!function_exists('analyze_posture')) {
    /**
     * Analyze posture from image/video frame
     *
     * @param string $imageBase64 Base64 encoded image
     * @param string $exerciseId  Exercise being performed
     * @return array Posture analysis with corrections
     */
    function analyze_posture(string $imageBase64, string $exerciseId = ''): array
    {
        $result = ml_inference('posture_analysis', [
            'image'       => $imageBase64,
            'exercise_id' => $exerciseId,
        ]);

        if (!$result['success']) {
            // Return mock data in development
            if (getenv('CI_ENVIRONMENT') === 'development') {
                return [
                    'success' => true,
                    'data'    => [
                        'score'       => 78,
                        'corrections' => [
                            [
                                'body_part'   => 'shoulders',
                                'issue'       => 'Shoulders too high',
                                'suggestion'  => 'Relax your shoulders and roll them back',
                                'severity'    => 'minor',
                            ]
                        ],
                        'keypoints' => [],
                    ],
                    'mock' => true,
                ];
            }
        }

        return $result;
    }
}

if (!function_exists('get_diet_recommendation')) {
    /**
     * Get AI diet recommendations based on user profile
     *
     * @param array $profile User health profile
     * @param array $goals   User goals (weight_loss, muscle_gain, etc.)
     * @return array Diet recommendations
     */
    function get_diet_recommendation(array $profile, array $goals = []): array
    {
        $result = ml_inference('diet_recommendation', [
            'profile' => $profile,
            'goals'   => $goals,
        ]);

        if (!$result['success']) {
            // Return mock recommendations in development
            if (getenv('CI_ENVIRONMENT') === 'development') {
                return [
                    'success' => true,
                    'data'    => [
                        'daily_calories' => 2000,
                        'macros'         => [
                            'protein'  => 150,
                            'carbs'    => 200,
                            'fat'      => 65,
                        ],
                        'meals' => [
                            ['type' => 'breakfast', 'calories' => 400, 'suggestions' => ['Oatmeal with berries', 'Greek yogurt']],
                            ['type' => 'lunch', 'calories' => 600, 'suggestions' => ['Grilled chicken salad', 'Quinoa bowl']],
                            ['type' => 'dinner', 'calories' => 700, 'suggestions' => ['Salmon with vegetables', 'Lean steak']],
                            ['type' => 'snacks', 'calories' => 300, 'suggestions' => ['Almonds', 'Apple', 'Protein bar']],
                        ],
                    ],
                    'mock' => true,
                ];
            }
        }

        return $result;
    }
}
