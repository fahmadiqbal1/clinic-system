<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives webhook events from NocoBase and writes them into audit_logs.
 *
 * Auth: HMAC-SHA256 of the raw request body, keyed by CLINIC_NOCOBASE_WEBHOOK_SECRET.
 * Sent by NocoBase as: X-NocoBase-Signature: sha256=<hex>
 *
 * Route is outside Sanctum — NocoBase has no Laravel session or token.
 * Spatie role enforcement is physical: NocoBase runs on 127.0.0.1:13000 (not internet-accessible)
 * and only Owner credentials grant access to that UI.
 */
class NocobaseAuditHookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        if (! $this->signatureValid($request)) {
            Log::warning('nocobase.audit-hook: invalid HMAC signature', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all();

        $table  = $payload['table']  ?? null;
        $event  = $payload['event']  ?? null;

        if (! $table || ! $event) {
            return response()->json(['error' => 'Missing table or event'], 422);
        }

        $action = 'nocobase.' . $table . '.' . $event;

        AuditLog::log(
            action:        $action,
            auditableType: 'Nocobase',
            auditableId:   (int) ($payload['record']['id'] ?? 0),
            beforeState:   $payload['old_record'] ?? null,
            afterState:    $payload['record'] ?? null,
            userId:        null,
            ipAddress:     $request->ip(),
        );

        return response()->json(['status' => 'logged']);
    }

    private function signatureValid(Request $request): bool
    {
        $secret = config('clinic.nocobase_webhook_secret', '');

        if ($secret === '') {
            Log::error('nocobase.audit-hook: CLINIC_NOCOBASE_WEBHOOK_SECRET is not set');
            return false;
        }

        $header = $request->header('X-NocoBase-Signature', '');

        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $received = substr($header, 7);
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $received);
    }
}
