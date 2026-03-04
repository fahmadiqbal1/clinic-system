<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add per-department commission columns to users table
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('commission_consultation', 5, 2)->default(0)->after('base_salary');
            $table->decimal('commission_pharmacy', 5, 2)->default(0)->after('commission_consultation');
            $table->decimal('commission_lab', 5, 2)->default(0)->after('commission_pharmacy');
            $table->decimal('commission_radiology', 5, 2)->default(0)->after('commission_lab');
        });

        // Step 2: Migrate existing commission_configs data into user columns
        // For each user with per-user overrides, copy their rates
        if (Schema::hasTable('commission_configs')) {
            $configs = DB::table('commission_configs')
                ->whereNotNull('user_id')
                ->where('is_active', true)
                ->get();

            foreach ($configs as $config) {
                $column = match ($config->service_type) {
                    'consultation' => 'commission_consultation',
                    'pharmacy' => 'commission_pharmacy',
                    'lab' => 'commission_lab',
                    'radiology' => 'commission_radiology',
                    default => null,
                };

                if ($column) {
                    DB::table('users')
                        ->where('id', $config->user_id)
                        ->update([$column => $config->percentage]);
                }
            }

            // Step 3: For commission-earning users WITHOUT per-user overrides,
            // seed defaults from the default commission_configs based on their role
            $defaults = DB::table('commission_configs')
                ->where('is_default', true)
                ->where('is_active', true)
                ->get()
                ->groupBy('service_type');

            // Map Spatie role names to commission_config role names
            $roleMapping = [
                'Doctor' => 'doctor',
                'Pharmacy' => 'pharmacist',
                'Laboratory' => 'technician',
                'Radiology' => 'technician',
            ];

            $users = DB::table('users')
                ->whereIn('compensation_type', ['commission', 'hybrid'])
                ->get();

            foreach ($users as $user) {
                // Get user's Spatie role
                $roleRecord = DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.model_id', $user->id)
                    ->where('model_has_roles.model_type', 'App\\Models\\User')
                    ->first();

                if (!$roleRecord) {
                    continue;
                }

                $configRole = $roleMapping[$roleRecord->name] ?? null;
                if (!$configRole) {
                    continue;
                }

                // For each department, if user doesn't already have a per-user override,
                // copy the default rate for their role
                foreach (['consultation', 'pharmacy', 'lab', 'radiology'] as $dept) {
                    $column = 'commission_' . $dept;

                    // Skip if user already has a value from per-user override
                    if ((float) $user->$column > 0) {
                        continue;
                    }

                    // Find default config for this department + role
                    $defaultConfig = ($defaults[$dept] ?? collect())
                        ->firstWhere('role', $configRole);

                    if ($defaultConfig) {
                        DB::table('users')
                            ->where('id', $user->id)
                            ->update([$column => $defaultConfig->percentage]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'commission_consultation',
                'commission_pharmacy',
                'commission_lab',
                'commission_radiology',
            ]);
        });
    }
};
