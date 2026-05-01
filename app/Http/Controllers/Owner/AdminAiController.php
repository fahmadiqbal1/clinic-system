<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\AiSidecarClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            $msg = str_contains($e->getMessage(), 'circuit open')
                ? 'Administrative AI temporarily unavailable.'
                : 'Administrative AI error: ' . $e->getMessage();
            return response()->json(['error' => $msg], 503);
        }
    }
}
