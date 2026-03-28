<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TextBeeSmsChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        $apiKey   = (string) config('textbee.api_key', '');
        $deviceId = (string) config('textbee.device_id', '');
        $baseUrl  = (string) config('textbee.base_url', 'https://api.textbee.dev/api/v1');

        if (empty($apiKey)) {
            return;
        }

        // Auto-discover device ID from API if not configured
        if (empty($deviceId)) {
            $deviceId = $this->resolveDeviceId($apiKey, $baseUrl);
            if (empty($deviceId)) {
                Log::warning('TextBee: no device found. Register a device at https://app.textbee.dev');
                return;
            }
        }

        // Resolve patient phone (encrypted cast auto-decrypts)
        $phone = null;
        if (method_exists($notifiable, 'patient') && $notifiable->patient !== null) {
            $phone = $notifiable->patient->phone ?? null;
        }
        if (empty($phone)) {
            return;
        }

        if (!method_exists($notification, 'toTextBee')) {
            return;
        }

        /** @var string $message */
        $message = $notification->toTextBee($notifiable);
        if (empty($message)) {
            return;
        }

        try {
            $response = Http::withHeaders(['x-api-key' => $apiKey])
                ->timeout(10)
                ->post("{$baseUrl}/gateway/devices/{$deviceId}/sendSMS", [
                    'receivers' => [$phone],
                    'message'   => $message,
                ]);

            if (!$response->successful()) {
                Log::warning("TextBee SMS failed for notifiable #{$notifiable->id}: HTTP {$response->status()} — {$response->body()}");
            }
        } catch (\Exception $e) {
            Log::warning("TextBee SMS exception for notifiable #{$notifiable->id}: {$e->getMessage()}");
        }
    }

    /**
     * Fetch the first active device ID from TextBee API and cache it for 24 h.
     */
    private function resolveDeviceId(string $apiKey, string $baseUrl): string
    {
        $cacheKey = 'textbee_device_id_' . substr(md5($apiKey), 0, 8);

        return (string) cache()->remember($cacheKey, now()->addDay(), function () use ($apiKey, $baseUrl) {
            try {
                $response = Http::withHeaders(['x-api-key' => $apiKey])
                    ->timeout(10)
                    ->get("{$baseUrl}/gateway/getDevices");

                if ($response->successful()) {
                    $devices = $response->json('data') ?? $response->json() ?? [];
                    // Handle both array-of-objects and nested formats
                    if (isset($devices[0])) {
                        return $devices[0]['_id'] ?? $devices[0]['id'] ?? '';
                    }
                }
            } catch (\Exception $e) {
                Log::warning("TextBee: could not fetch devices — {$e->getMessage()}");
            }
            return '';
        });
    }
}
