<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Development-only seeder for validating domain workflows.
 *
 * NOT called by DatabaseSeeder — run manually:
 *   php artisan db:seed --class=DevValidationSeeder
 *
 * Creates test users (if missing), patients, and invoices
 * that walk through proper domain transitions.
 */
class DevValidationSeeder extends Seeder
{
    public function run(): void
    {
        // ── Ensure test users exist ─────────────────────────────

        $users = [
            ['name' => 'Dr. John Smith',   'email' => 'doctor@clinic.com',       'role' => 'Doctor'],
            ['name' => 'Sarah Johnson',    'email' => 'receptionist@clinic.com', 'role' => 'Receptionist'],
            ['name' => 'Tom Wilson',       'email' => 'triage@clinic.com',       'role' => 'Triage'],
            ['name' => 'Lab Tech',         'email' => 'lab@clinic.com',          'role' => 'Laboratory'],
            ['name' => 'Radiology Tech',   'email' => 'radiology@clinic.com',    'role' => 'Radiology'],
            ['name' => 'Pharmacist',       'email' => 'pharmacy@clinic.com',     'role' => 'Pharmacy'],
        ];

        foreach ($users as $u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make('password123'),
                    'is_active' => true,
                    'compensation_type' => 'salaried',
                ]
            );
            $user->syncRoles([$u['role']]);
        }

        $doctor       = User::where('email', 'doctor@clinic.com')->first();
        $receptionist = User::where('email', 'receptionist@clinic.com')->first();

        // ── Create patients ─────────────────────────────────────

        $patients = [
            ['first_name' => 'John',  'last_name' => 'Doe',     'phone' => '0700000001', 'gender' => 'Male',   'date_of_birth' => '1990-01-15'],
            ['first_name' => 'Jane',  'last_name' => 'Smith',   'phone' => '0700000002', 'gender' => 'Female', 'date_of_birth' => '1985-06-20'],
            ['first_name' => 'Alice', 'last_name' => 'Mwangi',  'phone' => '0700000003', 'gender' => 'Female', 'date_of_birth' => '1992-03-10'],
            ['first_name' => 'Bob',   'last_name' => 'Ochieng', 'phone' => '0700000004', 'gender' => 'Male',   'date_of_birth' => '1988-11-05'],
        ];

        $createdPatients = [];

        foreach ($patients as $p) {
            $createdPatients[] = Patient::updateOrCreate(
                ['phone' => $p['phone']],
                array_merge($p, [
                    'doctor_id'     => $doctor->id,
                    'status'        => 'registered',
                    'registered_at' => now(),
                ])
            );
        }

        // ── Create invoices via domain methods ──────────────────

        // 1) Lab invoice — fully paid (walks full lifecycle)
        $inv1 = Invoice::create([
            'patient_id'          => $createdPatients[0]->id,
            'patient_type'        => 'clinic',
            'department'          => 'lab',
            'service_name'        => 'Complete Blood Count',
            'total_amount'        => 2000,
            'prescribing_doctor_id' => $doctor->id,
            'status'              => Invoice::STATUS_PENDING,
            'has_prescribed_items' => false,
        ]);
        $inv1->startWork();
        $inv1->markCompleted();
        $inv1->markPaid('cash', $receptionist->id);

        // 2) Radiology invoice — fully paid
        $inv2 = Invoice::create([
            'patient_id'          => $createdPatients[1]->id,
            'patient_type'        => 'clinic',
            'department'          => 'radiology',
            'service_name'        => 'Chest X-Ray',
            'total_amount'        => 3500,
            'prescribing_doctor_id' => $doctor->id,
            'status'              => Invoice::STATUS_PENDING,
            'has_prescribed_items' => false,
        ]);
        $inv2->startWork();
        $inv2->markCompleted();
        $inv2->markPaid('transfer', $receptionist->id);

        // 3) Pharmacy invoice — completed, awaiting payment
        $inv3 = Invoice::create([
            'patient_id'          => $createdPatients[2]->id,
            'patient_type'        => 'clinic',
            'department'          => 'pharmacy',
            'service_name'        => 'Amoxicillin 500mg',
            'total_amount'        => 800,
            'prescribing_doctor_id' => $doctor->id,
            'status'              => Invoice::STATUS_PENDING,
            'has_prescribed_items' => true,
        ]);
        $inv3->startWork();
        $inv3->markCompleted();

        // 4) Consultation invoice — fully paid
        $inv4 = Invoice::create([
            'patient_id'          => $createdPatients[3]->id,
            'patient_type'        => 'clinic',
            'department'          => 'consultation',
            'service_name'        => 'General Consultation',
            'total_amount'        => 1500,
            'prescribing_doctor_id' => $doctor->id,
            'status'              => Invoice::STATUS_PENDING,
            'has_prescribed_items' => false,
        ]);
        $inv4->startWork();
        $inv4->markCompleted();
        $inv4->markPaid('cash', $receptionist->id);

        // 5) Lab invoice — in progress
        Invoice::create([
            'patient_id'          => $createdPatients[0]->id,
            'patient_type'        => 'clinic',
            'department'          => 'lab',
            'service_name'        => 'Urinalysis',
            'total_amount'        => 1200,
            'prescribing_doctor_id' => $doctor->id,
            'status'              => Invoice::STATUS_PENDING,
            'has_prescribed_items' => false,
        ])->startWork();

        // 6) Pending invoice
        Invoice::create([
            'patient_id'          => $createdPatients[1]->id,
            'patient_type'        => 'clinic',
            'department'          => 'radiology',
            'service_name'        => 'Ultrasound',
            'total_amount'        => 4000,
            'prescribing_doctor_id' => $doctor->id,
            'status'              => Invoice::STATUS_PENDING,
            'has_prescribed_items' => false,
        ]);

        // ── Create prescriptions ────────────────────────────────

        $rx1 = Prescription::create([
            'patient_id' => $createdPatients[0]->id,
            'doctor_id'  => $doctor->id,
            'diagnosis'  => 'Upper respiratory tract infection',
            'notes'      => 'Patient presents with sore throat, cough, mild fever for 3 days.',
            'status'     => 'dispensed',
        ]);
        $rx1->items()->createMany([
            ['medication_name' => 'Amoxicillin 500mg', 'dosage' => '500mg', 'frequency' => '3 times daily', 'duration' => '7 days', 'quantity' => 21, 'instructions' => 'Take after meals'],
            ['medication_name' => 'Paracetamol 500mg', 'dosage' => '500mg', 'frequency' => 'Every 6 hours as needed', 'duration' => '5 days', 'quantity' => 20, 'instructions' => 'For fever and pain'],
        ]);

        $rx2 = Prescription::create([
            'patient_id' => $createdPatients[1]->id,
            'doctor_id'  => $doctor->id,
            'diagnosis'  => 'Acute gastritis',
            'notes'      => 'Epigastric pain, nausea. No vomiting. Advised dietary modification.',
            'status'     => 'active',
        ]);
        $rx2->items()->createMany([
            ['medication_name' => 'Omeprazole 20mg', 'dosage' => '20mg', 'frequency' => 'Once daily before breakfast', 'duration' => '14 days', 'quantity' => 14, 'instructions' => 'Take 30 min before food'],
            ['medication_name' => 'Antacid Suspension', 'dosage' => '15ml', 'frequency' => '3 times daily', 'duration' => '7 days', 'quantity' => 1, 'instructions' => 'Take between meals'],
        ]);

        $rx3 = Prescription::create([
            'patient_id' => $createdPatients[2]->id,
            'doctor_id'  => $doctor->id,
            'diagnosis'  => 'Hypertension — newly diagnosed',
            'notes'      => 'BP 160/95. Start on Amlodipine, review in 2 weeks.',
            'status'     => 'active',
        ]);
        $rx3->items()->createMany([
            ['medication_name' => 'Amlodipine 5mg', 'dosage' => '5mg', 'frequency' => 'Once daily', 'duration' => '30 days', 'quantity' => 30, 'instructions' => 'Take in the morning'],
        ]);

        $rx4 = Prescription::create([
            'patient_id' => $createdPatients[3]->id,
            'doctor_id'  => $doctor->id,
            'diagnosis'  => 'Bacterial skin infection',
            'notes'      => 'Localized cellulitis on right forearm. No systemic signs.',
            'status'     => 'dispensed',
        ]);
        $rx4->items()->createMany([
            ['medication_name' => 'Flucloxacillin 500mg', 'dosage' => '500mg', 'frequency' => '4 times daily', 'duration' => '7 days', 'quantity' => 28, 'instructions' => 'Take on empty stomach'],
            ['medication_name' => 'Ibuprofen 400mg', 'dosage' => '400mg', 'frequency' => '3 times daily', 'duration' => '5 days', 'quantity' => 15, 'instructions' => 'Take after meals. Stop if stomach upset.'],
            ['medication_name' => 'Fusidic Acid Cream 2%', 'dosage' => 'Apply topically', 'frequency' => '3 times daily', 'duration' => '7 days', 'quantity' => 1, 'instructions' => 'Apply thin layer to affected area'],
        ]);

        $this->command->info('DevValidationSeeder: 4 patients, 6 invoices (3 paid, 1 completed, 1 in_progress, 1 pending), 4 prescriptions (2 active, 2 dispensed)');
    }
}
