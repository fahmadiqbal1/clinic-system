<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $flags = [
        'feature.room_management'    => 'Clinic Room Management',
        'feature.doctor_credentials' => 'Doctor Credential Verification (48h)',
    ];

    private array $defaults = [
        'feature.room_management'    => false,
        'feature.doctor_credentials' => true,
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
