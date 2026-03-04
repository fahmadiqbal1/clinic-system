<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(CommissionConfigSeeder::class);
        $this->call(ServiceCatalogSeeder::class);

        // Owner
        $owner = User::firstOrCreate(
            ['email' => 'owner@clinic.com'],
            [
                'name' => 'Clinic Owner',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'compensation_type' => 'commission',
                'email_verified_at' => now(),
            ]
        );
        $owner->assignRole('Owner');

        // Doctor
        $doctor = User::firstOrCreate(
            ['email' => 'doctor@clinic.com'],
            [
                'name' => 'Dr. Ahmed',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'compensation_type' => 'commission',
                'email_verified_at' => now(),
            ]
        );
        $doctor->assignRole('Doctor');

        // Second Doctor
        $doctor2 = User::firstOrCreate(
            ['email' => 'doctor2@clinic.com'],
            [
                'name' => 'Dr. Fatima',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'compensation_type' => 'hybrid',
                'base_salary' => 5000.00,
                'email_verified_at' => now(),
            ]
        );
        $doctor2->assignRole('Doctor');

        // Receptionist
        $receptionist = User::firstOrCreate(
            ['email' => 'receptionist@clinic.com'],
            [
                'name' => 'Sarah Receptionist',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'compensation_type' => 'salaried',
                'base_salary' => 3000.00,
                'email_verified_at' => now(),
            ]
        );
        $receptionist->assignRole('Receptionist');

        // Triage
        $triage = User::firstOrCreate(
            ['email' => 'triage@clinic.com'],
            [
                'name' => 'Nurse Aisha',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'compensation_type' => 'salaried',
                'base_salary' => 2500.00,
                'email_verified_at' => now(),
            ]
        );
        $triage->assignRole('Triage');

        // Laboratory
        $lab = User::firstOrCreate(
            ['email' => 'lab@clinic.com'],
            [
                'name' => 'Lab Tech Omar',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'compensation_type' => 'commission',
                'email_verified_at' => now(),
            ]
        );
        $lab->assignRole('Laboratory');

        // Radiology
        $radiology = User::firstOrCreate(
            ['email' => 'radiology@clinic.com'],
            [
                'name' => 'Radiologist Hassan',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'compensation_type' => 'commission',
                'email_verified_at' => now(),
            ]
        );
        $radiology->assignRole('Radiology');

        // Pharmacy
        $pharmacy = User::firstOrCreate(
            ['email' => 'pharmacy@clinic.com'],
            [
                'name' => 'Pharmacist Mona',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'compensation_type' => 'commission',
                'email_verified_at' => now(),
            ]
        );
        $pharmacy->assignRole('Pharmacy');

        // Patient (demo account)
        $patientUser = User::firstOrCreate(
            ['email' => 'patient@clinic.com'],
            [
                'name' => 'Ali Patient',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $patientUser->assignRole('Patient');
    }
}
