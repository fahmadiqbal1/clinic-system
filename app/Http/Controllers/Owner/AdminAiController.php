<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\AiSidecarClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminAiController extends Controller
{
    public function __construct(private readonly AiSidecarClient $sidecar) {}

    public function index(): View
    {
        abort_unless(PlatformSetting::isEnabled('ai.admin.enabled'), 404);
        return view('owner.admin-ai', [
            'title' => 'Administrative AI',
        ]);
    }

    public function analyse(Request $request): JsonResponse
    {
        if (!PlatformSetting::isEnabled('ai.admin.enabled')) {
            return response()->json(['error' => 'Administrative AI is disabled.'], 403);
        }

        $validated = $request->validate([
            'query_type'      => ['sometimes', 'string', 'in:revenue_anomaly,discount_risk,fbr_status,payout_audit,general'],
            'period_days'     => ['sometimes', 'integer', 'min:1', 'max:365'],
            'custom_question' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $payload = [
            'session_token'   => bin2hex(random_bytes(32)),
            'query_type'      => $validated['query_type'] ?? 'general',
            'period_days'     => $validated['period_days'] ?? 7,
            'custom_question' => $validated['custom_question'] ?? null,
        ];

        try {
            return response()->json($this->sidecar->adminAnalyse($payload));
        } catch (\Throwable $e) {
            $raw = $e->getMessage();
            if (str_contains($raw, 'circuit open')) {
                $msg = 'Administrative AI temporarily unavailable.';
            } elseif (str_contains($raw, 'Connection refused') || str_contains($raw, 'Failed to connect') || str_contains($raw, 'cURL error')) {
                $msg = 'AI sidecar is not running (localhost:8001 unreachable). Start it with: docker compose -f docker-compose.yml -f docker-compose.ai.yml up -d sidecar — or use Ollama locally if running natively.';
            } elseif (str_contains($raw, 'timed out') || str_contains($raw, 'timeout')) {
                $msg = 'AI sidecar timed out. The model may be loading — please retry in 30 seconds.';
            } else {
                $msg = 'Administrative AI is unavailable: ' . Str::limit($raw, 120);
            }
            return response()->json(['error' => $msg], 503);
        }
    }
}
