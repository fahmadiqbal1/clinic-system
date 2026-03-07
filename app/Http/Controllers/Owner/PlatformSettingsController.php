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

            $medgemma->update([
                'status'         => 'failed',
                'last_tested_at' => now(),
                'last_error'     => $error,
            ]);

            return response()->json(['status' => 'failed', 'error' => $error]);
        }
    }
}
