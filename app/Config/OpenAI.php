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

    // Food Analysis Mode
    public string $foodAnalysisMode;

    public function __construct()
    {
        parent::__construct();
        $this->apiKey = env('OPENAI_API_KEY', '');
        $this->clarifaiApiKey = env('CLARIFAI_API_KEY', '');
        $this->foodAnalysisMode = env('FOOD_ANALYSIS_MODE', 'mock');
    }
}
