<?php

namespace App\Services;

use App\Models\AiInvocation;
use App\Models\Patient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiSidecarClient
{
    // Circuit-breaker constants (Phase -1 measured thresholds)
    private const CB_FAILURES_KEY = 'ai_sidecar:cb_failures';
    private const CB_OPEN_KEY     = 'ai_sidecar:cb_open';
    private const CB_THRESHOLD    = 3;
    private const CB_WINDOW_S     = 60;
    private const CB_OPEN_S       = 300; // 5 min
    private const TIMEOUT_S       = 15;
    private const AI_TIMEOUT_S    = 120; // online providers; Ollama uses model_provider timeout

    public function __construct(private readonly CaseTokenService $caseTokenService) {}

    public function isCircuitOpen(): bool
    {
        return (bool) Cache::get(self::CB_OPEN_KEY, false);
    }

    /**
     * Send a pseudonymised consultation to /v1/consult.
     * CaseTokenService guarantees no raw PHI leaves this process.
     */
    public function consult(Patient $patient, array $vitals = [], ?string $customQuestion = null): array
    {
        $caseToken = $this->caseTokenService->tokenize($patient);
        $ageBand   = $this->caseTokenService->ageBand($patient->date_of_birth);

        $payload = [
            'case_token'      => $caseToken,
            'age_band'        => $ageBand,
            'gender'          => $patient->gender,
            'vitals'          => $vitals ?: null,
            'custom_question' => $customQuestion,
        ];

        return $this->call('POST', '/v1/consult', $payload, $caseToken, timeoutS: self::AI_TIMEOUT_S);
    }

    /**
     * Generic single-turn text analysis — routes through whatever provider is active in the sidecar.
     * Used for lab and radiology analyses so they share the same provider as consultations.
     */
    public function analyseText(string $systemPrompt, string $userMessage): string
    {
        $result = $this->call('POST', '/v1/analyse', [
            'system_prompt' => $systemPrompt,
            'user_message'  => $userMessage,
        ], timeoutS: self::AI_TIMEOUT_S);

        return $result['text'] ?? '';
    }

    public function ragQuery(string $query, string $collection = 'general'): array
    {
        return $this->call('POST', '/v1/rag/query', compact('query', 'collection'));
    }

    public function ragIngest(string $filePath, string $collection = 'general'): array
    {
        return $this->call('POST', '/v1/rag/ingest', compact('filePath', 'collection'));
    }

    /** Send text content directly to RAGFlow (e.g. DB-generated corpus). */
    public function ragIngestContent(string $content, string $collection = 'general'): array
    {
        return $this->call('POST', '/v1/rag/ingest', compact('content', 'collection'));
    }

    public function forecastRevenue(array $params = []): array
    {
        return $this->call('POST', '/v1/forecast/revenue', $params);
    }

    public function forecastInventory(array $params = []): array
    {
        return $this->call('POST', '/v1/forecast/inventory', $params);
    }

    /**
     * Phase 8 — Administrative AI persona.
     * Returns rationale + priority + action_items.
     */
    public function adminAnalyse(array $payload): array
    {
        return $this->call('POST', '/v1/admin/analyse', $payload);
    }

    /**
     * Phase 8 — Operations AI persona.
     * Returns rationale + urgency + critical_items + action_items.
     */
    public function opsAnalyse(array $payload): array
    {
        return $this->call('POST', '/v1/ops/analyse', $payload);
    }

    /**
     * Phase 8 — Compliance AI persona.
     * Returns rationale + status + escalation_pending + evidence_refs.
     */
    public function complianceAnalyse(array $payload): array
    {
        return $this->call('POST', '/v1/compliance/analyse', $payload);
    }

    private function call(string $method, string $path, array $payload = [], ?string $caseToken = null, int $timeoutS = self::TIMEOUT_S): array
    {
        if ($this->isCircuitOpen()) {
            Log::warning('AiSidecarClient: circuit open', ['path' => $path]);
            throw new \RuntimeException('AI sidecar unavailable (circuit open).');
        }

        $url     = rtrim(config('clinic.sidecar_url', 'http://localhost:8001'), '/') . $path;
        $jwt     = $this->mintJwt();
        $start   = (int) (microtime(true) * 1000);
        $method  = strtolower($method);

        try {
            $response = Http::withToken($jwt)
                ->timeout($timeoutS)
                ->retry(1, 0)
                ->{$method}($url, $payload);

            $latencyMs = (int) (microtime(true) * 1000) - $start;

            if (!$response->successful()) {
                throw new \RuntimeException("Sidecar HTTP {$response->status()}: " . $response->body());
            }

            $body = $response->json() ?? [];
            $this->recordSuccess();
            $this->writeLog($path, $payload, $body, $latencyMs, 'ok', $caseToken);

            return $body;
        } catch (\Exception $e) {
            $latencyMs = (int) (microtime(true) * 1000) - $start;
            $this->recordFailure();
            $this->writeLog($path, $payload, null, $latencyMs, 'error', $caseToken);

            throw $e;
        }
    }

    private function recordFailure(): void
    {
        $failures = (int) Cache::get(self::CB_FAILURES_KEY, 0) + 1;
        Cache::put(self::CB_FAILURES_KEY, $failures, self::CB_WINDOW_S);

        if ($failures >= self::CB_THRESHOLD) {
            Cache::put(self::CB_OPEN_KEY, true, self::CB_OPEN_S);
            Log::warning('AiSidecarClient: circuit breaker opened', ['failures' => $failures]);
        }
    }

    private function recordSuccess(): void
    {
        Cache::forget(self::CB_FAILURES_KEY);
        Cache::forget(self::CB_OPEN_KEY);
    }

    /**
     * Mint a HS256 JWT without composer dependencies.
     * Carries user_id, role, feature_flags; expires in 5 min.
     */
    private function mintJwt(): string
    {
        $user   = Auth::user();
        $now    = time();
        $secret = config('clinic.sidecar_jwt_secret', '');

        $header  = $this->b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->b64url(json_encode([
            'sub'           => $user?->id,
            'role'          => $user?->getRoleNames()->first(),
            'feature_flags' => ['ai.sidecar.enabled' => true],
            'iat'           => $now,
            'exp'           => $now + 300,
        ]));

        $sig = $this->b64url(hash_hmac('sha256', "{$header}.{$payload}", $secret, true));

        return "{$header}.{$payload}.{$sig}";
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function writeLog(
        string $path,
        array $payload,
        ?array $response,
        int $latencyMs,
        string $outcome,
        ?string $caseToken = null
    ): void {
        try {
            AiInvocation::log(
                endpoint:        $path,
                promptHash:      hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
                latencyMs:       $latencyMs,
                outcome:         $outcome,
                caseToken:       $caseToken,
                modelId:         $response['model_id'] ?? null,
                retrievalDocIds: $response['retrieval_citations'] ?? null,
            );
        } catch (\Exception $e) {
            Log::error('AiSidecarClient: failed to write ai_invocations', ['error' => $e->getMessage()]);
        }
    }
}
