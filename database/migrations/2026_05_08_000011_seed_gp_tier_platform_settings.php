<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * GP salary tier thresholds (advisory — tiers are calculated at runtime,
 * not stored on the user). Owner configures thresholds and bonuses here;
 * DoctorPayoutService reads them at payout generation time.
 *
 * Thresholds are patients seen per calendar month.
 * Bonuses are added to base_salary in PKR.
 */
return new class extends Migration
{
    private array $settings = [
        'gp.tier1.label'              => 'Tier 1 (Base)',
        'gp.tier1.patients_threshold' => '0',
        'gp.tier2.label'              => 'Tier 2 (Active)',
        'gp.tier2.patients_threshold' => '30',
        'gp.tier2.bonus'              => '5000',
        'gp.tier3.label'              => 'Tier 3 (Senior)',
        'gp.tier3.patients_threshold' => '60',
        'gp.tier3.bonus'              => '10000',
    ];

    public function up(): void
    {
        foreach ($this->settings as $name => $value) {
            DB::table('platform_settings')->upsert(
                [
                    'platform_name' => $name,
                    'provider'      => 'hr_config',
                    'status'        => 'connected',
                    'meta'          => json_encode(['value' => $value, 'label' => $name]),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
                ['platform_name'],
                ['meta', 'updated_at']
            );
        }
    }

    public function down(): void
    {
        DB::table('platform_settings')
            ->where('provider', 'hr_config')
            ->whereIn('platform_name', array_keys($this->settings))
            ->delete();
    }
};
