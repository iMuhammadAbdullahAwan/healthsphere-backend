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
     * Log a meal as eaten
     * POST /api/food-logs
     * 
     * @return ResponseInterface
     */
    public function create(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $validationRules = [
                'food_name' => 'required|string|max_length[255]',
                'calories' => 'required|numeric',
                'protein' => 'permit_empty|numeric',
                'carbohydrates' => 'permit_empty|numeric',
                'fat' => 'permit_empty|numeric',
                'meal_type' => 'permit_empty|in_list[breakfast,lunch,dinner,snack]',
            ];

            if (!$this->validate($validationRules)) {
                return sendApiResponse(null, 'Validation failed: ' . json_encode($this->validator->getErrors()), 400);
            }

            $data = [
                'user_id'          => $this->current_user_id,
                'food_name'        => $this->request->getVar('food_name'),
                'image_path'       => $this->request->getVar('image_path'),
                'portion_size'     => $this->request->getVar('portion_size') ?? 'Standard portion',
                'calories'         => $this->request->getVar('calories'),
                'protein'          => $this->request->getVar('protein') ?? 0,
                'carbohydrates'    => $this->request->getVar('carbohydrates') ?? 0,
                'fat'              => $this->request->getVar('fat') ?? 0,
                'fiber'            => $this->request->getVar('fiber') ?? 0,
                'sugar'            => $this->request->getVar('sugar') ?? 0,
                'sodium'           => $this->request->getVar('sodium') ?? 0,
                'confidence_score' => $this->request->getVar('confidence_score'),
                'raw_analysis'     => $this->request->getVar('raw_analysis'),
                'meal_type'        => $this->request->getVar('meal_type') ?? 'snack',
                'consumed_at'      => $this->request->getVar('consumed_at') ?? date('Y-m-d H:i:s'),
            ];

            $logId = $this->foodLogModel->insert($data);

            if (!$logId) {
                return sendApiResponse(null, 'Failed to log food entry', 500);
            }

            // Return the created log
            $createdLog = $this->foodLogModel->find($logId);

            return sendApiResponse($createdLog, 'Meal logged successfully', 201);
        } catch (\Throwable $e) {
            log_message('error', 'Log eat error: ' . $e->getMessage());
            return sendApiResponse(null, 'An error occurred while logging the meal', 500);
        }
    }

    /**
     * Get food logs with optional search, filters, and pagination
     * GET /api/food-logs?search=chicken&meal_type=lunch&page=1&limit=20
     */
    public function index(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $search = $this->request->getVar('search');
            $mealType = $this->request->getVar('meal_type');
            $startDate = $this->request->getVar('start_date');
            $endDate = $this->request->getVar('end_date');
            $page = $this->request->getVar('page') ?? 1;
            $limit = $this->request->getVar('limit') ?? 20;
            $offset = ($page - 1) * $limit;

            $query = $this->foodLogModel->where('user_id', $this->current_user_id);

            if ($search) {
                $query->like('food_name', $search);
            }
            if ($mealType) {
                $query->where('meal_type', $mealType);
            }
            if ($startDate) {
                $query->where('consumed_at >=', $startDate . ' 00:00:00');
            }
            if ($endDate) {
                $query->where('consumed_at <=', $endDate . ' 23:59:59');
            }

            $total = (clone $query)->countAllResults();
            $logs = $query->orderBy('consumed_at', 'DESC')->findAll($limit, $offset);

            return sendApiResponse([
                'logs' => $logs,
                'pagination' => [
                    'total' => $total,
                    'page' => (int)$page,
                    'limit' => (int)$limit,
                    'pages' => ceil($total / $limit)
                ]
            ], 'Food logs retrieved successfully');
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
     * Get food recommendations based on user profile and history
     * GET /api/food-logs/recommendations
     * 
     * @return ResponseInterface
     */
    public function recommendations(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            if (empty($this->openAIConfig->logMealToken)) {
                return sendApiResponse(null, 'LogMeal service not configured', 500);
            }

            $client = \Config\Services::curlrequest();

            // Sync user profile to LogMeal for better recommendations
            $userModel = new \App\Models\UserModel();
            $user = $userModel->find($this->current_user_id);
            
            if ($user) {
                // Map gender to ISO/IEC 5218
                $sex = match ($user['gender'] ?? '') {
                    'male' => 1,
                    'female' => 2,
                    default => 0
                };

                $client->request('POST', $this->openAIConfig->logMealBaseUrl . '/profile/modifyUserProfileInfo', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->openAIConfig->logMealToken,
                        'Content-Type'  => 'application/json'
                    ],
                    'json' => [
                        'first_name' => explode(' ', $user['full_name'] ?? 'User')[0],
                        'sex' => $sex,
                        'date_of_birth' => $user['date_of_birth'] ?? null,
                    ],
                    'http_errors' => false
                ]);
            }

            // Get recommendations
            $response = $client->request('GET', $this->openAIConfig->logMealBaseUrl . '/recommend/dish', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openAIConfig->logMealToken
                ],
                'http_errors' => false,
                'timeout' => 30
            ]);

            if ($response->getStatusCode() !== 200) {
                log_message('error', 'LogMeal Recommendation failed: ' . $response->getBody());
                return sendApiResponse(null, 'Failed to fetch recommendations', 500);
            }

            $recommendations = json_decode($response->getBody(), true);

            return sendApiResponse($recommendations, 'Food recommendations retrieved successfully');
        } catch (\Throwable $e) {
            log_message('error', 'Recommendations error: ' . $e->getMessage());
            return sendApiResponse(null, 'An error occurred while fetching recommendations', 500);
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
            // Increase execution time for AI analysis
            set_time_limit(180);

            // Validate authentication
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            // Determine analysis mode (allow override from request)
            $mode = $this->request->getPost('analysis_type') ?? $this->openAIConfig->foodAnalysisMode;

            // Validate API key based on mode
            if ($mode === 'clarifai' && empty($this->openAIConfig->clarifaiApiKey)) {
                log_message('error', 'Clarifai API key not configured');
                return sendApiResponse(null, 'Clarifai service not configured', 500);
            }

            if ($mode === 'openai' && empty($this->openAIConfig->apiKey)) {
                log_message('error', 'OpenAI API key not configured');
                return sendApiResponse(null, 'OpenAI service not configured', 500);
            }

            if ($mode === 'gemini' && empty($this->openAIConfig->geminiApiKey)) {
                log_message('error', 'Gemini API key not configured');
                return sendApiResponse(null, 'Gemini service not configured', 500);
            }

            if ($mode === 'logmeal' && empty($this->openAIConfig->logMealToken)) {
                log_message('error', 'LogMeal API token not configured');
                return sendApiResponse(null, 'LogMeal service not configured', 500);
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

            // Move file to public/uploads/food directory
            $uploadPath = rtrim(FCPATH, '\\/') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'food' . DIRECTORY_SEPARATOR;
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            $fileName = $file->getRandomName();
            $file->move($uploadPath, $fileName);
            $filePath = $uploadPath . $fileName;

            // Convert image to base64
            $imageData = base64_encode(file_get_contents($filePath));

            switch ($mode) {
                case 'openai':
                    $base64Image = "data:{$mimeType};base64,{$imageData}";
                    $nutritionData = $this->analyzeFood($base64Image);
                    break;

                case 'clarifai':
                    $nutritionData = $this->analyzeFoodClarifai($imageData);
                    break;

                case 'gemini':
                    $nutritionData = $this->analyzeFoodGemini($mimeType, $imageData);
                    break;

                case 'logmeal':
                    $nutritionData = $this->analyzeFoodLogMeal($filePath, $mimeType, $fileName);
                    break;

                default:
                    return sendApiResponse(null, 'Invalid or unsupported analysis mode: ' . $mode, 400);
            }

            if (!$nutritionData) {
                // Clean up file on error
                @unlink($filePath);

                $errorMsg = 'Failed to analyze food image. ';
                $errorMsg .= 'Mode: ' . $mode . '. Check server logs for details.';

                return sendApiResponse(null, $errorMsg, 500);
            }

            // Return only the analysis result
            return sendApiResponse([
                'image_path' => 'uploads/food/' . $fileName,
                'analysis' => $nutritionData,
                'analyzed_at' => date('Y-m-d H:i:s')
            ], 'Food image analyzed successfully', 200);
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
                $imagePath = rtrim(FCPATH, '\\/') . DIRECTORY_SEPARATOR . ltrim($log['image_path'], '\\/');
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
                'http_errors' => false, // Don't throw exceptions on HTTP errors
                'timeout' => 120
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
                'http_errors' => false,
                'timeout' => 120
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
     * Analyze food using Gemini 1.5 Flash API
     * 
     * @param string $mimeType Image MIME type
     * @param string $base64Image Base64 encoded image (without prefix)
     * @return array|null Nutrition data or null on failure
     */
    private function analyzeFoodGemini(string $mimeType, string $base64Image): ?array
    {
        try {
            $client = \Config\Services::curlrequest();

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => 'Analyze this food image and provide detailed nutritional information in JSON format. Return only a JSON object with these keys: food_name, portion_size, calories, protein, carbohydrates, fat, fiber, sugar, sodium, confidence_score. All nutrient values should be numbers (in grams/mg as appropriate), confidence_score should be 0-100. If multiple food items are present, provide the combined nutritional value for the whole meal shown.'
                            ],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $base64Image
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $url = $this->openAIConfig->geminiBaseUrl . '/' . $this->openAIConfig->geminiModel . ':generateContent?key=' . $this->openAIConfig->geminiApiKey;

            $response = $client->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'json' => $payload,
                'http_errors' => false,
                'timeout' => 120
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);

            if ($statusCode !== 200) {
                log_message('error', 'Gemini API error (' . $statusCode . '): ' . $response->getBody());
                return null;
            }

            $content = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$content) {
                log_message('error', 'No content in Gemini response');
                return null;
            }

            $nutritionData = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                log_message('error', 'Failed to parse JSON from Gemini response');
                return null;
            }

            return $nutritionData;
        } catch (\Throwable $e) {
            log_message('error', 'Gemini API call failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Analyze food using LogMeal API
     * 
     * @param string $filePath Path to the image file
     * @param string $mimeType MIME type of the image
     * @param string $fileName Original filename
     * @return array|null Nutrition data or null on failure
     */
    private function analyzeFoodLogMeal(string $filePath, string $mimeType, string $fileName): ?array
    {
        try {
            $client = \Config\Services::curlrequest();

            // Step 1: Image Segmentation (upload image)
            $response = $client->request('POST', $this->openAIConfig->logMealBaseUrl . '/image/segmentation/complete', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openAIConfig->logMealToken
                ],
                'multipart' => [
                    'image' => new \CURLFile($filePath, $mimeType, $fileName)
                ],
                'http_errors' => false,
                'timeout' => 120
            ]);

            if ($response->getStatusCode() !== 200) {
                log_message('error', 'LogMeal Step 1 failed: ' . $response->getBody());
                return null;
            }

            $body1 = json_decode($response->getBody(), true);
            $imageId = $body1['imageId'] ?? null;

            if (!$imageId) {
                log_message('error', 'LogMeal failed to return imageId');
                return null;
            }

            // Step 2: Get Nutritional Info
            $response = $client->request('POST', $this->openAIConfig->logMealBaseUrl . '/nutrition/recipe/nutritionalInfo', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openAIConfig->logMealToken,
                    'Content-Type'  => 'application/json'
                ],
                'json' => [
                    'imageId' => $imageId
                ],
                'http_errors' => false,
                'timeout' => 120
            ]);

            if ($response->getStatusCode() !== 200) {
                log_message('error', 'LogMeal Step 2 failed: ' . $response->getBody());
                return null;
            }

            $body2 = json_decode($response->getBody(), true);
            
            // Map LogMeal response to our nutritionData structure
            $nutrients = $body2['nutritional_info'] ?? [];
            
            return [
                'food_name' => $body2['foodName'] ?? (is_array($body2['food_names'] ?? null) ? $body2['food_names'][0] : 'Unknown Food'),
                'portion_size' => 'Standard portion',
                'calories' => $nutrients['calories'] ?? 0,
                'protein' => $nutrients['total_nutrients']['protein']['quantity'] ?? ($nutrients['protein'] ?? 0),
                'carbohydrates' => $nutrients['total_nutrients']['chocdf']['quantity'] ?? ($nutrients['carbohydrates'] ?? 0),
                'fat' => $nutrients['total_nutrients']['fat']['quantity'] ?? ($nutrients['fat'] ?? 0),
                'fiber' => $nutrients['total_nutrients']['fibtg']['quantity'] ?? ($nutrients['fiber'] ?? 0),
                'sugar' => $nutrients['total_nutrients']['sugar']['quantity'] ?? ($nutrients['sugar'] ?? 0),
                'sodium' => $nutrients['total_nutrients']['na']['quantity'] ?? ($nutrients['sodium'] ?? 0),
                'confidence_score' => isset($body1['recognition_results'][0]['prob']) ? round($body1['recognition_results'][0]['prob'] * 100) : 100,
                
                // Extra Free Features from LogMeal
                'food_type' => $body1['recognition_results'][0]['food_type'] ?? null,
                'food_family' => $body1['recognition_results'][0]['food_family'] ?? null,
                'nutritional_score' => $body2['nutritional_score'] ?? null, // A, B, C, D, E
                'intake_reference' => $body2['dailyIntakeReference'] ?? null, // LOW, MEDIUM, HIGH
                
                'raw_logmeal_response' => $body2
            ];
        } catch (\Throwable $e) {
            log_message('error', 'LogMeal API call failed: ' . $e->getMessage());
            return null;
        }
    }
}
