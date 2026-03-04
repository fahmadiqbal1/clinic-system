<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Hugging Face API Key
    |--------------------------------------------------------------------------
    |
    | MedGemma is accessed via the Hugging Face Inference API.
    | Get a free API key at https://huggingface.co/settings/tokens
    |
    */
    'api_key' => env('HUGGINGFACE_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Model Identifier
    |--------------------------------------------------------------------------
    |
    | The MedGemma model to use on Hugging Face Inference API.
    |
    */
    'model' => env('MEDGEMMA_MODEL', 'google/medgemma-4b-it'),

    /*
    |--------------------------------------------------------------------------
    | API Base URL
    |--------------------------------------------------------------------------
    */
    'api_url' => env('MEDGEMMA_API_URL', 'https://router.huggingface.co/hf-inference/models/'),
];
