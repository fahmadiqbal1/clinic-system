<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class PlatformSettingsController extends Controller
{
    /**
     * Display the platform settings page.
     */
    public function index(): View
    {
        $medgemma = PlatformSetting::medgemma();

        return view('owner.platform-settings.index', compact('medgemma'));
    }

    /**
     * Toggle a feature flag on or off. Called via AJAX from the flags panel.
     */
    public function toggleFlag(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'flag'    => ['required', 'string', 'max:100'],
            'enabled' => ['required', 'boolean'],
        ]);

        $allowed = [
            'ai.admin.enabled', 'ai.ops.enabled', 'ai.compliance.enabled',
            'ai.sidecar.enabled', 'ai.ragflow.enabled', 'ai.gitnexus.enabled',
            'ai.chat.enabled.owner', 'ai.chat.enabled.doctor',
            'ai.chat.enabled.pharmacy', 'ai.chat.enabled.laboratory',
            'ai.chat.enabled.radiology', 'admin.nocobase.enabled',
        ];

        if (!in_array($validated['flag'], $allowed, true)) {
            return response()->json(['ok' => false, 'error' => 'Unknown flag.'], 422);
        }

        PlatformSetting::updateOrCreate(
            ['platform_name' => $validated['flag'], 'provider' => 'feature_flag'],
            ['meta' => ['value' => $validated['enabled']]]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Save AI model provider config and hot-swap the sidecar's active provider.
     *
     * Accepts a flat payload of provider + per-provider model names, URLs, and
     * API keys. Every field is optional — only non-empty values overwrite the DB.
     * API keys are encrypted at rest. Provider selection always saved.
     */
    public function saveModelConfig(Request $request): JsonResponse
    {
        $v = $request->validate([
            'provider'           => ['required', 'in:ollama,openai,anthropic,huggingface,groq'],
            // Ollama
            'ollama_url'         => ['nullable', 'string', 'max:300'],
            'ollama_model'       => ['nullable', 'string', 'max:200'],
            // OpenAI
            'openai_base_url'    => ['nullable', 'url', 'max:300'],
            'openai_model'       => ['nullable', 'string', 'max:200'],
            'openai_key'         => ['nullable', 'string', 'max:300'],
            // Anthropic
            'anthropic_model'    => ['nullable', 'string', 'max:200'],
            'anthropic_key'      => ['nullable', 'string', 'max:300'],
            // Hugging Face
            'hf_base_url'        => ['nullable', 'url', 'max:300'],
            'hf_model'           => ['nullable', 'string', 'max:200'],
            'hf_key'             => ['nullable', 'string', 'max:300'],
            // Groq
            'groq_model'         => ['nullable', 'string', 'max:200'],
            'groq_key'           => ['nullable', 'string', 'max:300'],
        ]);

        // Always save active provider
        $this->saveModelSetting('ai.model.provider', $v['provider']);

        // Save all non-empty plain fields
        $plain = [
            'ai.model.ollama.url'      => $v['ollama_url']      ?? null,
            'ai.model.ollama.model'    => $v['ollama_model']     ?? null,
            'ai.model.openai.base_url' => $v['openai_base_url']  ?? null,
            'ai.model.openai.model'    => $v['openai_model']     ?? null,
            'ai.model.anthropic.model' => $v['anthropic_model']  ?? null,
            'ai.model.hf.base_url'     => $v['hf_base_url']      ?? null,
            'ai.model.hf.model'        => $v['hf_model']         ?? null,
            'ai.model.groq.model'      => $v['groq_model']       ?? null,
        ];
        foreach ($plain as $key => $value) {
            if (!empty($value)) {
                $this->saveModelSetting($key, $value);
            }
        }

        // Save API keys as plaintext — sidecar must read them directly; Laravel encrypt() is
        // PHP-only and cannot be decrypted by the Python sidecar.
        $keys = [
            'ai.model.openai.key'     => $v['openai_key']    ?? null,
            'ai.model.anthropic.key'  => $v['anthropic_key'] ?? null,
            'ai.model.hf.key'         => $v['hf_key']        ?? null,
            'ai.model.groq.key'       => $v['groq_key']      ?? null,
        ];
        foreach ($keys as $settingKey => $rawKey) {
            if (!empty($rawKey)) {
                $this->saveModelSetting($settingKey, $rawKey);
            }
        }

        // Sync medgemma row so the status badge and test logic have a consistent record
        $this->syncMedgemmaRow($v);

        // Hot-swap sidecar — read the complete saved config from DB (so previously-saved keys
        // that were left blank in this form are still included in the payload).
        $sidecarSynced = false;
        $sidecarError  = null;
        try {
            $sidecarUrl = config('services.sidecar.url', env('CLINIC_SIDECAR_URL', 'http://localhost:8001'));
            $reloadResp = Http::withToken($this->mintSidecarJwt())->timeout(5)
                ->post($sidecarUrl . '/v1/config/reload', $this->buildReloadPayloadFromDb());
            if ($reloadResp->successful()) {
                $sidecarSynced = true;
                // Unblock any queued jobs that stalled while the circuit breaker was open.
                Cache::forget('ai_sidecar:cb_failures');
                Cache::forget('ai_sidecar:cb_open');
            } else {
                $sidecarError = "Sidecar replied HTTP {$reloadResp->status()}";
            }
        } catch (\Exception $e) {
            $sidecarError = $e->getMessage();
        }

        return response()->json([
            'ok'             => true,
            'provider'       => $v['provider'],
            'sidecar_synced' => $sidecarSynced,
            'sidecar_error'  => $sidecarError,
        ]);
    }

    /**
     * Test whichever provider is currently active in model_config.
     * Makes a minimal real inference call (max_tokens=1) and updates the medgemma
     * status row so the UI badge reflects real connectivity.
     */
    public function testProvider(): JsonResponse
    {
        $mc = PlatformSetting::where('provider', 'model_config')
            ->pluck('meta', 'platform_name')
            ->map(fn ($m) => is_array($m) ? ($m['value'] ?? '') : $m);

        $provider = $mc['ai.model.provider'] ?? 'ollama';
        $medgemma = PlatformSetting::medgemma();
        $medgemma->update(['status' => 'connecting', 'last_error' => null]);

        try {
            match ($provider) {
                'ollama'      => $this->pingOllama($mc),
                'openai'      => $this->pingOpenAI($mc),
                'anthropic'   => $this->pingAnthropic($mc),
                'huggingface' => $this->pingHuggingFace($mc),
                'groq'        => $this->pingGroq($mc),
                default       => throw new \RuntimeException("Unknown provider: {$provider}"),
            };

            $medgemma->update(['status' => 'connected', 'last_tested_at' => now(), 'last_error' => null]);
            return response()->json(['status' => 'connected', 'last_tested_at' => now()->diffForHumans()]);

        } catch (ConnectionException $e) {
            $url   = $mc['ai.model.ollama.url'] ?? '';
            $error = $this->enrichConnectionError($e->getMessage(), $url, $provider);
            $medgemma->update(['status' => 'failed', 'last_tested_at' => now(), 'last_error' => $error]);
            return response()->json(['status' => 'failed', 'error' => $error]);

        } catch (\Exception $e) {
            $error = $e->getMessage();
            $medgemma->update(['status' => 'failed', 'last_tested_at' => now(), 'last_error' => $error]);
            return response()->json(['status' => 'failed', 'error' => $error]);
        }
    }

    private function pingOllama(\Illuminate\Support\Collection $mc): void
    {
        $url   = rtrim($mc['ai.model.ollama.url'] ?? 'http://127.0.0.1:8081', '/') . '/v1/chat/completions';
        $model = $mc['ai.model.ollama.model'] ?? '';

        if (!$model) {
            throw new \RuntimeException('No Ollama model name configured.');
        }

        $resp = Http::withHeaders(['bypass-tunnel-reminder' => 'true'])
            ->timeout(30)
            ->post($url, ['model' => $model, 'messages' => [['role' => 'user', 'content' => 'Hi']], 'max_tokens' => 1]);

        if (!$resp->successful()) {
            if ($resp->status() === 401 && str_contains($resp->body(), 'Tunnel Password')) {
                throw new \RuntimeException(
                    "Localtunnel requires authentication. Visit the tunnel URL in your browser to bypass the Tunnel Password page, then retry."
                );
            }
            throw new \RuntimeException("Ollama replied with HTTP {$resp->status()}.\n" . mb_substr($resp->body(), 0, 200));
        }
    }

    private function enrichConnectionError(string $msg, string $url, string $provider): string
    {
        if ($provider !== 'ollama') {
            return $msg;
        }

        $isTunnel = str_contains($url, 'loca.lt') || str_contains($url, 'ngrok') || str_contains($url, 'trycloudflare');

        if (str_contains($msg, 'timed out') || str_contains($msg, 'Operation timed out')) {
            return $isTunnel
                ? "Connection timed out. The tunnel may have gone offline — restart it and try again."
                : "Connection timed out reaching {$url}.";
        }

        if (str_contains($msg, 'Could not resolve host')) {
            preg_match('/resolve host: (\S+)/', $msg, $m);
            $host = $m[1] ?? 'unknown';
            return "DNS resolution failed for '{$host}'. Check the hostname in your Ollama URL.";
        }

        if (str_contains($msg, 'Connection refused')) {
            $isLocal = str_contains($url, 'localhost') || str_contains($url, '127.0.0.1');
            return $isLocal
                ? "Ollama is running on your local machine (localhost) but not accessible from the VPS/server. Use a tunnel (scripts/start-ollama-tunnel.bat) or point MEDGEMMA_API_URL to a server running Ollama."
                : "Cannot reach Ollama at {$url}. Check that the host is reachable and Ollama is running on port 11434.";
        }

        return $msg;
    }

    private function pingOpenAI(\Illuminate\Support\Collection $mc): void
    {
        $key   = $mc['ai.model.openai.key']      ?? '';
        $model = $mc['ai.model.openai.model']     ?? '';
        $base  = rtrim($mc['ai.model.openai.base_url'] ?? 'https://api.openai.com/v1', '/');

        if (!$key)   { throw new \RuntimeException('No OpenAI API key configured.'); }
        if (!$model) { throw new \RuntimeException('No OpenAI model name configured.'); }

        $resp = Http::withHeaders(['Authorization' => "Bearer {$key}"])->timeout(20)
            ->post("{$base}/chat/completions", ['model' => $model, 'messages' => [['role' => 'user', 'content' => 'Hi']], 'max_tokens' => 1]);

        if (!$resp->successful()) {
            throw new \RuntimeException("OpenAI replied with HTTP {$resp->status()}: " . $resp->body());
        }
    }

    private function pingAnthropic(\Illuminate\Support\Collection $mc): void
    {
        $key   = $mc['ai.model.anthropic.key']   ?? '';
        $model = $mc['ai.model.anthropic.model'] ?? '';

        if (!$key)   { throw new \RuntimeException('No Anthropic API key configured.'); }
        if (!$model) { throw new \RuntimeException('No Anthropic model name configured.'); }

        $resp = Http::withHeaders([
            'x-api-key'         => $key,
            'anthropic-version' => '2023-06-01',
        ])->timeout(20)->post('https://api.anthropic.com/v1/messages', [
            'model'      => $model,
            'max_tokens' => 1,
            'messages'   => [['role' => 'user', 'content' => 'Hi']],
        ]);

        if (!$resp->successful()) {
            throw new \RuntimeException("Anthropic replied with HTTP {$resp->status()}: " . $resp->body());
        }
    }

    private function pingHuggingFace(\Illuminate\Support\Collection $mc): void
    {
        $key      = $mc['ai.model.hf.key']      ?? '';
        $model    = $mc['ai.model.hf.model']    ?? '';
        $customBase = rtrim($mc['ai.model.hf.base_url'] ?? '', '/');
        $defaultBase = 'https://api-inference.huggingface.co/v1';

        if (!$key)   { throw new \RuntimeException('No Hugging Face API key configured.'); }
        if (!$model) { throw new \RuntimeException('No Hugging Face model ID configured.'); }

        // Serverless Inference API: model embedded in path.
        // Dedicated Endpoint: custom base URL provided — use it directly.
        $url = ($customBase && $customBase !== $defaultBase)
            ? "{$customBase}/v1/chat/completions"
            : "https://api-inference.huggingface.co/models/{$model}/v1/chat/completions";

        $resp = Http::withHeaders(['Authorization' => "Bearer {$key}"])->timeout(30)
            ->post($url, ['model' => $model, 'messages' => [['role' => 'user', 'content' => 'Hi']], 'max_tokens' => 1]);

        if ($resp->status() === 404) {
            throw new \RuntimeException(
                "Model not accessible on HuggingFace serverless inference. " .
                "Either accept the model's license at huggingface.co/{$model} " .
                "or use a non-gated model such as HuggingFaceH4/zephyr-7b-beta."
            );
        }
        if (!$resp->successful()) {
            throw new \RuntimeException("Hugging Face replied with HTTP {$resp->status()}: " . mb_substr($resp->body(), 0, 200));
        }
    }

    private function pingGroq(\Illuminate\Support\Collection $mc): void
    {
        $key   = $mc['ai.model.groq.key']   ?? '';
        $model = $mc['ai.model.groq.model'] ?? '';

        if (!$key)   { throw new \RuntimeException('No Groq API key configured.'); }
        if (!$model) { throw new \RuntimeException('No Groq model ID configured.'); }

        $resp = Http::withHeaders(['Authorization' => "Bearer {$key}"])->timeout(30)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'      => $model,
                'messages'   => [['role' => 'user', 'content' => 'Hi']],
                'max_tokens' => 1,
            ]);

        if (!$resp->successful()) {
            throw new \RuntimeException("Groq replied with HTTP {$resp->status()}: " . mb_substr($resp->body(), 0, 200));
        }
    }

    /**
     * Keep the medgemma row in sync with whatever provider is active in model_config.
     * This ensures the status badge always reflects the right pipeline.
     */
    private function syncMedgemmaRow(array $v): void
    {
        $medgemma = PlatformSetting::medgemma();
        $provider = $v['provider'];

        $data = ['status' => 'disconnected', 'last_error' => null];

        match ($provider) {
            'ollama' => $data += [
                'provider' => 'ollama',
                'model'    => $v['ollama_model'] ?? $medgemma->model,
                'api_url'  => $v['ollama_url']   ?? $medgemma->api_url,
            ],
            'huggingface' => $data += [
                'provider' => 'huggingface',
                'model'    => $v['hf_model']    ?? $medgemma->model,
                'api_url'  => $v['hf_base_url'] ?? 'https://api-inference.huggingface.co/v1',
                ...(!empty($v['hf_key']) ? ['api_key' => $v['hf_key']] : []),
            ],
            'groq' => $data += [
                'provider' => 'huggingface',  // treated as online provider for isReady() purposes
                'model'    => $v['groq_model'] ?? $medgemma->model,
                'api_url'  => 'https://api.groq.com/openai/v1',
                ...(!empty($v['groq_key']) ? ['api_key' => $v['groq_key']] : []),
            ],
            // Anthropic and OpenAI: mark as huggingface provider so isReady() won't block
            // the PHP fallback path; actual calls go through sidecar model_provider.py
            default => $data += ['provider' => 'huggingface', 'model' => $v[$provider . '_model'] ?? $medgemma->model],
        };

        $medgemma->update($data);
    }

    /**
     * Build the env-var payload sent to /v1/config/reload by reading the COMPLETE
     * saved config from DB. This ensures API keys saved in previous sessions (and not
     * re-typed in the current form submission) are still included in the reload call.
     * DB is the single source of truth for the full pipeline.
     */
    private function buildReloadPayloadFromDb(): array
    {
        $mc = PlatformSetting::where('provider', 'model_config')
            ->pluck('meta', 'platform_name')
            ->map(fn($m) => is_array($m) ? ($m['value'] ?? '') : (string) $m);

        // Must mirror _DB_TO_ENV in sidecar/app/routes/health.py exactly.
        $dbToEnv = [
            'ai.model.provider'        => 'AI_MODEL_PROVIDER',
            'ai.model.ollama.url'      => 'OLLAMA_URL',
            'ai.model.ollama.model'    => 'OLLAMA_MODEL',
            'ai.model.openai.base_url' => 'OPENAI_BASE_URL',
            'ai.model.openai.model'    => 'OPENAI_MODEL',
            'ai.model.openai.key'      => 'OPENAI_API_KEY',
            'ai.model.anthropic.model' => 'ANTHROPIC_MODEL',
            'ai.model.anthropic.key'   => 'ANTHROPIC_API_KEY',
            'ai.model.hf.base_url'     => 'HF_BASE_URL',
            'ai.model.hf.model'        => 'HF_MODEL',
            'ai.model.hf.key'          => 'HF_API_KEY',
            'ai.model.groq.model'      => 'GROQ_MODEL',
            'ai.model.groq.key'        => 'GROQ_API_KEY',
        ];

        $env = [];
        foreach ($dbToEnv as $dbKey => $envKey) {
            $val = $mc[$dbKey] ?? '';
            if ($val !== '') {
                $env[$envKey] = $val;
            }
        }

        return ['env' => $env];
    }

    private function saveModelSetting(string $key, string $value): void
    {
        PlatformSetting::updateOrCreate(
            ['platform_name' => $key, 'provider' => 'model_config'],
            ['meta' => ['value' => $value]]
        );
    }

    private function mintSidecarJwt(): string
    {
        $secret = config('services.sidecar.jwt_secret', env('CLINIC_SIDECAR_JWT_SECRET', ''));
        if (!$secret) {
            return '';
        }
        $header  = rtrim(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '=');
        $payload = rtrim(base64_encode(json_encode(['sub' => 'laravel', 'role' => 'system', 'exp' => time() + 60])), '=');
        $header  = strtr($header,  '+/', '-_');
        $payload = strtr($payload, '+/', '-_');
        $sig     = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payload", $secret, true)), '+/', '-_'), '=');
        return "$header.$payload.$sig";
    }

}
