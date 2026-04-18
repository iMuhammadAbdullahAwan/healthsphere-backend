<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class OpenAI extends BaseConfig
{
    public string $apiKey;
    public string $model = 'gpt-4o'; // Latest model with vision capabilities
    public string $baseUrl = 'https://api.openai.com/v1';
    public int $maxTokens = 500;

    // Clarifai Configuration
    public string $clarifaiApiKey;
    public string $clarifaiBaseUrl = 'https://api.clarifai.com/v2';
    public string $clarifaiFoodModel = 'food-item-recognition';
    public string $clarifaiUserId = 'clarifai';
    public string $clarifaiAppId = 'main';

    // Gemini Configuration
    public string $geminiApiKey;
    public string $geminiModel = 'gemini-2.0-flash-lite';
    public string $geminiBaseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    // LogMeal Configuration
    public string $logMealToken;
    public string $logMealBaseUrl = 'https://api.logmeal.com/v2';

    // Food Analysis Mode
    public string $foodAnalysisMode;

    public function __construct()
    {
        parent::__construct();
        $this->apiKey = env('OPENAI_API_KEY', '');
        $this->clarifaiApiKey = env('CLARIFAI_API_KEY', '');
        $this->geminiApiKey = env('Gemini_API_KEY', '');
        $this->logMealToken = env('LOGMEAL_API_TOKEN', '');
        $this->foodAnalysisMode = env('FOOD_ANALYSIS_MODE', 'gemini');
    }
}
