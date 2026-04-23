<?php

return [
    /*
     * HMAC secret used by CaseTokenService to derive deterministic patient pseudonyms.
     * Must be set in .env as CLINIC_CASE_TOKEN_SECRET before Phase 0 migrations run.
     * Generate with: php -r "echo base64_encode(random_bytes(32));"
     */
    'case_token_secret' => env('CLINIC_CASE_TOKEN_SECRET', ''),

    /*
     * URL of the Python AI sidecar (Phase 2).
     * Set CLINIC_SIDECAR_URL in .env; defaults to localhost when sidecar is not running.
     */
    'sidecar_url' => env('CLINIC_SIDECAR_URL', 'http://localhost:8001'),

    /*
     * HS256 JWT secret shared between Laravel (mints) and the Python sidecar (verifies).
     * Generate with: php -r "echo base64_encode(random_bytes(32));"
     */
    'sidecar_jwt_secret' => env('CLINIC_SIDECAR_JWT_SECRET', ''),
];
