<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies that inbound requests from the Python sidecar carry a valid
 * HS256 JWT signed with CLINIC_SIDECAR_JWT_SECRET.
 *
 * This is used for internal-only endpoints (e.g. /api/internal/procurement/draft)
 * that should never be reachable from browser clients.
 */
class VerifySidecarJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if (!$token) {
            return response()->json(['error' => 'Missing sidecar JWT.'], 401);
        }

        $secret = config('services.sidecar.jwt_secret');
        if (!$secret) {
            return response()->json(['error' => 'Sidecar JWT secret not configured.'], 500);
        }

        if (!$this->verifyToken($token, $secret)) {
            return response()->json(['error' => 'Invalid sidecar JWT.'], 401);
        }

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }

    private function verifyToken(string $token, string $secret): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$header, $payload, $signature] = $parts;

        $expected = rtrim(strtr(base64_encode(
            hash_hmac('sha256', "{$header}.{$payload}", $secret, true)
        ), '+/', '-_'), '=');

        return hash_equals($expected, $signature);
    }
}
