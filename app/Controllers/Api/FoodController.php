<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\FoodLogModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\OpenAI;

/**
 * Food Controller
 * 
 * Handles food image analysis using OpenAI Vision API and food logging
 * 
 * @package HealthSphere
 */
class FoodController extends BaseController
{
    protected $openAIConfig;
    protected $foodLogModel;

    public function __construct()
    {
        $this->openAIConfig = new OpenAI();
        $this->foodLogModel = new FoodLogModel();
    }

    /**
     * Get food logs with optional filters
     * GET /api/food-logs?meal_type=lunch&start_date=2024-01-01
     * 
     * @return ResponseInterface
     */
    public function index(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $filters = [
                'meal_type' => $this->request->getGet('meal_type'),
                'start_date' => $this->request->getGet('start_date'),
                'end_date' => $this->request->getGet('end_date'),
            ];

            $logs = $this->foodLogModel->getUserFoodLogs($this->current_user_id, $filters);

            return sendApiResponse($logs, 'Food logs retrieved successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Get food logs error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve food logs', 500);
        }
    }

    /**
     * Get nutrition summary
     * GET /api/food-logs/summary?start_date=2024-01-01&end_date=2024-01-31
     * 
     * @return ResponseInterface
     */
    public function summary(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $startDate = $this->request->getGet('start_date');
            $endDate = $this->request->getGet('end_date');

            $summary = $this->foodLogModel->getNutritionSummary($this->current_user_id, $startDate, $endDate);

            return sendApiResponse($summary, 'Nutrition summary retrieved successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Get nutrition summary error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve nutrition summary', 500);
        }
    }

    /**
     * Get daily nutrition breakdown
     * GET /api/food-logs/daily?days=7
     * 
     * @return ResponseInterface
     */
    public function daily(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $days = (int)($this->request->getGet('days') ?? 7);

            $breakdown = $this->foodLogModel->getDailyBreakdown($this->current_user_id, $days);

            return sendApiResponse($breakdown, 'Daily breakdown retrieved successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Get daily breakdown error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve daily breakdown', 500);
        }
    }

    /**
     * Upload and analyze food image
     * POST /api/food-logs/analyze
     * 
     * @return ResponseInterface
     */
    public function upload(): ResponseInterface
    {
        try {
            // Validate authentication
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            // Check analysis mode - skip API key validation in mock mode
            $mode = $this->openAIConfig->foodAnalysisMode;

            if ($mode !== 'mock') {
                // Validate API key based on mode
                if ($mode === 'clarifai' && empty($this->openAIConfig->clarifaiApiKey)) {
                    log_message('error', 'Clarifai API key not configured');
                    return sendApiResponse(null, 'AI service not configured', 500);
                }

                if ($mode === 'openai' && empty($this->openAIConfig->apiKey)) {
                    log_message('error', 'OpenAI API key not configured');
                    return sendApiResponse(null, 'AI service not configured', 500);
                }
            }

            // Validate file upload
            $file = $this->request->getFile('food_image');

            if (!$file || !$file->isValid()) {
                return sendApiResponse(null, 'Invalid file upload', 400);
            }

            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            $mimeType = $file->getMimeType();

            if (!in_array($mimeType, $allowedTypes)) {
                return sendApiResponse(null, 'Only JPEG, PNG, and WebP images are allowed', 400);
            }

            // Validate file size (max 20MB for OpenAI)
            if ($file->getSize() > 20 * 1024 * 1024) {
                return sendApiResponse(null, 'File size too large. Maximum 20MB allowed', 400);
            }

            // Move file to uploads directory
            $uploadPath = WRITEPATH . 'uploads/food/';
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            $fileName = $file->getRandomName();
            $file->move($uploadPath, $fileName);
            $filePath = $uploadPath . $fileName;

            // Convert image to base64
            $imageData = base64_encode(file_get_contents($filePath));

            // Analyze food based on configured mode
            $mode = $this->openAIConfig->foodAnalysisMode;

            switch ($mode) {
                case 'openai':
                    $base64Image = "data:{$mimeType};base64,{$imageData}";
                    $nutritionData = $this->analyzeFood($base64Image);
                    break;

                case 'clarifai':
                    $nutritionData = $this->analyzeFoodClarifai($imageData);
                    break;

                case 'mock':
                default:
                    $nutritionData = $this->analyzeFoodMock($fileName);
                    break;
            }

            if (!$nutritionData) {
                // Clean up file on error
                @unlink($filePath);

                $errorMsg = 'Failed to analyze food image. ';
                $errorMsg .= 'Mode: ' . $mode . '. Check server logs for details.';

                return sendApiResponse(null, $errorMsg, 500);
            }

            // Get optional parameters
            $mealType = $this->request->getPost('meal_type');
            $consumedAt = $this->request->getPost('consumed_at') ?? date('Y-m-d H:i:s');
            $saveLog = $this->request->getPost('save_log') !== 'false'; // default true

            // Save to database if requested
            $logId = null;
            if ($saveLog) {
                $logData = [
                    'user_id' => $this->current_user_id,
                    'food_name' => $nutritionData['food_name'] ?? 'Unknown Food',
                    'image_path' => 'uploads/food/' . $fileName,
                    'portion_size' => $nutritionData['portion_size'] ?? null,
                    'calories' => $nutritionData['calories'] ?? 0,
                    'protein' => $nutritionData['protein'] ?? 0,
                    'carbohydrates' => $nutritionData['carbohydrates'] ?? 0,
                    'fat' => $nutritionData['fat'] ?? 0,
                    'fiber' => $nutritionData['fiber'] ?? 0,
                    'sugar' => $nutritionData['sugar'] ?? 0,
                    'sodium' => $nutritionData['sodium'] ?? 0,
                    'other_nutrients' => $nutritionData['other_nutrients'] ?? [],
                    'confidence_score' => $nutritionData['confidence'] ?? $nutritionData['confidence_score'] ?? null,
                    'raw_analysis' => json_encode($nutritionData),
                    'meal_type' => $mealType,
                    'consumed_at' => $consumedAt,
                ];

                $logId = $this->foodLogModel->insert($logData);

                if (!$logId) {
                    log_message('warning', 'Failed to save food log to database');
                }
            }

            // Prepare response
            $response = [
                'id' => $logId,
                'image_path' => 'uploads/food/' . $fileName,
                'analysis' => $nutritionData,
                'meal_type' => $mealType,
                'consumed_at' => $consumedAt,
                'analyzed_at' => date('Y-m-d H:i:s')
            ];

            return sendApiResponse($response, 'Food image analyzed successfully', 201);
        } catch (\Throwable $e) {
            log_message('error', 'Food upload error: ' . $e->getMessage());

            // Clean up file if it exists
            if (isset($filePath) && file_exists($filePath)) {
                @unlink($filePath);
            }

            return sendApiResponse(null, 'Failed to process food image', 500);
        }
    }

    /**
     * Get a specific food log
     * GET /api/food-logs/{id}
     * 
     * @param int|null $id Food log ID
     * @return ResponseInterface
     */
    public function show($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$id) {
                return sendApiResponse(null, 'Food log ID is required', 400);
            }

            $log = $this->foodLogModel->where('user_id', $this->current_user_id)->find($id);

            if (!$log) {
                return sendApiResponse(null, 'Food log not found', 404);
            }

            return sendApiResponse($log, 'Food log retrieved successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Get food log error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve food log', 500);
        }
    }

    /**
     * Update a food log
     * PUT /api/food-logs/{id}
     * 
     * @param int|null $id Food log ID
     * @return ResponseInterface
     */
    public function update($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$id) {
                return sendApiResponse(null, 'Food log ID is required', 400);
            }

            // Check if log exists and belongs to user
            $log = $this->foodLogModel->where('user_id', $this->current_user_id)->find($id);
            if (!$log) {
                return sendApiResponse(null, 'Food log not found', 404);
            }

            $data = $this->request->getJSON(true);
            if (!$data) {
                return sendApiResponse(null, 'Invalid JSON data', 400);
            }

            // Prevent changing user_id
            unset($data['user_id']);

            $updated = $this->foodLogModel->update($id, $data);

            if (!$updated) {
                $errors = $this->foodLogModel->errors();
                $errorMessage = !empty($errors) ? implode(', ', $errors) : 'Failed to update food log';
                return sendApiResponse(null, $errorMessage, 400);
            }

            $updatedLog = $this->foodLogModel->find($id);

            return sendApiResponse($updatedLog, 'Food log updated successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Update food log error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to update food log', 500);
        }
    }

    /**
     * Delete a food log
     * DELETE /api/food-logs/{id}
     * 
     * @param int|null $id Food log ID
     * @return ResponseInterface
     */
    public function delete($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (!$id) {
                return sendApiResponse(null, 'Food log ID is required', 400);
            }

            // Check if log exists and belongs to user
            $log = $this->foodLogModel->where('user_id', $this->current_user_id)->find($id);
            if (!$log) {
                return sendApiResponse(null, 'Food log not found', 404);
            }

            $deleted = $this->foodLogModel->delete($id);

            if (!$deleted) {
                return sendApiResponse(null, 'Failed to delete food log', 500);
            }

            // Optionally delete the image file
            if (!empty($log['image_path'])) {
                $imagePath = WRITEPATH . $log['image_path'];
                if (file_exists($imagePath)) {
                    @unlink($imagePath);
                }
            }

            return sendApiResponse(null, 'Food log deleted successfully', 200);
        } catch (\Throwable $e) {
            log_message('error', 'Delete food log error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to delete food log', 500);
        }
    }

    /**
     * Analyze food using OpenAI Vision API
     * 
     * @param string $base64Image Base64 encoded image
     * @return array|null Nutrition data or null on failure
     */
    private function analyzeFood(string $base64Image): ?array
    {
        try {
            $client = \Config\Services::curlrequest();

            $payload = [
                'model' => $this->openAIConfig->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Analyze this food image and provide detailed nutritional information in JSON format. Include: food_name, portion_size, calories, protein (g), carbohydrates (g), fat (g), fiber (g), sugar (g), sodium (mg), and any other relevant nutrients. Also provide a confidence score (0-100) for your analysis.'
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => $base64Image
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => $this->openAIConfig->maxTokens
            ];

            $response = $client->request('POST', $this->openAIConfig->baseUrl . '/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openAIConfig->apiKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => $payload,
                'http_errors' => false // Don't throw exceptions on HTTP errors
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);

            if ($statusCode !== 200) {
                $errorMsg = $response->getBody();

                // Provide specific error messages based on status code
                switch ($statusCode) {
                    case 401:
                        log_message('error', 'OpenAI API authentication failed. Check your API key.');
                        break;
                    case 429:
                        log_message('error', 'OpenAI API rate limit exceeded or quota reached. Error: ' . $errorMsg);
                        break;
                    case 500:
                    case 503:
                        log_message('error', 'OpenAI API server error. Please try again later.');
                        break;
                    default:
                        log_message('error', 'OpenAI API error (' . $statusCode . '): ' . $errorMsg);
                }

                return null;
            }

            // Extract the response
            $content = $body['choices'][0]['message']['content'] ?? null;

            if (!$content) {
                log_message('error', 'No content in OpenAI response');
                return null;
            }

            // Try to parse JSON from the response
            // OpenAI might return markdown code blocks, so we need to extract JSON
            $jsonMatch = [];
            if (preg_match('/```json\s*(.*?)\s*```/s', $content, $jsonMatch)) {
                $content = $jsonMatch[1];
            } elseif (preg_match('/```\s*(.*?)\s*```/s', $content, $jsonMatch)) {
                $content = $jsonMatch[1];
            }

            $nutritionData = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // If not valid JSON, return as text analysis
                log_message('warning', 'OpenAI response not valid JSON, returning as text');
                return [
                    'raw_analysis' => $content,
                    'food_name' => 'Unknown',
                    'confidence' => 50,
                    'other_nutrients' => []
                ];
            }

            return $nutritionData;
        } catch (\Throwable $e) {
            log_message('error', 'OpenAI API call failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Analyze food using Clarifai Food Recognition API
     * 
     * @param string $base64Image Base64 encoded image
     * @return array|null Nutrition data or null on failure
     */
    private function analyzeFoodClarifai(string $base64Image): ?array
    {
        try {
            $client = \Config\Services::curlrequest();

            // Clarifai expects base64 without data URI prefix
            $payload = [
                'user_app_id' => [
                    'user_id' => $this->openAIConfig->clarifaiUserId,
                    'app_id' => $this->openAIConfig->clarifaiAppId
                ],
                'inputs' => [
                    [
                        'data' => [
                            'image' => [
                                'base64' => $base64Image
                            ]
                        ]
                    ]
                ]
            ];

            $url = $this->openAIConfig->clarifaiBaseUrl .
                '/models/' . $this->openAIConfig->clarifaiFoodModel . '/outputs';

            $response = $client->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Key ' . $this->openAIConfig->clarifaiApiKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => $payload,
                'http_errors' => false
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);

            if ($statusCode !== 200) {
                $errorMsg = $response->getBody();
                log_message('error', 'Clarifai API error (' . $statusCode . '): ' . $errorMsg);
                return null;
            }

            // Extract food items from Clarifai response
            $concepts = $body['outputs'][0]['data']['concepts'] ?? [];

            if (empty($concepts)) {
                log_message('warning', 'No food items detected by Clarifai');
                return [
                    'food_name' => 'Unknown Food',
                    'confidence' => 0,
                    'detected_items' => [],
                    'other_nutrients' => []
                ];
            }

            // Get the top detected food item
            $topFood = $concepts[0];
            $foodName = $topFood['name'] ?? 'Unknown Food';
            $confidence = round(($topFood['value'] ?? 0) * 100);

            // Extract all detected items
            $detectedItems = array_map(function ($concept) {
                return [
                    'name' => $concept['name'],
                    'confidence' => round($concept['value'] * 100, 2)
                ];
            }, array_slice($concepts, 0, 5)); // Top 5 items

            // Generate estimated nutrition data
            // Note: Clarifai's food model only identifies food, doesn't provide nutrition
            // You'd need to integrate with a nutrition database API (like Nutritionix or USDA)
            // For now, returning basic structure with estimated values
            $nutritionData = $this->estimateNutrition($foodName);
            $nutritionData['food_name'] = $foodName;
            $nutritionData['confidence'] = $confidence;
            $nutritionData['detected_items'] = $detectedItems;
            $nutritionData['portion_size'] = '1 serving (estimated)';

            return $nutritionData;
        } catch (\Throwable $e) {
            log_message('error', 'Clarifai API call failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Estimate nutrition based on food name
     * Simple estimation - in production, integrate with nutrition API
     * 
     * @param string $foodName Name of the food
     * @return array Estimated nutrition data
     */
    private function estimateNutrition(string $foodName): array
    {
        // Basic food categories and their typical nutritional profiles
        $profiles = [
            'rice' => ['calories' => 200, 'protein' => 4, 'carbohydrates' => 45, 'fat' => 0.5, 'fiber' => 0.6, 'sugar' => 0, 'sodium' => 5],
            'chicken' => ['calories' => 165, 'protein' => 31, 'carbohydrates' => 0, 'fat' => 3.6, 'fiber' => 0, 'sugar' => 0, 'sodium' => 74],
            'bread' => ['calories' => 265, 'protein' => 9, 'carbohydrates' => 49, 'fat' => 3.2, 'fiber' => 2.7, 'sugar' => 5, 'sodium' => 491],
            'salad' => ['calories' => 50, 'protein' => 2, 'carbohydrates' => 10, 'fat' => 0.3, 'fiber' => 3, 'sugar' => 4, 'sodium' => 25],
            'pizza' => ['calories' => 285, 'protein' => 12, 'carbohydrates' => 36, 'fat' => 10, 'fiber' => 2.5, 'sugar' => 3.8, 'sodium' => 640],
            'pasta' => ['calories' => 220, 'protein' => 8, 'carbohydrates' => 43, 'fat' => 1.3, 'fiber' => 2.5, 'sugar' => 2, 'sodium' => 6],
            'burger' => ['calories' => 354, 'protein' => 17, 'carbohydrates' => 30, 'fat' => 17, 'fiber' => 1.5, 'sugar' => 5, 'sodium' => 497],
            'fish' => ['calories' => 206, 'protein' => 22, 'carbohydrates' => 0, 'fat' => 12, 'fiber' => 0, 'sugar' => 0, 'sodium' => 90],
            'fruit' => ['calories' => 60, 'protein' => 1, 'carbohydrates' => 15, 'fat' => 0.2, 'fiber' => 2.4, 'sugar' => 12, 'sodium' => 1],
            'vegetable' => ['calories' => 35, 'protein' => 2, 'carbohydrates' => 7, 'fat' => 0.2, 'fiber' => 2.8, 'sugar' => 3.5, 'sodium' => 15],
        ];

        // Find best match
        $foodLower = strtolower($foodName);
        foreach ($profiles as $key => $profile) {
            if (strpos($foodLower, $key) !== false) {
                return $profile;
            }
        }

        // Default fallback
        return [
            'calories' => 150,
            'protein' => 5,
            'carbohydrates' => 25,
            'fat' => 5,
            'fiber' => 2,
            'sugar' => 3,
            'sodium' => 100,
            'other_nutrients' => []
        ];
    }

    /**
     * Analyze food using mock data (for testing without API)
     * 
     * @param string $fileName File name to generate mock data
     * @return array Mock nutrition data
     */
    private function analyzeFoodMock(string $fileName): array
    {
        // Generate realistic mock data based on file name or random selection
        $mockFoods = [
            [
                'food_name' => 'Grilled Chicken Salad',
                'portion_size' => '1 serving (300g)',
                'calories' => 250,
                'protein' => 35,
                'carbohydrates' => 12,
                'fat' => 8,
                'fiber' => 4,
                'sugar' => 6,
                'sodium' => 350,
                'confidence' => 92
            ],
            [
                'food_name' => 'Margherita Pizza',
                'portion_size' => '2 slices (180g)',
                'calories' => 540,
                'protein' => 22,
                'carbohydrates' => 68,
                'fat' => 18,
                'fiber' => 4,
                'sugar' => 8,
                'sodium' => 1200,
                'confidence' => 88
            ],
            [
                'food_name' => 'Chicken Fried Rice',
                'portion_size' => '1 bowl (400g)',
                'calories' => 450,
                'protein' => 28,
                'carbohydrates' => 55,
                'fat' => 12,
                'fiber' => 3,
                'sugar' => 4,
                'sodium' => 800,
                'confidence' => 85
            ],
            [
                'food_name' => 'Fresh Fruit Salad',
                'portion_size' => '1 cup (200g)',
                'calories' => 120,
                'protein' => 2,
                'carbohydrates' => 30,
                'fat' => 0.5,
                'fiber' => 5,
                'sugar' => 24,
                'sodium' => 5,
                'confidence' => 90
            ],
            [
                'food_name' => 'Cheeseburger with Fries',
                'portion_size' => '1 serving',
                'calories' => 850,
                'protein' => 35,
                'carbohydrates' => 75,
                'fat' => 45,
                'fiber' => 6,
                'sugar' => 12,
                'sodium' => 1400,
                'confidence' => 94
            ]
        ];

        // Select a random food item for variety
        $selectedFood = $mockFoods[array_rand($mockFoods)];

        // Add timestamp and additional fields
        $selectedFood['analyzed_at'] = date('Y-m-d H:i:s');
        $selectedFood['analysis_mode'] = 'mock';
        $selectedFood['other_nutrients'] = [];

        log_message('info', 'Mock food analysis: ' . $selectedFood['food_name']);

        return $selectedFood;
    }
}
