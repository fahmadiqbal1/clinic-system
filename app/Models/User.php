<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles;
use App\Models\DoctorCredential;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'has_completed_tour',
        'is_active',
        'is_independent',
        'compensation_type',
        'employee_type',
        'base_salary',
        'revenue_share_enabled',
        'revenue_share_percentage',
        'commission_consultation',
        'commission_pharmacy',
        'commission_lab',
        'commission_radiology',
        'specialty',
        'timezone',
        'external_lab_id',
        'credentials_submitted_at',
        'credentials_verified_at',
        'credentials_verified_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at'        => 'datetime',
        'has_completed_tour'       => 'boolean',
        'is_active'                => 'boolean',
        'is_independent'           => 'boolean',
        'revenue_share_enabled'    => 'boolean',
        'base_salary'              => 'decimal:2',
        'revenue_share_percentage' => 'decimal:2',
        'commission_consultation'  => 'decimal:2',
        'commission_pharmacy'      => 'decimal:2',
        'commission_lab'           => 'decimal:2',
        'commission_radiology'     => 'decimal:2',
        'credentials_submitted_at' => 'datetime',
        'credentials_verified_at'  => 'datetime',
    ];

    /**
     * Default ordering scope.
     */
    public function scopeOrderly($query)
    {
        return $query->orderBy('name');
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class, 'doctor_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(StaffContract::class, 'user_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(DoctorPayout::class, 'doctor_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'prescribing_doctor_id');
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(DoctorCredential::class, 'user_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(StaffShift::class);
    }

    public function isGp(): bool
    {
        return $this->employee_type === 'gp';
    }

    /**
     * Resolve GP tier (1–3) from last month's patient count.
     * Thresholds come from platform_settings (hr_config provider).
     */
    public function gpTier(int $lastMonthPatients): int
    {
        $t3 = (int) PlatformSetting::getValue('gp.tier3.patients_threshold', 60);
        $t2 = (int) PlatformSetting::getValue('gp.tier2.patients_threshold', 30);

        if ($lastMonthPatients >= $t3) return 3;
        if ($lastMonthPatients >= $t2) return 2;
        return 1;
    }

    /*
    |--------------------------------------------------------------------------
    | Commission Methods (used by FinancialDistributionService)
    |--------------------------------------------------------------------------
    */

    /**
     * Check if this user earns commission (commission or hybrid compensation).
     */
    public function earnsCommission(): bool
    {
        return in_array($this->compensation_type, ['commission', 'hybrid'], true);
    }

    /**
     * Get the commission rate for a given department/service type.
     * Returns 0 for salaried users (they don't earn commission).
     *
     * Resolution order (most specific wins):
     *   1. User-specific CommissionConfig row (service_type + role + user_id)
     *   2. Per-user column rate set by owner (commission_lab, etc.)
     *   3. Role-default CommissionConfig row (service_type + role, is_default=true)
     */
    public function commissionRateFor(string $department): float
    {
        // Salaried users never earn commission
        if (!$this->earnsCommission()) {
            return 0;
        }

        $role = CommissionConfig::performerRoleForDepartment($department);

        // 1. User-specific CommissionConfig override (most specific)
        $userOverride = CommissionConfig::where('service_type', $department)
            ->where('role', $role)
            ->where('user_id', $this->id)
            ->where('is_active', true)
            ->first();

        if ($userOverride) {
            return (float) $userOverride->percentage;
        }

        // 2. Per-user column rate (owner-set explicit rate on user profile)
        $columnRate = match ($department) {
            'consultation' => (float) ($this->commission_consultation ?? 0),
            'pharmacy'     => (float) ($this->commission_pharmacy ?? 0),
            'lab'          => (float) ($this->commission_lab ?? 0),
            'radiology'    => (float) ($this->commission_radiology ?? 0),
            default        => 0,
        };

        if ($columnRate > 0) {
            return $columnRate;
        }

        // 3. Role-default CommissionConfig (fallback)
        $default = CommissionConfig::where('service_type', $department)
            ->where('role', $role)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        return $default ? (float) $default->percentage : 0;
    }

    /**
     * Map department to performer role (delegates to CommissionConfig).
     */
    public static function performerRoleForDepartment(string $department): string
    {
        return CommissionConfig::performerRoleForDepartment($department);
    }
}
