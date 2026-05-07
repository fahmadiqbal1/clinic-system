<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 10B — Feature flags for OmniDimension, doctor availability, and quick registration.
     */
    private array $flags = [
        'feature.omnidimension'       => 'OmniDimension Phone AI (Urdu/English)',
        'feature.doctor_availability' => 'Doctor Availability Calendar',
        'feature.quick_registration'  => 'Quick Registration from Pre-booked Appointments',
    ];

    private array $defaults = [
        'feature.omnidimension'       => false,
        'feature.doctor_availability' => true,
        'feature.quick_registration'  => true,
    ];

    public function up(): void
    {
        foreach ($this->flags as $name => $label) {
            DB::table('platform_settings')->upsert(
                [
                    'platform_name' => $name,
                    'provider'      => 'feature_flag',
                    'status'        => 'disconnected',
                    'meta'          => json_encode(['value' => $this->defaults[$name], 'label' => $label]),
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
            ->where('provider', 'feature_flag')
            ->whereIn('platform_name', array_keys($this->flags))
            ->delete();
    }
};
