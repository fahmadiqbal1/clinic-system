<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'platform_name',
        'api_key',
        'model',
        'api_url',
        'status',
        'last_tested_at',
        'last_error',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'last_tested_at' => 'datetime',
    ];

    /**
     * Get the platform setting for MedGemma (Hugging Face).
     */
    public static function medgemma(): self
    {
        return static::firstOrCreate(
            ['platform_name' => 'medgemma'],
            [
                'model' => config('medgemma.model', 'google/medgemma-4b-it'),
                'api_url' => config('medgemma.api_url', 'https://router.huggingface.co/hf-inference/models/'),
                'status' => 'disconnected',
            ]
        );
    }

    /**
     * Whether the platform is currently connected.
     */
    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    /**
     * Whether the platform has an API key configured.
     */
    public function hasApiKey(): bool
    {
        return !empty($this->api_key);
    }

    /**
     * Get status badge class.
     */
    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'connected'    => 'bg-success',
            'connecting'   => 'bg-warning text-dark',
            'failed'       => 'bg-danger',
            default        => 'bg-secondary',
        };
    }

    /**
     * Get status icon.
     */
    public function statusIcon(): string
    {
        return match ($this->status) {
            'connected'    => 'bi-check-circle-fill',
            'connecting'   => 'bi-arrow-repeat',
            'failed'       => 'bi-x-circle-fill',
            default        => 'bi-dash-circle',
        };
    }

    /**
     * Get human-readable status label.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'connected'    => 'Connected',
            'connecting'   => 'Connecting…',
            'failed'       => 'Connection Failed',
            default        => 'Disconnected',
        };
    }
}
