<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'platform_name',
        'provider',
        'api_key',
        'model',
        'api_url',
        'status',
        'last_tested_at',
        'last_error',
        'meta',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'last_tested_at' => 'datetime',
        'meta' => 'json',
    ];

    /**
     * Get the platform setting for MedGemma (Hugging Face or Ollama).
     */
    public static function medgemma(): self
    {
        return static::firstOrCreate(
            ['platform_name' => 'medgemma'],
            [
                'provider' => config('medgemma.provider', 'ollama'),
                'model' => config('medgemma.model', 'medgemma'),
                'api_url' => config('medgemma.api_url', 'http://localhost:11434'),
                'status' => 'disconnected',
            ]
        );
    }

    /**
     * Get the platform setting for FBR PRAL Digital Invoicing (DI) API v1.12.
     */
    public static function fbr(): self
    {
        return static::firstOrCreate(
            ['platform_name' => 'fbr'],
            [
                'provider'  => 'fbr_di',
                'api_url'   => 'https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb',
                'status'    => 'disconnected',
                'meta'      => [
                    'ntn'               => null,
                    'strn'              => null,
                    'business_name'     => null,
                    'business_address'  => null,
                    'seller_province'   => 'Punjab',
                    'sale_type'         => 'Services',
                    'uom'               => 'Numbers, pieces, units',
                    'tax_rate'          => 0,
                    'is_sandbox'        => true,
                    'sandbox_api_key'   => null,
                    'production_api_key'=> null,
                    'signing_secret'    => null,
                    'scenario_id'       => 'SN019',
                ],
            ]
        );
    }

    /**
     * Whether FBR DI integration is fully configured and ready to submit invoices.
     * Requires: NTN, business name, province, and the relevant environment token.
     */
    public function isFbrReady(): bool
    {
        if ($this->platform_name !== 'fbr') {
            return false;
        }

        $isSandbox = $this->getMeta('is_sandbox', true);
        $token     = $isSandbox
            ? $this->getMeta('sandbox_api_key')
            : $this->getMeta('production_api_key');

        return !empty($this->getMeta('ntn'))
            && !empty($this->getMeta('business_name'))
            && !empty($this->getMeta('seller_province'))
            && !empty($token);
    }

    /**
     * Get a value from the meta JSON field.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        $meta = $this->meta ?? [];
        return $meta[$key] ?? $default;
    }

    /**
     * Set a value in the meta JSON field.
     */
    public function setMeta(string $key, mixed $value): self
    {
        $meta = $this->meta ?? [];
        $meta[$key] = $value;
        $this->meta = $meta;
        return $this;
    }

    /**
     * Whether this platform uses the Ollama local provider.
     */
    public function isOllama(): bool
    {
        return $this->provider === 'ollama';
    }

    /**
     * Whether this platform uses the Hugging Face provider.
     */
    public function isHuggingFace(): bool
    {
        return $this->provider === 'huggingface';
    }

    /**
     * Check if the platform is ready to make API calls.
     * Ollama does not require an API key; Hugging Face does.
     */
    public function isReady(): bool
    {
        if ($this->isOllama()) {
            return !empty($this->api_url) && !empty($this->model);
        }

        return $this->hasApiKey();
    }

    /**
     * Build the full chat completions URL for this provider.
     */
    public function chatCompletionsUrl(): string
    {
        if ($this->isOllama()) {
            return rtrim($this->api_url ?? 'http://localhost:11434', '/') . '/v1/chat/completions';
        }

        // Hugging Face: {api_url}/{model}/v1/chat/completions
        return rtrim($this->api_url ?? config('medgemma.api_url'), '/')
            . '/' . $this->model
            . '/v1/chat/completions';
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
     * Get the meta.value for a named setting row (any provider).
     * Returns $default when the row or key is absent.
     */
    public static function getValue(string $name, mixed $default = null, string $provider = 'hr_config'): mixed
    {
        $setting = static::where('platform_name', $name)
            ->where('provider', $provider)
            ->first();

        return $setting?->getMeta('value') ?? $default;
    }

    /**
     * Check whether a feature flag is enabled.
     * All flags default to false when the row does not exist.
     */
    public static function isEnabled(string $flag): bool
    {
        $setting = static::where('platform_name', $flag)
            ->where('provider', 'feature_flag')
            ->first();

        return (bool) ($setting?->getMeta('value') ?? false);
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
