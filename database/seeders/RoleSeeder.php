<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'Owner',
            'Doctor',
            'Receptionist',
            'Triage',
            'Laboratory',
            'Radiology',
            'Pharmacy',
            'Patient'
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
