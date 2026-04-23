<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\AiActionRequest;
use App\Models\AiInvocation;
use App\Models\PlatformSetting;
use App\Services\AiSidecarClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class AiOversightController extends Controller
{
    public function index(Request $request, AiSidecarClient $client): View
    {
        $sidecarEnabled = PlatformSetting::isEnabled('ai.sidecar.enabled');
        $ragflowEnabled = PlatformSetting::isEnabled('ai.ragflow.enabled');

        $sidecarStatus = $this->pingSidecar($client);
        $pendingAiRequests = AiActionRequest::where('status', 'pending')->count();
        $recentInvocations = AiInvocation::orderByDesc('created_at')->take(20)->get();

        $topCitedDocs = AiInvocation::query()
            ->whereNotNull('retrieval_doc_ids')
            ->where('created_at', '>=', now()->subDays(7))
            ->get()
            ->flatMap(fn ($inv) => $inv->retrieval_doc_ids ?? [])
            ->countBy()
            ->sortDesc()
            ->take(5);

        $lastInvocation = AiInvocation::orderByDesc('created_at')->first();

        return view('owner.ai-oversight', compact(
            'sidecarEnabled',
            'ragflowEnabled',
            'sidecarStatus',
            'pendingAiRequests',
            'recentInvocations',
            'topCitedDocs',
            'lastInvocation',
        ));
    }

    private function pingSidecar(AiSidecarClient $client): array
    {
        if ($client->isCircuitOpen()) {
            return ['status' => 'circuit_open', 'message' => 'Circuit breaker is open.'];
        }

        $url = rtrim(config('clinic.sidecar_url', 'http://localhost:8001'), '/') . '/health';

        try {
            $response = Http::timeout(3)->get($url);
            if ($response->successful()) {
                return ['status' => 'ok', 'data' => $response->json()];
            }
            return ['status' => 'error', 'message' => "HTTP {$response->status()}"];
        } catch (\Exception $e) {
            return ['status' => 'unreachable', 'message' => $e->getMessage()];
        }
    }
}
