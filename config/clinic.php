<?php

return [
    /*
     * HMAC secret used by CaseTokenService to derive deterministic patient pseudonyms.
     * Must be set in .env as CLINIC_CASE_TOKEN_SECRET before Phase 0 migrations run.
     * Generate with: php artisan key:generate --show (use a separate random value, not APP_KEY)
     */
    'case_token_secret' => env('CLINIC_CASE_TOKEN_SECRET', ''),
];
