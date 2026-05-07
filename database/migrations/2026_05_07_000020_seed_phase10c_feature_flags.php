<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $flags = [
        'feature.vendor_portal'      => 'Vendor Portal (price list uploads)',
        'feature.price_ai_extraction' => 'AI Price List Extraction (PDF/Image)',
        'feature.external_lab_mou'   => 'External Lab MOU Management',
    ];

    private array $defaults = [
        'feature.vendor_portal'      => false,
        'feature.price_ai_extraction' => false,
        'feature.external_lab_mou'   => true,
    ];

    public function up(): void
    {
        foreach ($this->flags as $name => $label) {
            DB::table('platform_settings')->upsert(
                [
                    'platform_name' => $name,
                    'provider'      => 'feature_flag',
                    'status'        => 'disconnected',
                    'meta'          => json_encode([
                        'value' => $this->defaults[$name],
                        'label' => $label,
                    ]),
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
