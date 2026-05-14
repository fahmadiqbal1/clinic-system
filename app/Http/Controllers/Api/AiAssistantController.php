<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiActionRequest;
use App\Models\PlatformSetting;
use App\Services\AiSidecarClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AiAssistantController extends Controller
{
    // Keywords that signal a persona-routed query rather than a document search
    private const FINANCIAL_KEYWORDS = [
        'revenue', 'income', 'profit', 'discount', 'billing', 'invoice',
        'payment', 'expense', 'salary', 'payout', 'commission', 'strategy',
        'improve', 'increase', 'grow', 'analyse', 'analyze', 'performance',
        'anomaly', 'fraud', 'overcharge', 'refund', 'cancellation',
    ];

    private const CLINICAL_KEYWORDS = [
        'diagnosis', 'diagnose', 'symptom', 'treatment', 'medication',
        'prescription', 'patient', 'vitals', 'blood pressure', 'temperature',
        'second opinion', 'differential', 'missing', 'condition', 'disease',
        'drug', 'dose', 'therapy', 'test', 'lab result', 'radiology',
    ];

    private const STOCK_KEYWORDS = [
        'stock', 'inventory', 'medicine', 'drug', 'supply', 'reorder',
        'procurement', 'purchase', 'order', 'shortage', 'out of stock',
        'expiry', 'batch', 'vendor', 'supplier', 'quantity',
    ];

    public function query(Request $request, AiSidecarClient $client): JsonResponse
    {
        $user = Auth::user();
        $role = strtolower($user?->getRoleNames()->first() ?? 'unknown');

        if (!PlatformSetting::isEnabled("ai.chat.enabled.{$role}")) {
            return response()->json(['error' => 'AI assistant not enabled for your role.'], 403);
        }

        $validated = $request->validate([
            'query'      => ['required', 'string', 'min:3', 'max:1000'],
            'collection' => ['sometimes', 'string', 'in:general,service_catalog,inventory'],
        ]);

        $query = $validated['query'];

        // Audit every chat query
        Log::channel('single')->info('ai_chat_query', [
            'user_id' => $user?->id,
            'role'    => $role,
            'query'   => $query,
        ]);

        // Determine routing: persona call or RAGFlow knowledge search
        $route = $this->resolveRoute($role, $query);

        try {
            if ($route === 'admin') {
                return $this->routeToAdminPersona($client, $query, $user);
            }

            if ($route === 'clinical') {
                return $this->routeToClinicalPersona($client, $query, $user);
            }

            if ($route === 'ops') {
                return $this->routeToOpsPersona($client, $query, $user);
            }

            // Default: RAGFlow knowledge search
            $collection = $validated['collection'] ?? $this->defaultCollection($role);
            $result = $client->ragQuery($query, $collection);
            $result['route'] = 'knowledge';
            return response()->json($result);

        } catch (\RuntimeException $e) {
            return response()->json([
                'answer'    => null,
                'citations' => [],
                'route'     => $route,
                'error'     => 'AI assistant temporarily unavailable.',
            ], 503);
        }
    }

    public function flag(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query'      => ['required', 'string', 'max:1000'],
            'answer'     => ['required', 'string', 'max:5000'],
            'citations'  => ['sometimes', 'array'],
        ]);

        AiActionRequest::create([
            'case_token'          => null,
            'requested_by_source' => 'ai_assistant',
            'target_type'         => 'KnowledgeQuery',
            'target_id'           => 0,
            'proposed_action'     => 'owner_review',
            'proposed_payload'    => [
                'query'              => $validated['query'],
                'answer'             => $validated['answer'],
                'citations'          => $validated['citations'] ?? [],
                'flagged_by_user_id' => Auth::id(),
            ],
            'status' => 'pending',
        ]);

        return response()->json(['flagged' => true]);
    }

    // ── Routing logic ─────────────────────────────────────────────────────────

    private function resolveRoute(string $role, string $query): string
    {
        $lower = strtolower($query);

        if ($role === 'owner') {
            if ($this->matchesKeywords($lower, self::FINANCIAL_KEYWORDS)) {
                return 'admin';
            }
            if ($this->matchesKeywords($lower, self::STOCK_KEYWORDS)) {
                return 'ops';
            }
        }

        if (in_array($role, ['doctor']) && $this->matchesKeywords($lower, self::CLINICAL_KEYWORDS)) {
            return 'clinical';
        }

        if (in_array($role, ['pharmacy', 'laboratory', 'radiology'])
            && $this->matchesKeywords($lower, self::STOCK_KEYWORDS)) {
            return 'ops';
        }

        return 'knowledge';
    }

    private function matchesKeywords(string $text, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (str_contains($text, $kw)) {
                return true;
            }
        }
        return false;
    }

    // ── Persona proxies ───────────────────────────────────────────────────────

    private function routeToAdminPersona(AiSidecarClient $client, string $query, $user): JsonResponse
    {
        if (!PlatformSetting::isEnabled('ai.admin.enabled')) {
            return $this->personaDisabledFallback('admin intelligence');
        }
        $result = $client->adminAnalyse([
            'query_type'    => 'revenue_anomaly',
            'free_text'     => $query,
            'session_token' => (string) $user?->id,
        ]);
        return response()->json(array_merge($result, ['route' => 'admin']));
    }

    private function routeToClinicalPersona(AiSidecarClient $client, string $query, $user): JsonResponse
    {
        if (!PlatformSetting::isEnabled('ai.sidecar.enabled')) {
            return $this->personaDisabledFallback('clinical AI');
        }
        // Clinical chat uses RAGFlow catalog + user question framed as a consultation note
        $result = $client->ragQuery($query, 'service_catalog');
        return response()->json(array_merge($result, ['route' => 'clinical']));
    }

    private function routeToOpsPersona(AiSidecarClient $client, string $query, $user): JsonResponse
    {
        if (!PlatformSetting::isEnabled('ai.ops.enabled')) {
            return $this->personaDisabledFallback('operations AI');
        }
        $result = $client->opsAnalyse([
            'query_type'    => 'inventory_velocity',
            'free_text'     => $query,
            'session_token' => (string) $user?->id,
        ]);
        return response()->json(array_merge($result, ['route' => 'ops']));
    }

    private function personaDisabledFallback(string $persona): JsonResponse
    {
        return response()->json([
            'answer'    => "The {$persona} feature is not enabled. Please ask the owner to enable it in AI Settings.",
            'citations' => [],
            'route'     => 'fallback',
        ]);
    }

    private function defaultCollection(string $role): string
    {
        return match ($role) {
            'doctor'                     => 'service_catalog',
            'laboratory', 'radiology'    => 'service_catalog',
            'pharmacy'                   => 'inventory',
            default                      => 'general',
        };
    }
}
