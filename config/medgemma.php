<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Provider
    |--------------------------------------------------------------------------
    |
    | Choose 'ollama' for a free local deployment or 'huggingface' for the
    | cloud-based Hugging Face Inference API.
    |
    */
    'provider' => env('MEDGEMMA_PROVIDER', 'ollama'),

    /*
    |--------------------------------------------------------------------------
    | Hugging Face API Key
    |--------------------------------------------------------------------------
    |
    | Only required when the provider is 'huggingface'.
    | Get a free API key at https://huggingface.co/settings/tokens
    |
    */
    'api_key' => env('HUGGINGFACE_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Model Identifier
    |--------------------------------------------------------------------------
    |
    | Ollama: 'medgemma3:4b' (after running `ollama pull medgemma3:4b`)
    | Hugging Face: 'google/medgemma-4b-it'
    |
    */
    'model' => env('MEDGEMMA_MODEL', 'medgemma3:4b'),

    /*
    |--------------------------------------------------------------------------
    | API Base URL
    |--------------------------------------------------------------------------
    |
    | Ollama: 'http://localhost:11434'
    | Hugging Face: 'https://router.huggingface.co/hf-inference/models/'
    |
    */
    'api_url' => env('MEDGEMMA_API_URL', 'http://localhost:11434'),
];
