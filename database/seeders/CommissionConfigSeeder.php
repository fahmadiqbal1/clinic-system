<?php

namespace Database\Seeders;

use App\Models\CommissionConfig;
use Illuminate\Database\Seeder;

class CommissionConfigSeeder extends Seeder
{
    /**
     * Seed default commission configs for each service type.
     *
     * Schema: service_type, user_id (null = default), role, percentage, is_default.
     * Owner is NEVER stored — always absorbs remainder.
     */
    public function run(): void
    {
        $configs = [
            // Consultation: doctor gets 70%, Owner absorbs 30%
            [
                'service_type' => 'consultation',
                'user_id' => null,
                'role' => 'doctor',
                'percentage' => 70.00,
                'is_default' => true,
            ],

            // Lab: technician gets 55%, Owner absorbs 45%
            [
                'service_type' => 'lab',
                'user_id' => null,
                'role' => 'technician',
                'percentage' => 55.00,
                'is_default' => true,
            ],

            // Radiology: technician gets 55%, Owner absorbs 45%
            [
                'service_type' => 'radiology',
                'user_id' => null,
                'role' => 'technician',
                'percentage' => 55.00,
                'is_default' => true,
            ],

            // Pharmacy (profit-based): pharmacist 35%, doctor 15%, Owner absorbs 50%
            [
                'service_type' => 'pharmacy',
                'user_id' => null,
                'role' => 'pharmacist',
                'percentage' => 35.00,
                'is_default' => true,
            ],
            [
                'service_type' => 'pharmacy',
                'user_id' => null,
                'role' => 'doctor',
                'percentage' => 15.00,
                'is_default' => true,
            ],

            // Lab: doctor referral commission 10%, Owner absorbs remainder
            [
                'service_type' => 'lab',
                'user_id' => null,
                'role' => 'doctor',
                'percentage' => 10.00,
                'is_default' => true,
            ],

            // Radiology: doctor referral commission 10%, Owner absorbs remainder
            [
                'service_type' => 'radiology',
                'user_id' => null,
                'role' => 'doctor',
                'percentage' => 10.00,
                'is_default' => true,
            ],
        ];

        foreach ($configs as $config) {
            CommissionConfig::firstOrCreate(
                [
                    'service_type' => $config['service_type'],
                    'role' => $config['role'],
                    'is_default' => true,
                ],
                array_merge($config, ['is_active' => true])
            );
        }
    }
}
