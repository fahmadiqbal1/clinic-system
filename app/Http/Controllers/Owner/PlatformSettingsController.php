<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * Save updated platform settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['nullable', 'string', 'in:huggingface,ollama'],
            'model'    => ['required', 'string', 'max:255'],
            'api_key'  => ['nullable', 'string', 'max:512'],
            'api_url'  => ['nullable', 'string', 'max:500'],
        ]);

        $medgemma = PlatformSetting::medgemma();

        $data = ['model' => $validated['model']];

        if (!empty($validated['provider'])) {
            $data['provider'] = $validated['provider'];
        }

        if (!empty($validated['api_url'])) {
            $data['api_url'] = $validated['api_url'];
        }

        // Only update the API key if a non-empty value was submitted
        if (!empty($validated['api_key'])) {
            $data['api_key'] = $validated['api_key'];
            // Reset connection status when the key changes
            $data['status']     = 'disconnected';
            $data['last_error'] = null;
        }

        $medgemma->update($data);

        return back()->with('success', 'Platform settings saved. Test the connection to verify your configuration.');
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
            'provider'           => ['required', 'in:ollama,openai,anthropic,huggingface'],
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
        ];
        foreach ($keys as $settingKey => $rawKey) {
            if (!empty($rawKey)) {
                $this->saveModelSetting($settingKey, $rawKey);
            }
        }

        // Sync medgemma row so the status badge and test logic have a consistent record
        $this->syncMedgemmaRow($v);

        // Hot-swap sidecar — pass all current config (including plaintext keys) directly in the
        // request body so the sidecar never needs to decrypt anything from DB.
        try {
            $sidecarUrl = config('services.sidecar.url', env('CLINIC_SIDECAR_URL', 'http://localhost:8001'));

            $reloadPayload = $this->buildReloadPayload($v);

            Http::withToken($this->mintSidecarJwt())->timeout(5)
                ->post($sidecarUrl . '/v1/config/reload', $reloadPayload);
        } catch (\Exception) {
            // Non-fatal — sidecar picks up changes on next restart
        }

        return response()->json(['ok' => true, 'provider' => $v['provider']]);
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
                default       => throw new \RuntimeException("Unknown provider: {$provider}"),
            };

            $medgemma->update(['status' => 'connected', 'last_tested_at' => now(), 'last_error' => null]);
            return response()->json(['status' => 'connected', 'last_tested_at' => now()->diffForHumans()]);

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
            throw new \RuntimeException("Ollama replied with HTTP {$resp->status()}.\n" . $resp->body());
        }
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
        $key   = $mc['ai.model.hf.key']      ?? '';
        $model = $mc['ai.model.hf.model']    ?? '';
        $base  = rtrim($mc['ai.model.hf.base_url'] ?? 'https://api-inference.huggingface.co/v1', '/');

        if (!$key)   { throw new \RuntimeException('No Hugging Face API key configured.'); }
        if (!$model) { throw new \RuntimeException('No Hugging Face model ID configured.'); }

        $resp = Http::withHeaders(['Authorization' => "Bearer {$key}"])->timeout(30)
            ->post("{$base}/chat/completions", ['model' => $model, 'messages' => [['role' => 'user', 'content' => 'Hi']], 'max_tokens' => 1]);

        if (!$resp->successful()) {
            throw new \RuntimeException("Hugging Face replied with HTTP {$resp->status()}: " . mb_substr($resp->body(), 0, 300));
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
            // Anthropic and OpenAI: mark as huggingface provider so isReady() won't block
            // the PHP fallback path; actual calls go through sidecar model_provider.py
            default => $data += ['provider' => 'huggingface', 'model' => $v[$provider . '_model'] ?? $medgemma->model],
        };

        $medgemma->update($data);
    }

    /**
     * Build the env-var payload sent to /v1/config/reload.
     * All values are plaintext — the sidecar sets them directly into os.environ.
     */
    private function buildReloadPayload(array $v): array
    {
        $env = ['AI_MODEL_PROVIDER' => $v['provider']];

        $map = [
            'OLLAMA_URL'      => $v['ollama_url']      ?? null,
            'OLLAMA_MODEL'    => $v['ollama_model']     ?? null,
            'OPENAI_BASE_URL' => $v['openai_base_url']  ?? null,
            'OPENAI_MODEL'    => $v['openai_model']     ?? null,
            'OPENAI_API_KEY'  => $v['openai_key']       ?? null,
            'ANTHROPIC_MODEL' => $v['anthropic_model']  ?? null,
            'ANTHROPIC_API_KEY' => $v['anthropic_key']  ?? null,
            'HF_BASE_URL'     => $v['hf_base_url']      ?? null,
            'HF_MODEL'        => $v['hf_model']         ?? null,
            'HF_API_KEY'      => $v['hf_key']           ?? null,
        ];

        foreach ($map as $envKey => $value) {
            if (!empty($value)) {
                $env[$envKey] = $value;
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

    /**
     * Test the connection to the MedGemma API (Hugging Face or Ollama).
     * Returns JSON so the frontend can update the status badge live.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $medgemma = PlatformSetting::medgemma();

        if (!$medgemma->isReady()) {
            $hint = $medgemma->isOllama()
                ? 'Please configure the Ollama URL and model name first.'
                : 'No API key has been configured. Please enter your Hugging Face API key and save first.';

            return response()->json([
                'status' => 'failed',
                'error'  => $hint,
            ]);
        }

        // Mark as connecting while we attempt
        $medgemma->update(['status' => 'connecting', 'last_error' => null]);

        try {
            $url = $medgemma->chatCompletionsUrl();

            $headers = [];
            if ($medgemma->isHuggingFace() && $medgemma->hasApiKey()) {
                $headers['Authorization'] = 'Bearer ' . $medgemma->api_key;
            }
            // Bypass the Localtunnel browser-reminder page that returns 401
            // when the request does not come from a real browser.
            if ($medgemma->isOllama()) {
                $headers['bypass-tunnel-reminder'] = 'true';
            }

            // Use a 60-second timeout — tunnels (Localtunnel, ngrok, Cloudflare)
            // can add several seconds of latency on the first request.
            $response = Http::withHeaders($headers)->timeout(60)->post($url, [
                'model'      => $medgemma->model,
                'messages'   => [['role' => 'user', 'content' => 'Hi']],
                'max_tokens' => 1,
            ]);

            if ($response->successful()) {
                $medgemma->update([
                    'status'         => 'connected',
                    'last_tested_at' => now(),
                    'last_error'     => null,
                ]);

                return response()->json([
                    'status'          => 'connected',
                    'last_tested_at'  => now()->diffForHumans(),
                ]);
            }

            $error = $this->enrichHttpError($response->status(), $response->body(), $medgemma->api_url);

            $medgemma->update([
                'status'         => 'failed',
                'last_tested_at' => now(),
                'last_error'     => $error,
            ]);

            return response()->json(['status' => 'failed', 'error' => $error]);
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $error = $this->enrichConnectionError($error, $medgemma->api_url);

            $medgemma->update([
                'status'         => 'failed',
                'last_tested_at' => now(),
                'last_error'     => $error,
            ]);

            return response()->json(['status' => 'failed', 'error' => $error]);
        }
    }

    /**
     * Return a human-friendly error message for non-exception HTTP failures,
     * especially the Localtunnel 401 "Tunnel Password" reminder page.
     */
    private function enrichHttpError(int $status, string $body, ?string $configuredUrl): string
    {
        // Localtunnel returns a 401 HTML page when the bypass header is not respected.
        // We already send the header, but surface a clear message if it still occurs.
        if (
            $status === 401 && (
                str_contains($body, 'Tunnel Password') ||
                str_contains($body, 'loca.lt') ||
                (str_contains($configuredUrl ?? '', '.loca.lt') && str_contains($body, '<html'))
            )
        ) {
            return "Localtunnel returned a '401 Tunnel Password' reminder page. "
                 . "The tunnel is reachable but requires browser verification. "
                 . "Try restarting the tunnel (`npx localtunnel --port 11434`) "
                 . "and pasting the new URL above. "
                 . "If the issue persists, consider using ngrok or a Cloudflare Tunnel instead.";
        }

        return 'API returned HTTP ' . $status . ': ' . mb_substr($body, 0, 300);
    }

    /**
     * Return a human-friendly error message for common connection failures,
     * especially the "localhost not reachable from the server" scenario.
     */
    private function enrichConnectionError(string $rawError, ?string $configuredUrl): string
    {
        $isLocalhost = $configuredUrl && (
            str_contains($configuredUrl, 'localhost') ||
            str_contains($configuredUrl, '127.0.0.1')
        );

        // cURL error 7: connection refused / host unreachable
        if (
            str_contains($rawError, 'cURL error 7') ||
            str_contains($rawError, 'Connection refused') ||
            str_contains($rawError, 'Failed to connect')
        ) {
            $url = rtrim($configuredUrl ?? 'the configured URL', '/');

            if ($isLocalhost) {
                return "Cannot reach Ollama at {$url} — this URL points to the web server itself, "
                     . "not your local computer. To fix this you have two options: "
                     . "(1) Install and start Ollama on this VPS/server (recommended for production — "
                     . "run `curl -fsSL https://ollama.com/install.sh | sh` then `ollama serve`), or "
                     . "(2) Expose your local Ollama publicly using a tunnel such as ngrok "
                     . "(`ngrok http 11434`) or Cloudflare Tunnel, then paste the public HTTPS URL "
                     . "into the API Base URL field above and save. "
                     . "Original error: {$rawError}";
            }

            return "Cannot reach Ollama at {$url} from the server. "
                 . "Make sure Ollama is running and the URL is reachable from the VPS. "
                 . "Original error: {$rawError}";
        }

        // cURL error 28: operation timed out (common with tunnels)
        if (
            str_contains($rawError, 'cURL error 28') ||
            str_contains($rawError, 'Operation timed out')
        ) {
            $url = rtrim($configuredUrl ?? 'the configured URL', '/');

            if ($this->isTunnelUrl($configuredUrl)) {
                return "Connection to {$url} timed out. "
                     . "Your tunnel (Localtunnel / ngrok / Cloudflare) may have disconnected or restarted. "
                     . "Check that the tunnel is still running and update the URL here if it changed. "
                     . "Original error: {$rawError}";
            }

            return "Connection to {$url} timed out. "
                 . "Ensure Ollama is running and the URL is reachable from the VPS. "
                 . "Original error: {$rawError}";
        }

        // DNS resolution failure
        if (
            str_contains($rawError, 'cURL error 6') ||
            str_contains($rawError, 'Could not resolve host')
        ) {
            return "DNS resolution failed for the configured URL. "
                 . "Please double-check the API Base URL and ensure the hostname is correct. "
                 . "Original error: {$rawError}";
        }

        return $rawError;
    }

    /**
     * Detect whether a URL belongs to a known tunnel service.
     */
    private function isTunnelUrl(?string $url): bool
    {
        if (!$url) {
            return false;
        }

        return str_contains($url, '.loca.lt')
            || str_contains($url, '.ngrok')
            || str_contains($url, 'ngrok-free.app')
            || str_contains($url, 'trycloudflare.com');
    }
}
