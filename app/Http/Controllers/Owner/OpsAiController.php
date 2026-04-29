<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\AiSidecarClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpsAiController extends Controller
{
    public function __construct(private readonly AiSidecarClient $sidecar) {}

    public function index(): View
    {
        abort_unless(PlatformSetting::isEnabled('ai.ops.enabled'), 404);
        return view('owner.ops-ai', [
            'title' => 'Operations AI',
        ]);
    }

    public function analyse(Request $request): JsonResponse
    {
        if (!PlatformSetting::isEnabled('ai.ops.enabled')) {
            return response()->json(['error' => 'Operations AI is disabled.'], 403);
        }

        $validated = $request->validate([
            'domain'          => ['sometimes', 'string', 'in:inventory,procurement,expense,queue,general'],
            'period_days'     => ['sometimes', 'integer', 'min:1', 'max:365'],
            'custom_question' => ['sometimes', 'string', 'max:1000'],
        ]);

        $payload = [
            'session_token'   => bin2hex(random_bytes(32)),
            'domain'          => $validated['domain'] ?? 'general',
            'period_days'     => $validated['period_days'] ?? 30,
            'custom_question' => $validated['custom_question'] ?? null,
        ];

        try {
            return response()->json($this->sidecar->opsAnalyse($payload));
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => 'Operations AI temporarily unavailable.',
            ], 503);
        }
    }
}
