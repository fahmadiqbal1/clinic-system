<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MedGemmaConfigController extends Controller
{
    /**
     * Save MedGemma API settings from the owner profile form.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'medgemma_api_key' => ['nullable', 'string', 'max:200'],
            'medgemma_model'   => ['nullable', 'string', 'max:100'],
            'medgemma_api_url' => ['nullable', 'url', 'max:300'],
        ]);

        Setting::set('medgemma.api_key', $validated['medgemma_api_key'] ?? '');
        Setting::set('medgemma.model', $validated['medgemma_model'] ?? config('medgemma.model'));
        Setting::set('medgemma.api_url', $validated['medgemma_api_url'] ?? config('medgemma.api_url'));

        return redirect()->route('profile.edit')
            ->with('medgemma_status', 'saved')
            ->with('status', 'medgemma-saved');
    }

    /**
     * Test connectivity to the Hugging Face / MedGemma API.
     * Accepts optional api_key / model / api_url in the request body so the
     * owner can test values that have not been saved yet.
     * Returns JSON so the front-end can update the status badge live.
     */
    public function test(Request $request): \Illuminate\Http\JsonResponse
    {
        // Use values submitted in the form (pre-save test), fall back to stored/env values
        $apiKey = $request->input('api_key') ?: Setting::get('medgemma.api_key') ?: config('medgemma.api_key');
        $model  = $request->input('model')   ?: Setting::get('medgemma.model')   ?: config('medgemma.model');
        $apiUrl = $request->input('api_url') ?: Setting::get('medgemma.api_url') ?: config('medgemma.api_url');

        if (empty($apiKey)) {
            return response()->json([
                'connected' => false,
                'message'   => 'No API key configured.',
            ]);
        }

        try {
            $url = rtrim($apiUrl, '/') . '/' . $model . '/v1/chat/completions';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->timeout(15)->post($url, [
                'messages'   => [['role' => 'user', 'content' => 'ping']],
                'max_tokens' => 1,
            ]);

            // Treat 2xx, 4xx (bad request / unprocessable) as "reachable with valid key".
            // Only a 401/403 (auth error) or 5xx (server down) counts as not connected.
            $connected = ! in_array($response->status(), [401, 403]) && $response->status() < 500;

            return response()->json([
                'connected' => $connected,
                'message'   => $connected
                    ? 'Connected to MedGemma API successfully.'
                    : 'API returned status ' . $response->status() . '. Please check your API key.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'connected' => false,
                'message'   => 'Could not reach the API: ' . $e->getMessage(),
            ]);
        }
    }
}
