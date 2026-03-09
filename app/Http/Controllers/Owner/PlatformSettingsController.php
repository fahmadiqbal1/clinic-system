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

            $response = Http::withHeaders($headers)->timeout(30)->post($url, [
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

            $error = 'API returned HTTP ' . $response->status() . ': ' . mb_substr($response->body(), 0, 300);

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
            str_contains($rawError, 'Failed to connect') ||
            str_contains($rawError, 'cURL error 28')
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
}
