<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\DoctorPayout;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\RevenueLedger;
use App\Models\ServiceCatalog;
use App\Models\StockMovement;
use App\Models\TriageVital;
use App\Models\User;
use App\Models\Visit;
use App\Services\FinancialDistributionService;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds realistic clinic workflow data that follows the natural patient journey:
 *   Registration → Triage → Consultation → Prescriptions → Lab/Rad → Pharmacy → Payment → Payouts
 *
 * Prerequisite: Run PharmacyStockSeeder and LaboratoryStockSeeder first.
 *
 * Usage: php artisan db:seed --class=WorkflowSeeder
 */
class WorkflowSeeder extends Seeder
{
    private User $owner;
    private User $drAhmed;
    private User $drFatima;
    private ?User $drSidra;
    private User $receptionist;
    private User $triageNurse;
    private User $labTech;
    private User $radiologist;
    private User $pharmacist;
    private InventoryService $inventoryService;

    public function run(): void
    {
        $this->command->info('=== WorkflowSeeder: Building realistic clinic data ===');

        // Resolve existing users
        $this->owner = User::role('Owner')->first();
        $this->drAhmed = User::where('email', 'doctor@clinic.com')->first();
        $this->drFatima = User::where('email', 'doctor2@clinic.com')->first();
        $this->drSidra = User::where('email', 'muneebkhaliq@gmail.com')->first()
            ?? $this->drFatima;
        $this->receptionist = User::role('Receptionist')->first();
        $this->triageNurse = User::role('Triage')->first();
        $this->labTech = User::role('Laboratory')->first();
        $this->radiologist = User::role('Radiology')->first();
        $this->pharmacist = User::role('Pharmacy')->first();
        $this->inventoryService = app(InventoryService::class);

        // Phase 1: Register 2 more doctors
        $this->seedDoctors();
        $this->command->info('Phase 1: Doctors registered');

        // Phase 2: Register 10 new patients
        $patients = $this->seedPatients();
        $this->command->info('Phase 2: ' . count($patients) . ' patients registered');

        // Phase 3: Appointments
        $this->seedAppointments($patients);
        $this->command->info('Phase 3: Appointments created');

        // Phase 4: Triage vitals
        $this->seedTriageVitals($patients);
        $this->command->info('Phase 4: Triage vitals recorded');

        // Phase 5: Visits & Consultation invoices
        $visits = $this->seedVisitsAndConsultations($patients);
        $this->command->info('Phase 5: Visits & consultations created');

        // Phase 6: Prescriptions
        $this->seedPrescriptions($patients, $visits);
        $this->command->info('Phase 6: Prescriptions written');

        // Phase 7: Lab requests
        $this->seedLabRequests($patients);
        $this->command->info('Phase 7: Lab requests created');

        // Phase 8: Radiology requests
        $this->seedRadiologyRequests($patients);
        $this->command->info('Phase 8: Radiology requests created');

        // Phase 9: Pharmacy dispensing (stock outflow + invoices)
        $this->seedPharmacyDispensing($patients);
        $this->command->info('Phase 9: Pharmacy dispensing done');

        // Phase 10: Discount requests
        $this->seedDiscountRequests();
        $this->command->info('Phase 10: Discount requests created');

        // Phase 11: Expenses
        $this->seedExpenses();
        $this->command->info('Phase 11: Expenses recorded');

        // Phase 12: Procurement requests
        $this->seedProcurementRequests();
        $this->command->info('Phase 12: Procurement requests created');

        // Phase 13: Doctor payouts
        $this->seedPayouts();
        $this->command->info('Phase 13: Payouts created');

        $this->command->info('=== WorkflowSeeder complete ===');
        $this->printSummary();
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 1: Additional Doctors
    // ═══════════════════════════════════════════════════════════════════

    private function seedDoctors(): void
    {
        $newDoctors = [
            [
                'name'  => 'Dr. Imran Shah',
                'email' => 'dr.imran@clinic.com',
                'compensation_type' => 'commission',
            ],
            [
                'name'  => 'Dr. Ayesha Khan',
                'email' => 'dr.ayesha@clinic.com',
                'compensation_type' => 'hybrid',
                'base_salary' => 4000.00,
            ],
        ];

        foreach ($newDoctors as $doc) {
            $user = User::firstOrCreate(
                ['email' => $doc['email']],
                array_merge($doc, [
                    'password' => bcrypt('password123'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ])
            );
            $user->assignRole('Doctor');
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 2: Patients
    // ═══════════════════════════════════════════════════════════════════

    private function seedPatients(): array
    {
        $patientData = [
            // Group A: Full workflow (triage → consultation → prescription → dispensed)
            ['first_name' => 'Hamza',    'last_name' => 'Malik',     'gender' => 'male',   'date_of_birth' => '1990-03-15', 'phone' => '03001234567', 'cnic' => '3520112345671', 'doctor_id' => $this->drAhmed->id,  'status' => 'checked_out'],
            ['first_name' => 'Sana',     'last_name' => 'Abbas',     'gender' => 'female', 'date_of_birth' => '1985-07-22', 'phone' => '03009876543', 'cnic' => '3520112345672', 'doctor_id' => $this->drFatima->id, 'status' => 'checked_out'],
            ['first_name' => 'Raheel',   'last_name' => 'Ahmed',     'gender' => 'male',   'date_of_birth' => '1978-01-10', 'phone' => '03211234567', 'cnic' => '3520112345673', 'doctor_id' => $this->drAhmed->id,  'status' => 'checked_out'],

            // Group B: In consultation (triage done, with doctor now)
            ['first_name' => 'Farah',    'last_name' => 'Naz',       'gender' => 'female', 'date_of_birth' => '1995-11-05', 'phone' => '03331234567', 'cnic' => '3520112345674', 'doctor_id' => $this->drFatima->id, 'status' => 'with_doctor'],
            ['first_name' => 'Usman',    'last_name' => 'Ali',       'gender' => 'male',   'date_of_birth' => '2000-06-18', 'phone' => '03451234567', 'cnic' => '3520112345675', 'doctor_id' => $this->drSidra->id,  'status' => 'with_doctor'],

            // Group C: At triage (registered, waiting for triage or being triaged)
            ['first_name' => 'Zainab',   'last_name' => 'Hussain',   'gender' => 'female', 'date_of_birth' => '2005-09-30', 'phone' => '03121234567', 'cnic' => '3520112345676', 'doctor_id' => $this->drAhmed->id,  'status' => 'in_triage'],
            ['first_name' => 'Bilal',    'last_name' => 'Qureshi',   'gender' => 'male',   'date_of_birth' => '1970-04-25', 'phone' => '03001111111', 'cnic' => '3520112345677', 'doctor_id' => $this->drFatima->id, 'status' => 'in_triage'],

            // Group D: Just registered (waiting)
            ['first_name' => 'Aqsa',     'last_name' => 'Rehman',    'gender' => 'female', 'date_of_birth' => '1988-12-01', 'phone' => '03009999999', 'cnic' => '3520112345678', 'doctor_id' => $this->drAhmed->id,  'status' => 'registered'],
            ['first_name' => 'Tariq',    'last_name' => 'Mehmood',   'gender' => 'male',   'date_of_birth' => '1965-08-14', 'phone' => '03008888888', 'cnic' => '3520112345679', 'doctor_id' => $this->drSidra->id,  'status' => 'registered'],

            // Group E: Pediatric patient
            ['first_name' => 'Amna',     'last_name' => 'Iqbal',     'gender' => 'female', 'date_of_birth' => '2018-02-20', 'phone' => '03007777777', 'cnic' => '3520112345680', 'doctor_id' => $this->drFatima->id, 'status' => 'checked_out'],
        ];

        $patients = [];
        foreach ($patientData as $data) {
            $status = $data['status'];
            unset($data['status']);
            $data['registration_type'] = 'walk_in';

            $p = Patient::firstOrCreate(
                ['cnic' => $data['cnic']],
                $data
            );
            // Force the status since firstOrCreate won't update it
            $p->update(['status' => $status]);
            $patients[$p->first_name] = $p;
        }

        return $patients;
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 3: Appointments
    // ═══════════════════════════════════════════════════════════════════

    private function seedAppointments(array $patients): void
    {
        $appointments = [
            // Completed appointments (Groups A patients)
            ['patient' => 'Hamza',  'doctor_id' => $this->drAhmed->id,  'status' => Appointment::STATUS_COMPLETED, 'type' => Appointment::TYPE_FIRST_VISIT,   'days_ago' => 3],
            ['patient' => 'Sana',   'doctor_id' => $this->drFatima->id, 'status' => Appointment::STATUS_COMPLETED, 'type' => Appointment::TYPE_CONSULTATION,  'days_ago' => 2],
            ['patient' => 'Raheel', 'doctor_id' => $this->drAhmed->id,  'status' => Appointment::STATUS_COMPLETED, 'type' => Appointment::TYPE_FIRST_VISIT,   'days_ago' => 1],

            // In-progress appointments (Group B)
            ['patient' => 'Farah',  'doctor_id' => $this->drFatima->id, 'status' => Appointment::STATUS_IN_PROGRESS, 'type' => Appointment::TYPE_FIRST_VISIT, 'days_ago' => 0],
            ['patient' => 'Usman',  'doctor_id' => $this->drSidra->id,  'status' => Appointment::STATUS_IN_PROGRESS, 'type' => Appointment::TYPE_CONSULTATION, 'days_ago' => 0],

            // Scheduled for today (Group C)
            ['patient' => 'Zainab', 'doctor_id' => $this->drAhmed->id,  'status' => Appointment::STATUS_CONFIRMED, 'type' => Appointment::TYPE_FIRST_VISIT, 'days_ago' => 0],
            ['patient' => 'Bilal',  'doctor_id' => $this->drFatima->id, 'status' => Appointment::STATUS_CONFIRMED, 'type' => Appointment::TYPE_CONSULTATION, 'days_ago' => 0],

            // Future appointment
            ['patient' => 'Aqsa',   'doctor_id' => $this->drAhmed->id,  'status' => Appointment::STATUS_SCHEDULED, 'type' => Appointment::TYPE_FOLLOW_UP, 'days_ago' => -2],

            // No-show and cancelled
            ['patient' => 'Tariq',  'doctor_id' => $this->drSidra->id,  'status' => Appointment::STATUS_NO_SHOW,   'type' => Appointment::TYPE_FIRST_VISIT, 'days_ago' => 5],
            ['patient' => 'Amna',   'doctor_id' => $this->drFatima->id, 'status' => Appointment::STATUS_COMPLETED, 'type' => Appointment::TYPE_CONSULTATION, 'days_ago' => 4],
        ];

        foreach ($appointments as $apt) {
            $patient = $patients[$apt['patient']] ?? null;
            if (!$patient) continue;

            Appointment::firstOrCreate(
                ['patient_id' => $patient->id, 'scheduled_at' => now()->subDays($apt['days_ago'])->setTime(9 + rand(0, 6), rand(0, 59))],
                [
                    'patient_id'   => $patient->id,
                    'doctor_id'    => $apt['doctor_id'],
                    'booked_by'    => $this->receptionist->id,
                    'scheduled_at' => now()->subDays($apt['days_ago'])->setTime(9 + rand(0, 6), rand(0, 59)),
                    'type'         => $apt['type'],
                    'status'       => $apt['status'],
                    'reason'       => 'Routine checkup',
                ]
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 4: Triage Vitals
    // ═══════════════════════════════════════════════════════════════════

    private function seedTriageVitals(array $patients): void
    {
        // Groups A, B, C, E all went through triage
        $triageData = [
            ['patient' => 'Hamza',  'bp' => '120/80',  'temp' => 37.0, 'pulse' => 72,  'rr' => 16, 'weight' => 75.0,  'height' => 175.0, 'spo2' => 98.0, 'complaint' => 'Persistent cough for 2 weeks, mild fever',            'priority' => 'normal', 'days_ago' => 3],
            ['patient' => 'Sana',   'bp' => '110/70',  'temp' => 36.8, 'pulse' => 68,  'rr' => 14, 'weight' => 60.0,  'height' => 162.0, 'spo2' => 99.0, 'complaint' => 'Severe headache and dizziness for 3 days',            'priority' => 'normal', 'days_ago' => 2],
            ['patient' => 'Raheel', 'bp' => '145/95',  'temp' => 37.2, 'pulse' => 88,  'rr' => 18, 'weight' => 92.0,  'height' => 170.0, 'spo2' => 96.0, 'complaint' => 'Chest pain on exertion, shortness of breath',          'priority' => 'urgent', 'days_ago' => 1],
            ['patient' => 'Farah',  'bp' => '115/75',  'temp' => 38.5, 'pulse' => 92,  'rr' => 20, 'weight' => 55.0,  'height' => 158.0, 'spo2' => 97.0, 'complaint' => 'High fever, body aches, sore throat since yesterday', 'priority' => 'normal', 'days_ago' => 0],
            ['patient' => 'Usman',  'bp' => '125/82',  'temp' => 36.9, 'pulse' => 70,  'rr' => 15, 'weight' => 70.0,  'height' => 178.0, 'spo2' => 98.0, 'complaint' => 'Lower back pain radiating to left leg',               'priority' => 'normal', 'days_ago' => 0],
            ['patient' => 'Zainab', 'bp' => '100/65',  'temp' => 37.8, 'pulse' => 100, 'rr' => 22, 'weight' => 48.0,  'height' => 155.0, 'spo2' => 95.0, 'complaint' => 'Abdominal pain, nausea, vomiting since morning',       'priority' => 'urgent', 'days_ago' => 0],
            ['patient' => 'Bilal',  'bp' => '160/100', 'temp' => 36.5, 'pulse' => 78,  'rr' => 16, 'weight' => 85.0,  'height' => 168.0, 'spo2' => 97.0, 'complaint' => 'Routine blood pressure follow-up, on medications',     'priority' => 'normal', 'days_ago' => 0],
            ['patient' => 'Amna',   'bp' => '90/60',   'temp' => 38.2, 'pulse' => 110, 'rr' => 24, 'weight' => 15.0,  'height' => 95.0,  'spo2' => 96.0, 'complaint' => 'Pediatric: fever, runny nose, not eating well',        'priority' => 'normal', 'days_ago' => 4],
        ];

        foreach ($triageData as $data) {
            $patient = $patients[$data['patient']] ?? null;
            if (!$patient) continue;

            TriageVital::firstOrCreate(
                ['patient_id' => $patient->id, 'blood_pressure' => $data['bp']],
                [
                    'patient_id'        => $patient->id,
                    'blood_pressure'    => $data['bp'],
                    'temperature'       => $data['temp'],
                    'pulse_rate'        => $data['pulse'],
                    'respiratory_rate'  => $data['rr'],
                    'weight'            => $data['weight'],
                    'height'            => $data['height'],
                    'oxygen_saturation' => $data['spo2'],
                    'chief_complaint'   => $data['complaint'],
                    'priority'          => $data['priority'],
                    'recorded_by'       => $this->triageNurse->id,
                    'created_at'        => now()->subDays($data['days_ago']),
                ]
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 5: Visits & Consultation Invoices
    // ═══════════════════════════════════════════════════════════════════

    private function seedVisitsAndConsultations(array $patients): array
    {
        $generalConsult = ServiceCatalog::where('code', 'CON-GEN')->first();
        $followUp       = ServiceCatalog::where('code', 'CON-FUP')->first();
        $pediatric      = ServiceCatalog::where('code', 'CON-PED')->first();
        $emergency      = ServiceCatalog::where('code', 'CON-EMG')->first();

        $visitData = [
            // Group A: Full completed visits
            [
                'patient' => 'Hamza', 'doctor' => $this->drAhmed, 'service' => $generalConsult,
                'status' => 'completed', 'notes' => 'Patient presents with productive cough and low-grade fever. Lungs: mild crackles in right lower lobe. Suspect upper respiratory tract infection. Advised CBC and chest X-ray. Prescribed antibiotics.',
                'days_ago' => 3,
            ],
            [
                'patient' => 'Sana', 'doctor' => $this->drFatima, 'service' => $generalConsult,
                'status' => 'completed', 'notes' => 'Patient complains of recurring headaches and dizziness. BP normal. Neurological exam unremarkable. Advised blood glucose, lipid profile, and thyroid function tests. Started on migraine prophylaxis.',
                'days_ago' => 2,
            ],
            [
                'patient' => 'Raheel', 'doctor' => $this->drAhmed, 'service' => $emergency,
                'status' => 'completed', 'notes' => 'Middle-aged male with chest pain on exertion. Hypertensive (145/95). ECG requested urgently. Started on aspirin and antihypertensive. Lipid panel and renal function tests ordered.',
                'days_ago' => 1,
            ],
            [
                'patient' => 'Amna', 'doctor' => $this->drFatima, 'service' => $pediatric,
                'status' => 'completed', 'notes' => 'Pediatric patient 5 years. Fever 38.2C, runny nose, mild ear redness. Likely viral URTI with possible otitis media. Prescribed paracetamol suspension and antibiotics as precaution. Follow-up in 3 days.',
                'days_ago' => 4,
            ],

            // Group B: Currently with doctor (with_doctor visits)
            [
                'patient' => 'Farah', 'doctor' => $this->drFatima, 'service' => $generalConsult,
                'status' => 'with_doctor', 'notes' => null,
                'days_ago' => 0,
            ],
            [
                'patient' => 'Usman', 'doctor' => $this->drSidra, 'service' => $followUp,
                'status' => 'with_doctor', 'notes' => null,
                'days_ago' => 0,
            ],
        ];

        $visits = [];

        foreach ($visitData as $vd) {
            $patient = $patients[$vd['patient']] ?? null;
            if (!$patient) continue;

            // Create or find visit
            $visit = Visit::firstOrCreate(
                ['patient_id' => $patient->id, 'doctor_id' => $vd['doctor']->id, 'visit_date' => now()->subDays($vd['days_ago'])->toDateString()],
                [
                    'patient_id'        => $patient->id,
                    'doctor_id'         => $vd['doctor']->id,
                    'triage_nurse_id'   => $this->triageNurse->id,
                    'visit_date'        => now()->subDays($vd['days_ago'])->toDateString(),
                    'status'            => $vd['status'],
                    'consultation_notes' => $vd['notes'],
                    'created_at'        => now()->subDays($vd['days_ago']),
                ]
            );

            $visits[$vd['patient']] = $visit;

            // Create consultation invoice for completed visits (paid upfront)
            if ($vd['status'] === 'completed') {
                $service = $vd['service'];
                $invoice = Invoice::firstOrCreate(
                    ['visit_id' => $visit->id, 'department' => 'consultation'],
                    [
                        'patient_id'            => $patient->id,
                        'patient_type'          => 'walk_in',
                        'department'            => 'consultation',
                        'service_name'          => $service->name,
                        'service_catalog_id'    => $service->id,
                        'total_amount'          => $service->price,
                        'net_amount'            => $service->price,
                        'status'                => Invoice::STATUS_PENDING,
                        'prescribing_doctor_id' => $vd['doctor']->id,
                        'performed_by_user_id'  => $vd['doctor']->id,
                        'created_by_user_id'    => $this->receptionist->id,
                        'visit_id'              => $visit->id,
                        'created_at'            => now()->subDays($vd['days_ago']),
                    ]
                );

                // Link visit to invoice
                if (!$visit->consultation_fee_invoice_id) {
                    $visit->update(['consultation_fee_invoice_id' => $invoice->id]);
                }

                // Mark paid (triggers FinancialDistributionService)
                if ($invoice->status !== Invoice::STATUS_PAID) {
                    try {
                        $invoice->markPaid('cash', $this->receptionist->id);
                    } catch (\Exception $e) {
                        $this->command->warn("  Could not pay consultation invoice #{$invoice->id}: {$e->getMessage()}");
                    }
                }
            }
        }

        return $visits;
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 6: Prescriptions
    // ═══════════════════════════════════════════════════════════════════

    private function seedPrescriptions(array $patients, array $visits): void
    {
        // Need pharmacy items to exist
        $paracetamol  = InventoryItem::where('sku', 'PH-PCM500')->first();
        $amoxicillin  = InventoryItem::where('sku', 'PH-AMX500')->first();
        $azithromycin = InventoryItem::where('sku', 'PH-AZT500')->first();
        $omeprazole   = InventoryItem::where('sku', 'PH-OMP020')->first();
        $cetirizine   = InventoryItem::where('sku', 'PH-CTZ010')->first();
        $amlodipine   = InventoryItem::where('sku', 'PH-AML005')->first();
        $metformin    = InventoryItem::where('sku', 'PH-MET500')->first();
        $aspirin      = InventoryItem::where('sku', 'PH-ASP300')->first();
        $losartan     = InventoryItem::where('sku', 'PH-LOS050')->first();
        $prednisolone = InventoryItem::where('sku', 'PH-PRD005')->first();
        $coughSyrup   = InventoryItem::where('sku', 'PH-CGH001')->first();
        $ibuprofen    = InventoryItem::where('sku', 'PH-IBU400')->first();

        if (!$paracetamol) {
            $this->command->warn('  Pharmacy stock not seeded. Skipping prescriptions. Run PharmacyStockSeeder first.');
            return;
        }

        $prescriptions = [
            // Hamza: URTI — antibiotics + analgesics + cough syrup
            [
                'patient' => 'Hamza', 'doctor' => $this->drAhmed, 'status' => 'dispensed',
                'diagnosis' => 'Upper Respiratory Tract Infection (URTI)',
                'notes' => 'Complete full course of antibiotics. Take paracetamol for fever. Cough syrup at bedtime.',
                'items' => [
                    ['item' => $azithromycin, 'name' => 'Azithromycin 500mg', 'dosage' => '500mg', 'frequency' => 'Once daily', 'duration' => '3 days', 'qty' => 3],
                    ['item' => $paracetamol,  'name' => 'Paracetamol 500mg',  'dosage' => '500mg', 'frequency' => 'Every 6 hours as needed', 'duration' => '5 days', 'qty' => 20],
                    ['item' => $coughSyrup,   'name' => 'Cough Syrup',        'dosage' => '10ml',  'frequency' => 'At bedtime', 'duration' => '7 days', 'qty' => 1],
                ],
                'days_ago' => 3,
            ],
            // Sana: Migraine prophylaxis
            [
                'patient' => 'Sana', 'doctor' => $this->drFatima, 'status' => 'dispensed',
                'diagnosis' => 'Chronic Migraine with dizziness',
                'notes' => 'Start with low-dose amlodipine. Monitor blood pressure weekly.',
                'items' => [
                    ['item' => $amlodipine,  'name' => 'Amlodipine 5mg',   'dosage' => '5mg',  'frequency' => 'Once daily at bedtime', 'duration' => '30 days', 'qty' => 30],
                    ['item' => $paracetamol, 'name' => 'Paracetamol 500mg', 'dosage' => '1g',   'frequency' => 'As needed for headache', 'duration' => '30 days', 'qty' => 30],
                ],
                'days_ago' => 2,
            ],
            // Raheel: Cardiac risk — urgent
            [
                'patient' => 'Raheel', 'doctor' => $this->drAhmed, 'status' => 'dispensed',
                'diagnosis' => 'Hypertensive emergency, suspected angina',
                'notes' => 'Urgent: Start aspirin immediately. Losartan for BP control. Review after ECG results.',
                'items' => [
                    ['item' => $aspirin,  'name' => 'Aspirin 300mg',  'dosage' => '300mg', 'frequency' => 'Once daily after breakfast', 'duration' => '30 days', 'qty' => 30],
                    ['item' => $losartan, 'name' => 'Losartan 50mg',  'dosage' => '50mg',  'frequency' => 'Once daily morning', 'duration' => '30 days', 'qty' => 30],
                ],
                'days_ago' => 1,
            ],
            // Amna: Pediatric URTI
            [
                'patient' => 'Amna', 'doctor' => $this->drFatima, 'status' => 'dispensed',
                'diagnosis' => 'Viral URTI with possible otitis media',
                'notes' => 'Paracetamol for fever (weight-based dosing). Amoxicillin if no improvement in 48 hours.',
                'items' => [
                    ['item' => $paracetamol, 'name' => 'Paracetamol 500mg', 'dosage' => '250mg', 'frequency' => 'Every 6 hours', 'duration' => '5 days', 'qty' => 10],
                    ['item' => $amoxicillin, 'name' => 'Amoxicillin 500mg', 'dosage' => '250mg', 'frequency' => 'Every 8 hours', 'duration' => '7 days', 'qty' => 21],
                    ['item' => $cetirizine,  'name' => 'Cetirizine 10mg',   'dosage' => '5mg',   'frequency' => 'Once at bedtime', 'duration' => '5 days', 'qty' => 5],
                ],
                'days_ago' => 4,
            ],
            // Active prescription (not yet dispensed) — for patient currently with doctor
            [
                'patient' => 'Farah', 'doctor' => $this->drFatima, 'status' => 'active',
                'diagnosis' => 'Acute pharyngitis with fever',
                'notes' => 'Suspected streptococcal pharyngitis. Start antibiotics pending culture results.',
                'items' => [
                    ['item' => $amoxicillin, 'name' => 'Amoxicillin 500mg', 'dosage' => '500mg', 'frequency' => 'Every 8 hours', 'duration' => '10 days', 'qty' => 30],
                    ['item' => $ibuprofen,   'name' => 'Ibuprofen 400mg',   'dosage' => '400mg', 'frequency' => 'Every 8 hours as needed', 'duration' => '5 days', 'qty' => 15],
                    ['item' => $omeprazole,  'name' => 'Omeprazole 20mg',   'dosage' => '20mg',  'frequency' => 'Before breakfast', 'duration' => '10 days', 'qty' => 10],
                ],
                'days_ago' => 0,
            ],
        ];

        foreach ($prescriptions as $rxData) {
            $patient = $patients[$rxData['patient']] ?? null;
            $visit   = $visits[$rxData['patient']] ?? null;
            if (!$patient) continue;

            $rx = Prescription::firstOrCreate(
                ['patient_id' => $patient->id, 'doctor_id' => $rxData['doctor']->id, 'diagnosis' => $rxData['diagnosis']],
                [
                    'patient_id' => $patient->id,
                    'doctor_id'  => $rxData['doctor']->id,
                    'visit_id'   => $visit?->id,
                    'diagnosis'  => $rxData['diagnosis'],
                    'notes'      => $rxData['notes'],
                    'status'     => $rxData['status'],
                    'created_at' => now()->subDays($rxData['days_ago']),
                ]
            );

            foreach ($rxData['items'] as $item) {
                if (!$item['item']) continue;
                PrescriptionItem::firstOrCreate(
                    ['prescription_id' => $rx->id, 'inventory_item_id' => $item['item']->id],
                    [
                        'prescription_id'   => $rx->id,
                        'inventory_item_id' => $item['item']->id,
                        'medication_name'   => $item['name'],
                        'dosage'            => $item['dosage'],
                        'frequency'         => $item['frequency'],
                        'duration'          => $item['duration'],
                        'quantity'          => $item['qty'],
                        'instructions'      => "Take {$item['dosage']} {$item['frequency']} for {$item['duration']}",
                    ]
                );
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 7: Lab Requests
    // ═══════════════════════════════════════════════════════════════════

    private function seedLabRequests(array $patients): void
    {
        $cbc    = ServiceCatalog::where('code', 'LAB-CBC')->first();
        $lipid  = ServiceCatalog::where('code', 'LAB-LPD')->first();
        $lft    = ServiceCatalog::where('code', 'LAB-LFT')->first();
        $rft    = ServiceCatalog::where('code', 'LAB-RFT')->first();
        $thyroid = ServiceCatalog::where('code', 'LAB-THY')->first();
        $glucose = ServiceCatalog::where('code', 'LAB-GLF')->first();
        $hba1c  = ServiceCatalog::where('code', 'LAB-HBA')->first();

        $labInvoices = [
            // Hamza: CBC — PAID + COMPLETED (results filled)
            [
                'patient' => 'Hamza', 'service' => $cbc, 'doctor' => $this->drAhmed,
                'status' => 'paid_completed', 'days_ago' => 3,
                'report' => "WBC: 11.2 ×10³/µL (H), RBC: 4.8 ×10⁶/µL, Hb: 14.2 g/dL, Platelets: 280 ×10³/µL\nNeutrophils: 72% (H), Lymphocytes: 20%, Monocytes: 5%\nConclusion: Mild leukocytosis with neutrophilia, consistent with bacterial infection.",
            ],
            // Sana: Lipid Profile + Thyroid — PAID + COMPLETED
            [
                'patient' => 'Sana', 'service' => $lipid, 'doctor' => $this->drFatima,
                'status' => 'paid_completed', 'days_ago' => 2,
                'report' => "Total Cholesterol: 195 mg/dL, HDL: 55 mg/dL, LDL: 118 mg/dL, Triglycerides: 110 mg/dL, VLDL: 22 mg/dL\nConclusion: Lipid profile within normal limits. LDL borderline — dietary modifications recommended.",
            ],
            [
                'patient' => 'Sana', 'service' => $thyroid, 'doctor' => $this->drFatima,
                'status' => 'paid_completed', 'days_ago' => 2,
                'report' => "TSH: 3.2 mIU/L (Normal), Free T4: 1.1 ng/dL (Normal), Free T3: 3.0 pg/mL (Normal)\nConclusion: Thyroid function within normal limits. No thyroid-related cause for headaches.",
            ],
            // Raheel: Lipid + RFT — PAID, awaiting results
            [
                'patient' => 'Raheel', 'service' => $lipid, 'doctor' => $this->drAhmed,
                'status' => 'paid_pending', 'days_ago' => 1,
            ],
            [
                'patient' => 'Raheel', 'service' => $rft, 'doctor' => $this->drAhmed,
                'status' => 'paid_pending', 'days_ago' => 1,
            ],
            // Bilal: Glucose fasting — PENDING payment (not yet paid)
            [
                'patient' => 'Bilal', 'service' => $glucose, 'doctor' => $this->drFatima,
                'status' => 'pending', 'days_ago' => 0,
            ],
        ];

        foreach ($labInvoices as $labData) {
            $patient = $patients[$labData['patient']] ?? null;
            $service = $labData['service'];
            if (!$patient || !$service) continue;

            $invoice = Invoice::firstOrCreate(
                ['patient_id' => $patient->id, 'service_catalog_id' => $service->id, 'department' => 'lab'],
                [
                    'patient_id'            => $patient->id,
                    'patient_type'          => 'walk_in',
                    'department'            => 'lab',
                    'service_name'          => $service->name,
                    'service_catalog_id'    => $service->id,
                    'total_amount'          => $service->price,
                    'net_amount'            => $service->price,
                    'status'                => Invoice::STATUS_PENDING,
                    'prescribing_doctor_id' => $labData['doctor']->id,
                    'created_by_user_id'    => $this->receptionist->id,
                    'created_at'            => now()->subDays($labData['days_ago']),
                ]
            );

            if (in_array($labData['status'], ['paid_completed', 'paid_pending']) && $invoice->status !== Invoice::STATUS_PAID) {
                try {
                    $invoice->markPaid('cash', $this->receptionist->id);
                } catch (\Exception $e) {
                    $this->command->warn("  Lab invoice #{$invoice->id} payment failed: {$e->getMessage()}");
                }
            }

            // For completed ones: assign performer, report, and trigger deferred distribution
            if ($labData['status'] === 'paid_completed') {
                $invoice->refresh();
                if ($invoice->status === Invoice::STATUS_PAID && !$invoice->report_text) {
                    try {
                        $invoice->update([
                            'performed_by_user_id' => $this->labTech->id,
                            'report_text'          => $labData['report'],
                        ]);
                        $invoice->completeAndDistribute();
                    } catch (\Exception $e) {
                        $this->command->warn("  Lab invoice #{$invoice->id} completion failed: {$e->getMessage()}");
                    }
                }
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 8: Radiology Requests
    // ═══════════════════════════════════════════════════════════════════

    private function seedRadiologyRequests(array $patients): void
    {
        $chestXray  = ServiceCatalog::where('code', 'RAD-CXR')->first();
        $ecg        = ServiceCatalog::where('code', 'RAD-ECG')->first();
        $abdominalUS = ServiceCatalog::where('code', 'RAD-UAB')->first();
        $lumbarXray = ServiceCatalog::where('code', 'RAD-LSP')->first();

        $radInvoices = [
            // Hamza: Chest X-ray — PAID + COMPLETED
            [
                'patient' => 'Hamza', 'service' => $chestXray, 'doctor' => $this->drAhmed,
                'status' => 'paid_completed', 'days_ago' => 3,
                'report' => "PA chest radiograph obtained.\nHeart size: Normal cardiothoracic ratio.\nLungs: Mild peribronchial thickening in right lower zone. No focal consolidation or pleural effusion.\nMediastinum: Normal.\nBony thorax: Intact.\nImpression: Mild bronchitic changes in right lower lobe, consistent with lower respiratory tract infection. No pneumonia.",
            ],
            // Raheel: ECG — PAID + COMPLETED (urgent)
            [
                'patient' => 'Raheel', 'service' => $ecg, 'doctor' => $this->drAhmed,
                'status' => 'paid_completed', 'days_ago' => 1,
                'report' => "12-Lead ECG performed.\nRate: 88 bpm, Regular rhythm.\nP-wave: Normal morphology.\nPR interval: 0.16s (normal).\nQRS: 0.08s, no bundle branch block.\nST segment: No acute ST changes. Mild ST depression in V5-V6 (non-specific).\nT-wave: Normal.\nAxis: Normal.\nImpression: Sinus rhythm, mild non-specific ST-T changes in lateral leads. Clinical correlation with stress test recommended.",
            ],
            // Usman: Lumbar spine X-ray — PAID, awaiting results
            [
                'patient' => 'Usman', 'service' => $lumbarXray, 'doctor' => $this->drSidra,
                'status' => 'paid_pending', 'days_ago' => 0,
            ],
            // Zainab: Abdominal US — PENDING (not yet paid)
            [
                'patient' => 'Zainab', 'service' => $abdominalUS, 'doctor' => $this->drAhmed,
                'status' => 'pending', 'days_ago' => 0,
            ],
        ];

        foreach ($radInvoices as $radData) {
            $patient = $patients[$radData['patient']] ?? null;
            $service = $radData['service'];
            if (!$patient || !$service) continue;

            $invoice = Invoice::firstOrCreate(
                ['patient_id' => $patient->id, 'service_catalog_id' => $service->id, 'department' => 'radiology'],
                [
                    'patient_id'            => $patient->id,
                    'patient_type'          => 'walk_in',
                    'department'            => 'radiology',
                    'service_name'          => $service->name,
                    'service_catalog_id'    => $service->id,
                    'total_amount'          => $service->price,
                    'net_amount'            => $service->price,
                    'status'                => Invoice::STATUS_PENDING,
                    'prescribing_doctor_id' => $radData['doctor']->id,
                    'created_by_user_id'    => $this->receptionist->id,
                    'created_at'            => now()->subDays($radData['days_ago']),
                ]
            );

            if (in_array($radData['status'], ['paid_completed', 'paid_pending']) && $invoice->status !== Invoice::STATUS_PAID) {
                try {
                    $invoice->markPaid('cash', $this->receptionist->id);
                } catch (\Exception $e) {
                    $this->command->warn("  Rad invoice #{$invoice->id} payment failed: {$e->getMessage()}");
                }
            }

            if ($radData['status'] === 'paid_completed') {
                $invoice->refresh();
                if ($invoice->status === Invoice::STATUS_PAID && !$invoice->report_text) {
                    try {
                        $invoice->update([
                            'performed_by_user_id' => $this->radiologist->id,
                            'report_text'          => $radData['report'],
                        ]);
                        $invoice->completeAndDistribute();
                    } catch (\Exception $e) {
                        $this->command->warn("  Rad invoice #{$invoice->id} completion failed: {$e->getMessage()}");
                    }
                }
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 9: Pharmacy Dispensing (Stock Outflow + Invoices)
    // ═══════════════════════════════════════════════════════════════════

    private function seedPharmacyDispensing(array $patients): void
    {
        $dispensingService = ServiceCatalog::where('code', 'PHR-DIS')->first();

        // Build dispenses from the prescriptions (Groups A patients were dispensed)
        $dispenses = [
            // Hamza's meds
            [
                'patient' => 'Hamza', 'doctor' => $this->drAhmed, 'days_ago' => 3,
                'items' => [
                    ['sku' => 'PH-AZT500', 'qty' => 3],
                    ['sku' => 'PH-PCM500', 'qty' => 20],
                    ['sku' => 'PH-CGH001', 'qty' => 1],
                ],
            ],
            // Sana's meds
            [
                'patient' => 'Sana', 'doctor' => $this->drFatima, 'days_ago' => 2,
                'items' => [
                    ['sku' => 'PH-AML005', 'qty' => 30],
                    ['sku' => 'PH-PCM500', 'qty' => 30],
                ],
            ],
            // Raheel's meds
            [
                'patient' => 'Raheel', 'doctor' => $this->drAhmed, 'days_ago' => 1,
                'items' => [
                    ['sku' => 'PH-ASP300', 'qty' => 30],
                    ['sku' => 'PH-LOS050', 'qty' => 30],
                ],
            ],
            // Amna's meds (pediatric)
            [
                'patient' => 'Amna', 'doctor' => $this->drFatima, 'days_ago' => 4,
                'items' => [
                    ['sku' => 'PH-PCM500', 'qty' => 10],
                    ['sku' => 'PH-AMX500', 'qty' => 21],
                    ['sku' => 'PH-CTZ010', 'qty' => 5],
                ],
            ],
        ];

        foreach ($dispenses as $disp) {
            $patient = $patients[$disp['patient']] ?? null;
            if (!$patient) continue;

            // Calculate total from items
            $totalAmount = 0;
            $totalCogs = 0;
            $invoiceItems = [];

            foreach ($disp['items'] as $itemData) {
                $invItem = InventoryItem::where('sku', $itemData['sku'])->first();
                if (!$invItem) continue;

                $lineTotal = $invItem->selling_price * $itemData['qty'];
                $lineCogs  = $invItem->weighted_avg_cost * $itemData['qty'];
                $totalAmount += $lineTotal;
                $totalCogs   += $lineCogs;

                $invoiceItems[] = [
                    'inventory_item_id' => $invItem->id,
                    'description'       => $invItem->name,
                    'quantity'          => $itemData['qty'],
                    'unit_price'        => $invItem->selling_price,
                    'cost_price'        => $invItem->weighted_avg_cost,
                    'line_total'        => $lineTotal,
                    'line_cogs'         => $lineCogs,
                ];
            }

            if (empty($invoiceItems)) continue;

            // Create pharmacy invoice
            $invoice = Invoice::firstOrCreate(
                ['patient_id' => $patient->id, 'department' => 'pharmacy', 'prescribing_doctor_id' => $disp['doctor']->id, 'created_at' => now()->subDays($disp['days_ago'])],
                [
                    'patient_id'            => $patient->id,
                    'patient_type'          => 'walk_in',
                    'department'            => 'pharmacy',
                    'service_name'          => 'Prescription Dispensing',
                    'total_amount'          => $totalAmount,
                    'net_amount'            => $totalAmount,
                    'status'                => Invoice::STATUS_PENDING,
                    'has_prescribed_items'  => true,
                    'prescribing_doctor_id' => $disp['doctor']->id,
                    'performed_by_user_id'  => $this->pharmacist->id,
                    'created_by_user_id'    => $this->pharmacist->id,
                    'service_catalog_id'    => $dispensingService?->id,
                    'created_at'            => now()->subDays($disp['days_ago']),
                ]
            );

            // Add invoice items
            if ($invoice->items()->count() === 0) {
                foreach ($invoiceItems as $ii) {
                    InvoiceItem::create(array_merge($ii, ['invoice_id' => $invoice->id]));
                }
            }

            // Record stock outbound for each item
            foreach ($disp['items'] as $itemData) {
                $invItem = InventoryItem::where('sku', $itemData['sku'])->first();
                if (!$invItem) continue;

                // Check if already dispensed for this invoice
                $alreadyDispensed = StockMovement::where('inventory_item_id', $invItem->id)
                    ->where('reference_type', 'invoice')
                    ->where('reference_id', $invoice->id)
                    ->exists();

                if (!$alreadyDispensed) {
                    try {
                        $this->inventoryService->recordOutbound(
                            $invItem,
                            $itemData['qty'],
                            'invoice',
                            $invoice->id,
                            $this->pharmacist
                        );
                    } catch (\Exception $e) {
                        $this->command->warn("  Stock outbound failed for {$invItem->name}: {$e->getMessage()}");
                    }
                }
            }

            // Mark paid (triggers distribution with COGS)
            if ($invoice->status !== Invoice::STATUS_PAID) {
                // Pharmacy invoices go: pending → completed → paid
                try {
                    $invoice->update(['status' => Invoice::STATUS_IN_PROGRESS]);
                    $invoice->update(['status' => Invoice::STATUS_COMPLETED]);
                    $invoice->refresh();
                    $invoice->markPaid('cash', $this->pharmacist->id);
                } catch (\Exception $e) {
                    $this->command->warn("  Pharmacy invoice #{$invoice->id} payment failed: {$e->getMessage()}");
                }
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 10: Discount Requests
    // ═══════════════════════════════════════════════════════════════════

    private function seedDiscountRequests(): void
    {
        // Create two new invoices specifically for discount workflow
        $patient1 = Patient::where('first_name', 'Aqsa')->first();
        $patient2 = Patient::where('first_name', 'Tariq')->first();
        $patient3 = Patient::where('first_name', 'Bilal')->first();
        $genConsult = ServiceCatalog::where('code', 'CON-GEN')->first();

        if (!$patient1 || !$genConsult) return;

        // 1. Pending discount request
        $inv1 = Invoice::firstOrCreate(
            ['patient_id' => $patient1->id, 'department' => 'consultation', 'service_catalog_id' => $genConsult->id],
            [
                'patient_id'            => $patient1->id,
                'patient_type'          => 'walk_in',
                'department'            => 'consultation',
                'service_name'          => $genConsult->name,
                'service_catalog_id'    => $genConsult->id,
                'total_amount'          => 1500,
                'net_amount'            => 1500,
                'status'                => Invoice::STATUS_PENDING,
                'prescribing_doctor_id' => $this->drAhmed->id,
                'performed_by_user_id'  => $this->drAhmed->id,
                'created_by_user_id'    => $this->receptionist->id,
            ]
        );
        if (($inv1->discount_status ?? Invoice::DISCOUNT_NONE) === Invoice::DISCOUNT_NONE) {
            try {
                $inv1->requestDiscount(300, $this->receptionist->id, 'Low-income patient, regular visitor');
            } catch (\Exception $e) {
                $this->command->warn("  Discount request failed: {$e->getMessage()}");
            }
        }

        // 2. Approved discount
        if ($patient2) {
            $inv2 = Invoice::firstOrCreate(
                ['patient_id' => $patient2->id, 'department' => 'consultation', 'service_catalog_id' => $genConsult->id],
                [
                    'patient_id'            => $patient2->id,
                    'patient_type'          => 'walk_in',
                    'department'            => 'consultation',
                    'service_name'          => $genConsult->name,
                    'service_catalog_id'    => $genConsult->id,
                    'total_amount'          => 1500,
                    'net_amount'            => 1500,
                    'status'                => Invoice::STATUS_PENDING,
                    'prescribing_doctor_id' => $this->drSidra->id,
                    'performed_by_user_id'  => $this->drSidra->id,
                    'created_by_user_id'    => $this->receptionist->id,
                ]
            );
            if (($inv2->discount_status ?? Invoice::DISCOUNT_NONE) === Invoice::DISCOUNT_NONE) {
                try {
                    $inv2->requestDiscount(500, $this->receptionist->id, 'Elderly patient on fixed income');
                    $inv2->refresh();
                    $inv2->approveDiscount($this->owner->id);
                } catch (\Exception $e) {
                    $this->command->warn("  Discount approve failed: {$e->getMessage()}");
                }
            }
        }

        // 3. Rejected discount
        if ($patient3) {
            $labCBC = ServiceCatalog::where('code', 'LAB-CBC')->first();
            $inv3 = Invoice::where('patient_id', $patient3->id)->where('department', 'lab')->first();
            if (!$inv3) {
                $inv3 = Invoice::create([
                    'patient_id'            => $patient3->id,
                    'patient_type'          => 'walk_in',
                    'department'            => 'lab',
                    'service_name'          => $labCBC->name,
                    'service_catalog_id'    => $labCBC->id,
                    'total_amount'          => 800,
                    'net_amount'            => 800,
                    'status'                => Invoice::STATUS_PENDING,
                    'prescribing_doctor_id' => $this->drFatima->id,
                    'created_by_user_id'    => $this->receptionist->id,
                ]);
            }
            if (($inv3->discount_status ?? Invoice::DISCOUNT_NONE) === Invoice::DISCOUNT_NONE) {
                try {
                    $inv3->requestDiscount(200, $this->receptionist->id, 'Patient requested discount');
                    $inv3->refresh();
                    $inv3->rejectDiscount($this->owner->id, 'Standard pricing applies, no discount criteria met');
                } catch (\Exception $e) {
                    $this->command->warn("  Discount reject failed: {$e->getMessage()}");
                }
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 11: Expenses
    // ═══════════════════════════════════════════════════════════════════

    private function seedExpenses(): void
    {
        $expenses = [
            ['department' => 'general',    'category' => 'Rent',           'description' => 'Monthly clinic rent — June 2025',          'cost' => 50000, 'days_ago' => 15],
            ['department' => 'general',    'category' => 'Utilities',      'description' => 'Electricity bill — June 2025',             'cost' => 12000, 'days_ago' => 10],
            ['department' => 'general',    'category' => 'Utilities',      'description' => 'Water & gas bill — June 2025',             'cost' => 3500,  'days_ago' => 10],
            ['department' => 'general',    'category' => 'Internet',       'description' => 'Monthly internet (PTCL) + backup 4G',      'cost' => 4500,  'days_ago' => 8],
            ['department' => 'general',    'category' => 'Cleaning',       'description' => 'Janitorial service — monthly contract',    'cost' => 8000,  'days_ago' => 5],
            ['department' => 'general',    'category' => 'Stationery',     'description' => 'Printer paper, ink, receipt rolls',         'cost' => 2500,  'days_ago' => 7],
            ['department' => 'pharmacy',   'category' => 'Maintenance',    'description' => 'AC repair in pharmacy storage area',        'cost' => 5000,  'days_ago' => 12],
            ['department' => 'laboratory', 'category' => 'Equipment',      'description' => 'Centrifuge calibration & service',          'cost' => 8000,  'days_ago' => 6],
            ['department' => 'laboratory', 'category' => 'Consumables',    'description' => 'Microscope oil, staining reagents restock', 'cost' => 3000,  'days_ago' => 3],
            ['department' => 'radiology',  'category' => 'Maintenance',    'description' => 'X-ray machine annual maintenance',          'cost' => 15000, 'days_ago' => 20],
            ['department' => 'general',    'category' => 'Security',       'description' => 'Security guard salary — June 2025',         'cost' => 20000, 'days_ago' => 1],
            ['department' => 'general',    'category' => 'Tea/Kitchen',    'description' => 'Kitchen supplies, tea, sugar, milk',         'cost' => 3000,  'days_ago' => 2],
        ];

        foreach ($expenses as $exp) {
            $daysAgo = $exp['days_ago'];
            unset($exp['days_ago']);

            Expense::firstOrCreate(
                ['description' => $exp['description']],
                array_merge($exp, [
                    'created_by' => $this->owner->id,
                    'created_at' => now()->subDays($daysAgo),
                ])
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 12: Procurement Requests (Pharmacy)
    // ═══════════════════════════════════════════════════════════════════

    private function seedProcurementRequests(): void
    {
        // 1. Pending procurement — pharmacist needs more ORS and cough syrup
        $pending = ProcurementRequest::firstOrCreate(
            ['department' => 'pharmacy', 'notes' => 'Running low on ORS sachets and cough syrup for monsoon season'],
            [
                'department'   => 'pharmacy',
                'type'         => ProcurementRequest::TYPE_INVENTORY,
                'requested_by' => $this->pharmacist->id,
                'status'       => 'pending',
                'notes'        => 'Running low on ORS sachets and cough syrup for monsoon season',
            ]
        );

        $ors     = InventoryItem::where('sku', 'PH-ORS001')->first();
        $cghSyr  = InventoryItem::where('sku', 'PH-CGH001')->first();
        if ($ors && $pending->items()->count() === 0) {
            ProcurementRequestItem::create(['procurement_request_id' => $pending->id, 'inventory_item_id' => $ors->id, 'quantity_requested' => 200, 'quoted_unit_price' => 8]);
        }
        if ($cghSyr && $pending->items()->count() <= 1) {
            ProcurementRequestItem::create(['procurement_request_id' => $pending->id, 'inventory_item_id' => $cghSyr->id, 'quantity_requested' => 50, 'quoted_unit_price' => 80]);
        }

        // 2. Approved procurement — waiting for delivery
        $approved = ProcurementRequest::firstOrCreate(
            ['department' => 'pharmacy', 'notes' => 'Monthly restock of common antibiotics approved'],
            [
                'department'   => 'pharmacy',
                'type'         => ProcurementRequest::TYPE_INVENTORY,
                'requested_by' => $this->pharmacist->id,
                'approved_by'  => $this->owner->id,
                'status'       => 'approved',
                'notes'        => 'Monthly restock of common antibiotics approved',
            ]
        );

        $amx = InventoryItem::where('sku', 'PH-AMX500')->first();
        $cip = InventoryItem::where('sku', 'PH-CIP500')->first();
        if ($amx && $approved->items()->count() === 0) {
            ProcurementRequestItem::create(['procurement_request_id' => $approved->id, 'inventory_item_id' => $amx->id, 'quantity_requested' => 500, 'quoted_unit_price' => 8]);
        }
        if ($cip && $approved->items()->count() <= 1) {
            ProcurementRequestItem::create(['procurement_request_id' => $approved->id, 'inventory_item_id' => $cip->id, 'quantity_requested' => 300, 'quoted_unit_price' => 10]);
        }

        // 3. Received procurement — already fulfilled + stock updated
        $received = ProcurementRequest::firstOrCreate(
            ['department' => 'pharmacy', 'notes' => 'Emergency restock of paracetamol and omeprazole — delivered'],
            [
                'department'   => 'pharmacy',
                'type'         => ProcurementRequest::TYPE_INVENTORY,
                'requested_by' => $this->pharmacist->id,
                'approved_by'  => $this->owner->id,
                'status'       => 'received',
                'received_at'  => now()->subDays(5),
                'notes'        => 'Emergency restock of paracetamol and omeprazole — delivered',
            ]
        );

        $pcm  = InventoryItem::where('sku', 'PH-PCM500')->first();
        $omp  = InventoryItem::where('sku', 'PH-OMP020')->first();
        if ($pcm && $received->items()->count() === 0) {
            ProcurementRequestItem::create([
                'procurement_request_id' => $received->id,
                'inventory_item_id'      => $pcm->id,
                'quantity_requested'     => 1000,
                'quoted_unit_price'      => 2,
                'quantity_invoiced'      => 1000,
                'unit_price_invoiced'    => 2,
                'quantity_received'      => 1000,
                'unit_price'             => 2,
            ]);

            // Record the inbound stock for this fulfilled procurement
            $existing = StockMovement::where('reference_type', 'procurement_request')
                ->where('reference_id', $received->id)
                ->where('inventory_item_id', $pcm->id)
                ->exists();
            if (!$existing) {
                $this->inventoryService->recordInbound($pcm, 1000, 2.00, 'procurement_request', $received->id, $this->owner);
            }
        }
        if ($omp && $received->items()->count() <= 1) {
            ProcurementRequestItem::create([
                'procurement_request_id' => $received->id,
                'inventory_item_id'      => $omp->id,
                'quantity_requested'     => 500,
                'quoted_unit_price'      => 6,
                'quantity_invoiced'      => 500,
                'unit_price_invoiced'    => 6,
                'quantity_received'      => 500,
                'unit_price'             => 6,
            ]);

            $existing = StockMovement::where('reference_type', 'procurement_request')
                ->where('reference_id', $received->id)
                ->where('inventory_item_id', $omp->id)
                ->exists();
            if (!$existing) {
                $this->inventoryService->recordInbound($omp, 500, 6.00, 'procurement_request', $received->id, $this->owner);
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 13: Doctor Payouts
    // ═══════════════════════════════════════════════════════════════════

    private function seedPayouts(): void
    {
        // Calculate actual earned commissions from RevenueLedger
        $doctors = User::role('Doctor')->get();

        foreach ($doctors as $doctor) {
            $unpaid = RevenueLedger::where('user_id', $doctor->id)
                ->where('category', 'commission')
                ->where('payout_status', '!=', 'paid')
                ->orWhere(function ($q) use ($doctor) {
                    $q->where('user_id', $doctor->id)
                      ->where('category', 'commission')
                      ->whereNull('payout_status');
                })
                ->sum('amount');

            if ($unpaid <= 0) continue;

            // Create a pending payout for the current period
            DoctorPayout::firstOrCreate(
                ['doctor_id' => $doctor->id, 'period_start' => now()->startOfMonth()->toDateString()],
                [
                    'doctor_id'       => $doctor->id,
                    'period_start'    => now()->startOfMonth()->toDateString(),
                    'period_end'      => now()->endOfMonth()->toDateString(),
                    'total_amount'    => round($unpaid, 2),
                    'paid_amount'     => 0,
                    'salary_amount'   => $doctor->base_salary ?? 0,
                    'status'          => 'pending',
                    'payout_type'     => DoctorPayout::TYPE_COMMISSION,
                    'approval_status' => DoctorPayout::APPROVAL_PENDING,
                    'created_by'      => $this->owner->id,
                    'notes'           => "Auto-generated payout for {$doctor->name} — " . now()->format('F Y'),
                ]
            );
        }

        // Also create payouts for non-doctor commission earners (lab tech, pharmacist)
        foreach ([$this->labTech, $this->pharmacist] as $staff) {
            $unpaid = RevenueLedger::where('user_id', $staff->id)
                ->where('category', 'commission')
                ->where(function ($q) {
                    $q->where('payout_status', '!=', 'paid')
                      ->orWhereNull('payout_status');
                })
                ->sum('amount');

            if ($unpaid <= 0) continue;

            DoctorPayout::firstOrCreate(
                ['doctor_id' => $staff->id, 'period_start' => now()->startOfMonth()->toDateString()],
                [
                    'doctor_id'       => $staff->id,
                    'period_start'    => now()->startOfMonth()->toDateString(),
                    'period_end'      => now()->endOfMonth()->toDateString(),
                    'total_amount'    => round($unpaid, 2),
                    'paid_amount'     => 0,
                    'salary_amount'   => $staff->base_salary ?? 0,
                    'status'          => 'pending',
                    'payout_type'     => DoctorPayout::TYPE_COMMISSION,
                    'approval_status' => DoctorPayout::APPROVAL_PENDING,
                    'created_by'      => $this->owner->id,
                    'notes'           => "Commission payout for {$staff->name} — " . now()->format('F Y'),
                ]
            );
        }

        // Approve Dr. Ahmed's payout (show at least one approved)
        $ahmedPayout = DoctorPayout::where('doctor_id', $this->drAhmed->id)
            ->where('approval_status', DoctorPayout::APPROVAL_PENDING)
            ->first();
        if ($ahmedPayout) {
            $ahmedPayout->update([
                'approval_status' => DoctorPayout::APPROVAL_APPROVED,
                'approved_by'     => $this->owner->id,
                'approved_at'     => now(),
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // Summary
    // ═══════════════════════════════════════════════════════════════════

    private function printSummary(): void
    {
        $this->command->newLine();
        $this->command->info('╔════════════════════════════════════════╗');
        $this->command->info('║       WORKFLOW SEEDER SUMMARY          ║');
        $this->command->info('╠════════════════════════════════════════╣');
        $this->command->info('║ Patients:      ' . str_pad(Patient::count(), 20) . '  ║');
        $this->command->info('║ Appointments:  ' . str_pad(Appointment::count(), 20) . '  ║');
        $this->command->info('║ Triage Vitals: ' . str_pad(TriageVital::count(), 20) . '  ║');
        $this->command->info('║ Visits:        ' . str_pad(Visit::count(), 20) . '  ║');
        $this->command->info('║ Prescriptions: ' . str_pad(Prescription::count(), 20) . '  ║');
        $this->command->info('║ Invoices:      ' . str_pad(Invoice::count(), 20) . '  ║');
        $this->command->info('║   - Paid:      ' . str_pad(Invoice::where('status', 'paid')->count(), 20) . '  ║');
        $this->command->info('║   - Pending:   ' . str_pad(Invoice::where('status', 'pending')->count(), 20) . '  ║');
        $this->command->info('║ Inventory:     ' . str_pad(InventoryItem::count(), 20) . '  ║');
        $this->command->info('║ Stock Moves:   ' . str_pad(StockMovement::count(), 20) . '  ║');
        $this->command->info('║ Procurements:  ' . str_pad(ProcurementRequest::count(), 20) . '  ║');
        $this->command->info('║ Expenses:      ' . str_pad(Expense::count(), 20) . '  ║');
        $this->command->info('║ Ledger Entries:' . str_pad(RevenueLedger::count(), 20) . '  ║');
        $this->command->info('║ Payouts:       ' . str_pad(DoctorPayout::count(), 20) . '  ║');
        $this->command->info('╚════════════════════════════════════════╝');

        // Financial summary
        $totalRevenue = Invoice::where('status', 'paid')->sum('net_amount');
        $totalExpenses = Expense::sum('cost');
        $this->command->newLine();
        $this->command->info("Total Revenue (paid invoices): PKR " . number_format($totalRevenue, 2));
        $this->command->info("Total Expenses:                PKR " . number_format($totalExpenses, 2));
        $this->command->info("Net:                           PKR " . number_format($totalRevenue - $totalExpenses, 2));
    }
}
