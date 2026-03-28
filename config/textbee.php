<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TextBee SMS Gateway Configuration
    | https://textbee.dev — free, open-source Android SMS gateway
    |--------------------------------------------------------------------------
    */
    'api_key'   => env('TEXTBEE_API_KEY', ''),
    'device_id' => env('TEXTBEE_DEVICE_ID', ''), // Get from https://app.textbee.dev
    'base_url'  => 'https://api.textbee.dev/api/v1',
];
