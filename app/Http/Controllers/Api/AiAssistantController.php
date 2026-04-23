<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiActionRequest;
use App\Models\PlatformSetting;
use App\Services\AiSidecarClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiAssistantController extends Controller
{
    public function query(Request $request, AiSidecarClient $client): JsonResponse
    {
        $role = strtolower(Auth::user()?->getRoleNames()->first() ?? 'unknown');

        if (!PlatformSetting::isEnabled("ai.chat.enabled.{$role}")) {
            return response()->json(['error' => 'AI assistant not enabled for your role.'], 403);
        }

        $validated = $request->validate([
            'query'      => ['required', 'string', 'min:3', 'max:1000'],
            'collection' => ['sometimes', 'string', 'in:general,service_catalog,inventory'],
        ]);

        $collection = $validated['collection'] ?? $this->defaultCollection($role);

        try {
            $result = $client->ragQuery($validated['query'], $collection);
            return response()->json($result);
        } catch (\RuntimeException $e) {
            // Circuit open or sidecar down — degrade gracefully
            return response()->json([
                'answer'    => null,
                'citations' => [],
                'error'     => 'Knowledge assistant temporarily unavailable.',
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
            'target_id'           => 0,  // No entity target for knowledge queries; 0 is the sentinel
            'proposed_action'     => 'owner_review',
            'proposed_payload'    => [
                'query'     => $validated['query'],
                'answer'    => $validated['answer'],
                'citations' => $validated['citations'] ?? [],
                'flagged_by_user_id' => Auth::id(),
            ],
            'status' => 'pending',
        ]);

        return response()->json(['flagged' => true]);
    }

    private function defaultCollection(string $role): string
    {
        return match ($role) {
            'doctor'    => 'service_catalog',
            'laboratory', 'radiology' => 'service_catalog',
            'pharmacy'  => 'inventory',
            default     => 'general',
        };
    }
}
