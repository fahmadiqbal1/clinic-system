<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionConfig extends Model
{
    use HasFactory;

    protected $table = 'commission_configs';

    protected $fillable = [
        'service_type',
        'user_id',
        'role',
        'percentage',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Boot — Enforce is_default / user_id invariant
    |--------------------------------------------------------------------------
    | Rule: is_default=true → user_id MUST be null
    |        user_id NOT NULL → is_default MUST be false
    */
    protected static function booted(): void
    {
        $enforce = function (CommissionConfig $config) {
            if ($config->is_default && $config->user_id !== null) {
                throw new \RuntimeException('Default configs must not have a user_id.');
            }
            if ($config->user_id !== null && $config->is_default) {
                throw new \RuntimeException('User-specific configs cannot be marked as default.');
            }
            // Auto-set is_default when user_id is null
            if ($config->user_id === null) {
                $config->is_default = true;
            }
        };

        static::creating($enforce);
        static::updating($enforce);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Resolution Algorithm — Deterministic, Hybrid Model
    |--------------------------------------------------------------------------
    | For each service_type:
    |   Step 1: Fetch all user-specific configs (user_id NOT NULL)
    |   Step 2: Fetch role-default configs (is_default=true, user_id=NULL)
    |   Step 3: Per role — if user-specific exists → use it; else default → use it; else 0%
    |   Step 4: Owner = 100% - sum(all other shares). If < 0 → throw.
    */

    /**
     * Resolve rate for a specific role + optional user.
     * User-specific overrides role default.
     * Fixed: includes role filter on user-specific lookup to prevent cross-service contamination.
     */
    public static function resolveRate(string $serviceType, string $role, ?int $userId): float
    {
        // Step 1: Try user-specific for this exact service_type + role + user
        if ($userId) {
            $override = static::where('service_type', $serviceType)
                ->where('role', $role)
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->first();

            if ($override) {
                return (float) $override->percentage;
            }
        }

        // Step 2: Fall back to role default (is_default=true)
        $default = static::where('service_type', $serviceType)
            ->where('role', $role)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        return $default ? (float) $default->percentage : 0;
    }

    /**
     * Get all default configs for a service type (is_default=true).
     */
    public static function getDefaults(string $serviceType): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('service_type', $serviceType)
            ->where('is_default', true)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Check if a default config already exists for this service_type + role.
     */
    public static function defaultExistsForRole(string $serviceType, string $role, ?int $excludeId = null): bool
    {
        return static::where('service_type', $serviceType)
            ->where('role', $role)
            ->where('is_default', true)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Department → Commission Role Mapping
    |--------------------------------------------------------------------------
    | Maps invoice department to the performer's commission role.
    */

    /**
     * Get the commission role for a given department's performer.
     */
    public static function performerRoleForDepartment(string $department): string
    {
        return match ($department) {
            'consultation' => 'doctor',
            'lab' => 'technician',
            'radiology' => 'technician',
            'pharmacy' => 'pharmacist',
            default => throw new \InvalidArgumentException("Unknown department: {$department}"),
        };
    }
}
