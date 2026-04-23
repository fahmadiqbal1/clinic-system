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
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Enrichment seeder — adds missing coverage areas to the existing workflow data:
 *   - Patients for Dr. Imran & Dr. Ayesha (they currently have none)
 *   - Lab procurement requests for test kit top-up
 *   - More expenses (monthly operational costs)
 *   - Radiologist payout (missing)
 *   - Confirmed/paid payouts (to show the full lifecycle)
 *   - Card/transfer payments (diversity in payment methods)
 *   - Walk-in vs clinic patient types
 *   - More consultations with different fee types
 *
 * Usage: php artisan db:seed --class=WorkflowEnrichmentSeeder
 */
class WorkflowEnrichmentSeeder extends Seeder
{
    private User $owner;
    private User $drAhmed;
    private User $drFatima;
    private User $drImran;
    private User $drAyesha;
    private User $receptionist;
    private User $triageNurse;
    private User $labTech;
    private User $radiologist;
    private User $pharmacist;
    private InventoryService $inventoryService;

    public function run(): void
    {
        $this->command->info('=== WorkflowEnrichmentSeeder: Filling gaps in workflow data ===');

        $this->owner = User::role('Owner')->first();
        $this->drAhmed = User::where('email', 'doctor@clinic.com')->first();
        $this->drFatima = User::where('email', 'doctor2@clinic.com')->first();
        $this->drImran = User::where('email', 'dr.imran@clinic.com')->first();
        $this->drAyesha = User::where('email', 'dr.ayesha@clinic.com')->first();
        $this->receptionist = User::role('Receptionist')->first();
        $this->triageNurse = User::role('Triage')->first();
        $this->labTech = User::role('Laboratory')->first();
        $this->radiologist = User::role('Radiology')->first();
        $this->pharmacist = User::role('Pharmacy')->first();
        $this->inventoryService = app(InventoryService::class);

        if (!$this->drImran || !$this->drAyesha) {
            $this->command->error('Dr. Imran or Dr. Ayesha not found. Run WorkflowSeeder first.');
            return;
        }

        // Phase 1: New patients for Dr. Imran & Dr. Ayesha
        $patients = $this->seedNewPatients();
        $this->command->info('Phase 1: ' . count($patients) . ' new patients registered');

        // Phase 2: Triage for new patients
        $this->seedNewTriageVitals($patients);
        $this->command->info('Phase 2: Triage vitals recorded');

        // Phase 3: Visits & consultations for new patients
        $visits = $this->seedNewVisitsAndConsultations($patients);
        $this->command->info('Phase 3: Visits & consultations created');

        // Phase 4: Prescriptions for new patients
        $this->seedNewPrescriptions($patients, $visits);
        $this->command->info('Phase 4: Prescriptions written');

        // Phase 5: Lab requests for new patients
        $this->seedNewLabRequests($patients);
        $this->command->info('Phase 5: Lab requests created');

        // Phase 6: Radiology for new patients
        $this->seedNewRadiologyRequests($patients);
        $this->command->info('Phase 6: Radiology requests created');

        // Phase 7: Pharmacy dispensing for new patients
        $this->seedNewPharmacyDispensing($patients);
        $this->command->info('Phase 7: Pharmacy dispensing done');

        // Phase 8: Lab stock procurement (test kit top-up)
        $this->seedLabProcurement();
        $this->command->info('Phase 8: Lab procurement requests created');

        // Phase 9: More expenses
        $this->seedAdditionalExpenses();
        $this->command->info('Phase 9: Additional expenses recorded');

        // Phase 10: Fix up payouts — add radiologist, confirm some
        $this->seedEnrichedPayouts();
        $this->command->info('Phase 10: Payouts enriched');

        // Phase 11: More discounts (on new invoices)
        $this->seedAdditionalDiscounts($patients);
        $this->command->info('Phase 11: Additional discounts created');

        $this->command->info('=== WorkflowEnrichmentSeeder complete ===');
        $this->printSummary();
    }

    // ═══ Phase 1: New Patients (for Dr. Imran & Dr. Ayesha) ═══

    private function seedNewPatients(): array
    {
        $data = [
            // Dr. Imran's patients — full workflow
            ['first_name' => 'Kamran',  'last_name' => 'Nawaz',   'gender' => 'male',   'dob' => '1982-05-12', 'phone' => '03151234567', 'cnic' => '3520198205121', 'doctor' => $this->drImran, 'status' => 'checked_out'],
            ['first_name' => 'Nadia',   'last_name' => 'Butt',    'gender' => 'female', 'dob' => '1991-08-03', 'phone' => '03161234568', 'cnic' => '3520199108032', 'doctor' => $this->drImran, 'status' => 'checked_out'],
            ['first_name' => 'Hassan',  'last_name' => 'Raza',    'gender' => 'male',   'dob' => '1974-03-20', 'phone' => '03171234569', 'cnic' => '3520197403203', 'doctor' => $this->drImran, 'status' => 'with_doctor'],

            // Dr. Ayesha's patients
            ['first_name' => 'Mariam',  'last_name' => 'Akram',   'gender' => 'female', 'dob' => '1998-12-15', 'phone' => '03181234570', 'cnic' => '3520199812154', 'doctor' => $this->drAyesha, 'status' => 'checked_out'],
            ['first_name' => 'Waqar',   'last_name' => 'Siddiqui','gender' => 'male',   'dob' => '1960-07-28', 'phone' => '03191234571', 'cnic' => '3520196007285', 'doctor' => $this->drAyesha, 'status' => 'checked_out'],
            ['first_name' => 'Saima',   'last_name' => 'Khalid',  'gender' => 'female', 'dob' => '2002-01-09', 'phone' => '03201234572', 'cnic' => '3520200201096', 'doctor' => $this->drAyesha, 'status' => 'in_triage'],

            // Cross-doctor: walk-in, no appointment
            ['first_name' => 'Adeel',   'last_name' => 'Farooq',  'gender' => 'male',   'dob' => '1987-11-22', 'phone' => '03211234573', 'cnic' => '3520198711227', 'doctor' => $this->drAhmed, 'status' => 'registered'],
            ['first_name' => 'Rubina',  'last_name' => 'Shaheen', 'gender' => 'female', 'dob' => '1955-04-06', 'phone' => '03221234574', 'cnic' => '3520195504068', 'doctor' => $this->drFatima, 'status' => 'checked_out'],
        ];

        $patients = [];
        foreach ($data as $d) {
            $status = $d['status'];
            $doctor = $d['doctor'];
            $p = Patient::firstOrCreate(
                ['cnic' => $d['cnic']],
                [
                    'first_name'        => $d['first_name'],
                    'last_name'         => $d['last_name'],
                    'phone'             => $d['phone'],
                    'cnic'              => $d['cnic'],
                    'gender'            => $d['gender'],
                    'date_of_birth'     => $d['dob'],
                    'doctor_id'         => $doctor->id,
                    'registration_type' => 'walk_in',
                    'status'            => $status,
                    'registered_at'     => now(),
                ]
            );
            $p->update(['status' => $status]);
            $patients[$d['first_name']] = $p;
        }
        return $patients;
    }

    // ═══ Phase 2: Triage Vitals ═══

    private function seedNewTriageVitals(array $patients): void
    {
        $triageData = [
            ['patient' => 'Kamran',  'bp' => '130/85',  'temp' => 37.1, 'pulse' => 78,  'rr' => 16, 'wt' => 82.0,  'ht' => 172.0, 'spo2' => 97.0, 'complaint' => 'Joint pain in knees for the past month, worse in morning',         'priority' => 'normal', 'days_ago' => 2],
            ['patient' => 'Nadia',   'bp' => '105/68',  'temp' => 37.6, 'pulse' => 84,  'rr' => 17, 'wt' => 58.0,  'ht' => 160.0, 'spo2' => 98.0, 'complaint' => 'Recurring urinary tract infection, burning sensation',              'priority' => 'normal', 'days_ago' => 1],
            ['patient' => 'Hassan',  'bp' => '155/100', 'temp' => 36.9, 'pulse' => 90,  'rr' => 19, 'wt' => 95.0,  'ht' => 176.0, 'spo2' => 95.0, 'complaint' => 'Diabetic follow-up, numbness in feet, blurred vision',              'priority' => 'urgent', 'days_ago' => 0],
            ['patient' => 'Mariam',  'bp' => '110/72',  'temp' => 38.0, 'pulse' => 86,  'rr' => 18, 'wt' => 54.0,  'ht' => 157.0, 'spo2' => 98.0, 'complaint' => 'Skin rash on arms and back for 1 week, itching intensified',        'priority' => 'normal', 'days_ago' => 3],
            ['patient' => 'Waqar',   'bp' => '170/105', 'temp' => 36.7, 'pulse' => 82,  'rr' => 17, 'wt' => 78.0,  'ht' => 169.0, 'spo2' => 96.0, 'complaint' => 'Chronic cough with blood-tinged sputum, weight loss',                'priority' => 'urgent', 'days_ago' => 2],
            ['patient' => 'Saima',   'bp' => '100/65',  'temp' => 37.3, 'pulse' => 76,  'rr' => 15, 'wt' => 50.0,  'ht' => 163.0, 'spo2' => 99.0, 'complaint' => 'Frequent headaches and fatigue, irregular periods',                   'priority' => 'normal', 'days_ago' => 0],
            ['patient' => 'Rubina',  'bp' => '140/90',  'temp' => 36.8, 'pulse' => 74,  'rr' => 16, 'wt' => 65.0,  'ht' => 155.0, 'spo2' => 97.0, 'complaint' => 'Knee replacement follow-up, post-surgery pain management',           'priority' => 'normal', 'days_ago' => 1],
        ];

        foreach ($triageData as $td) {
            $patient = $patients[$td['patient']] ?? null;
            if (!$patient) continue;

            TriageVital::firstOrCreate(
                ['patient_id' => $patient->id, 'blood_pressure' => $td['bp']],
                [
                    'patient_id'        => $patient->id,
                    'blood_pressure'    => $td['bp'],
                    'temperature'       => $td['temp'],
                    'pulse_rate'        => $td['pulse'],
                    'respiratory_rate'  => $td['rr'],
                    'weight'            => $td['wt'],
                    'height'            => $td['ht'],
                    'oxygen_saturation' => $td['spo2'],
                    'chief_complaint'   => $td['complaint'],
                    'priority'          => $td['priority'],
                    'recorded_by'       => $this->triageNurse->id,
                    'created_at'        => now()->subDays($td['days_ago']),
                ]
            );
        }
    }

    // ═══ Phase 3: Visits & Consultations ═══

    private function seedNewVisitsAndConsultations(array $patients): array
    {
        $general  = ServiceCatalog::where('code', 'CON-GEN')->first();
        $followUp = ServiceCatalog::where('code', 'CON-FUP')->first();
        $special  = ServiceCatalog::where('code', 'CON-SPL')->first();

        $visitData = [
            // Dr. Imran completed visits
            ['patient' => 'Kamran', 'doctor' => $this->drImran, 'service' => $general, 'status' => 'completed', 'days_ago' => 2,
             'notes' => 'Patient presents with bilateral knee pain, worse in mornings. Crepitus on examination. X-ray knees AP/lateral and blood urea ordered. Started on anti-inflammatory protocol. Advised weight management.',
             'payment' => 'card'],
            ['patient' => 'Nadia', 'doctor' => $this->drImran, 'service' => $general, 'status' => 'completed', 'days_ago' => 1,
             'notes' => 'Recurring UTI — third episode in 6 months. Urine culture and sensitivity ordered. Started empirical antibiotics. Advised increased fluid intake and cranberry supplements.',
             'payment' => 'cash'],

            // Dr. Imran — still with doctor
            ['patient' => 'Hassan', 'doctor' => $this->drImran, 'service' => $special, 'status' => 'with_doctor', 'days_ago' => 0,
             'notes' => null, 'payment' => null],

            // Dr. Ayesha completed visits
            ['patient' => 'Mariam', 'doctor' => $this->drAyesha, 'service' => $general, 'status' => 'completed', 'days_ago' => 3,
             'notes' => 'Widespread erythematous maculopapular rash on arms and trunk. No mucosal involvement. Suspect allergic dermatitis. CBC, ESR ordered. Started antihistamines and topical corticosteroid.',
             'payment' => 'transfer'],
            ['patient' => 'Waqar', 'doctor' => $this->drAyesha, 'service' => $special, 'status' => 'completed', 'days_ago' => 2,
             'notes' => 'Elderly male with hemoptysis and significant weight loss. Chest X-ray and sputum AFB ordered urgently. Possible differential: TB, malignancy. Referred for CT scan if X-ray abnormal.',
             'payment' => 'cash'],

            // Dr. Fatima — another completed (Rubina)
            ['patient' => 'Rubina', 'doctor' => $this->drFatima, 'service' => $followUp, 'status' => 'completed', 'days_ago' => 1,
             'notes' => 'Post knee replacement follow-up. Wound healing well. ROM improving. Continue physiotherapy. Adjust pain medications — reduce tramadol, increase paracetamol. Follow-up in 2 weeks.',
             'payment' => 'card'],
        ];

        $visits = [];
        foreach ($visitData as $vd) {
            $patient = $patients[$vd['patient']] ?? null;
            if (!$patient) continue;

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

            // Create and pay consultation invoice for completed visits
            if ($vd['status'] === 'completed' && $vd['service']) {
                $invoice = Invoice::firstOrCreate(
                    ['visit_id' => $visit->id, 'department' => 'consultation'],
                    [
                        'patient_id'            => $patient->id,
                        'patient_type'          => 'walk_in',
                        'department'            => 'consultation',
                        'service_name'          => $vd['service']->name,
                        'service_catalog_id'    => $vd['service']->id,
                        'total_amount'          => $vd['service']->price,
                        'net_amount'            => $vd['service']->price,
                        'status'                => Invoice::STATUS_PENDING,
                        'prescribing_doctor_id' => $vd['doctor']->id,
                        'performed_by_user_id'  => $vd['doctor']->id,
                        'created_by_user_id'    => $this->receptionist->id,
                        'visit_id'              => $visit->id,
                        'created_at'            => now()->subDays($vd['days_ago']),
                    ]
                );

                if (!$visit->consultation_fee_invoice_id) {
                    $visit->update(['consultation_fee_invoice_id' => $invoice->id]);
                }

                if ($invoice->status !== Invoice::STATUS_PAID) {
                    try {
                        $invoice->markPaid($vd['payment'] ?? 'cash', $this->receptionist->id);
                    } catch (\Exception $e) {
                        $this->command->warn("  Consultation invoice #{$invoice->id}: {$e->getMessage()}");
                    }
                }
            }
        }

        return $visits;
    }

    // ═══ Phase 4: Prescriptions ═══

    private function seedNewPrescriptions(array $patients, array $visits): void
    {
        $ibuprofen    = InventoryItem::where('sku', 'PH-IBU400')->first();
        $omeprazole   = InventoryItem::where('sku', 'PH-OMP020')->first();
        $ciprofloxacin = InventoryItem::where('sku', 'PH-CIP500')->first();
        $cetirizine   = InventoryItem::where('sku', 'PH-CTZ010')->first();
        $prednisolone = InventoryItem::where('sku', 'PH-PRD005')->first();
        $metformin    = InventoryItem::where('sku', 'PH-MET500')->first();
        $amlodipine   = InventoryItem::where('sku', 'PH-AML005')->first();
        $paracetamol  = InventoryItem::where('sku', 'PH-PCM500')->first();
        $diclofenac   = InventoryItem::where('sku', 'PH-DCL050')->first();

        if (!$ibuprofen) {
            $this->command->warn('  Pharmacy stock missing. Skipping prescriptions.');
            return;
        }

        $rxData = [
            // Kamran: Joint pain — NSAIDs + gastroprotection
            [
                'patient' => 'Kamran', 'doctor' => $this->drImran, 'status' => 'dispensed', 'days_ago' => 2,
                'diagnosis' => 'Osteoarthritis of bilateral knees',
                'notes' => 'Anti-inflammatory protocol. Take ibuprofen with food. Add omeprazole for GI protection.',
                'items' => [
                    ['item' => $ibuprofen,  'name' => 'Ibuprofen 400mg',  'dosage' => '400mg', 'freq' => 'Three times daily after meals', 'dur' => '14 days', 'qty' => 42],
                    ['item' => $omeprazole, 'name' => 'Omeprazole 20mg',  'dosage' => '20mg',  'freq' => 'Once daily before breakfast',   'dur' => '14 days', 'qty' => 14],
                ],
            ],
            // Nadia: UTI — antibiotics
            [
                'patient' => 'Nadia', 'doctor' => $this->drImran, 'status' => 'dispensed', 'days_ago' => 1,
                'diagnosis' => 'Acute uncomplicated urinary tract infection',
                'notes' => 'Complete full antibiotic course. Increase fluid intake to 3L/day.',
                'items' => [
                    ['item' => $ciprofloxacin, 'name' => 'Ciprofloxacin 500mg', 'dosage' => '500mg', 'freq' => 'Twice daily', 'dur' => '7 days', 'qty' => 14],
                    ['item' => $paracetamol,   'name' => 'Paracetamol 500mg',   'dosage' => '500mg', 'freq' => 'As needed for fever/pain', 'dur' => '7 days', 'qty' => 14],
                ],
            ],
            // Mariam: Allergic dermatitis — antihistamine + steroid
            [
                'patient' => 'Mariam', 'doctor' => $this->drAyesha, 'status' => 'dispensed', 'days_ago' => 3,
                'diagnosis' => 'Allergic dermatitis, widespread',
                'notes' => 'Cetirizine at bedtime. Low-dose prednisolone tapering over 5 days.',
                'items' => [
                    ['item' => $cetirizine,   'name' => 'Cetirizine 10mg',    'dosage' => '10mg', 'freq' => 'Once at bedtime',   'dur' => '14 days', 'qty' => 14],
                    ['item' => $prednisolone, 'name' => 'Prednisolone 5mg',   'dosage' => '20mg', 'freq' => 'Morning, tapering', 'dur' => '5 days',  'qty' => 15],
                ],
            ],
            // Rubina: Post-surgery pain management (through Dr. Fatima)
            [
                'patient' => 'Rubina', 'doctor' => $this->drFatima, 'status' => 'dispensed', 'days_ago' => 1,
                'diagnosis' => 'Post-operative pain, total knee replacement recovery',
                'notes' => 'Transition from tramadol to paracetamol + diclofenac. Continue physiotherapy.',
                'items' => [
                    ['item' => $paracetamol, 'name' => 'Paracetamol 500mg', 'dosage' => '1g',   'freq' => 'Three times daily',        'dur' => '14 days', 'qty' => 42],
                    ['item' => $diclofenac,  'name' => 'Diclofenac 50mg',   'dosage' => '50mg', 'freq' => 'Twice daily after meals',  'dur' => '14 days', 'qty' => 28],
                    ['item' => $omeprazole,  'name' => 'Omeprazole 20mg',   'dosage' => '20mg', 'freq' => 'Once before breakfast',    'dur' => '14 days', 'qty' => 14],
                ],
            ],
            // Hassan: Active prescription (not yet dispensed — patient still with doctor)
            [
                'patient' => 'Hassan', 'doctor' => $this->drImran, 'status' => 'active', 'days_ago' => 0,
                'diagnosis' => 'Type 2 diabetes mellitus with peripheral neuropathy',
                'notes' => 'Increase metformin dose. Add amlodipine for BP. Review HbA1c in 3 months.',
                'items' => [
                    ['item' => $metformin,  'name' => 'Metformin 500mg',  'dosage' => '1g',   'freq' => 'Twice daily with meals', 'dur' => '30 days', 'qty' => 60],
                    ['item' => $amlodipine, 'name' => 'Amlodipine 5mg',  'dosage' => '5mg',  'freq' => 'Once daily morning',     'dur' => '30 days', 'qty' => 30],
                ],
            ],
        ];

        foreach ($rxData as $rd) {
            $patient = $patients[$rd['patient']] ?? null;
            $visit   = $visits[$rd['patient']] ?? null;
            if (!$patient) continue;

            $rx = Prescription::firstOrCreate(
                ['patient_id' => $patient->id, 'doctor_id' => $rd['doctor']->id, 'diagnosis' => $rd['diagnosis']],
                [
                    'patient_id' => $patient->id,
                    'doctor_id'  => $rd['doctor']->id,
                    'visit_id'   => $visit?->id,
                    'diagnosis'  => $rd['diagnosis'],
                    'notes'      => $rd['notes'],
                    'status'     => $rd['status'],
                    'created_at' => now()->subDays($rd['days_ago']),
                ]
            );

            foreach ($rd['items'] as $item) {
                if (!$item['item']) continue;
                PrescriptionItem::firstOrCreate(
                    ['prescription_id' => $rx->id, 'inventory_item_id' => $item['item']->id],
                    [
                        'prescription_id'   => $rx->id,
                        'inventory_item_id' => $item['item']->id,
                        'medication_name'   => $item['name'],
                        'dosage'            => $item['dosage'],
                        'frequency'         => $item['freq'],
                        'duration'          => $item['dur'],
                        'quantity'          => $item['qty'],
                        'instructions'      => "Take {$item['dosage']} {$item['freq']} for {$item['dur']}",
                    ]
                );
            }
        }
    }

    // ═══ Phase 5: Lab Requests ═══

    private function seedNewLabRequests(array $patients): void
    {
        $cbc     = ServiceCatalog::where('code', 'LAB-CBC')->first();
        $urinalysis = ServiceCatalog::where('code', 'LAB-URN')->first();
        $esr     = ServiceCatalog::where('code', 'LAB-ESR')->first();
        $hba1c   = ServiceCatalog::where('code', 'LAB-HBA')->first();
        $rft     = ServiceCatalog::where('code', 'LAB-RFT')->first();
        $glucose = ServiceCatalog::where('code', 'LAB-GLF')->first();

        $labInvoices = [
            // Nadia: Urine culture — PAID + COMPLETED
            [
                'patient' => 'Nadia', 'service' => $urinalysis, 'doctor' => $this->drImran,
                'status' => 'paid_completed', 'days_ago' => 1,
                'report' => "Urine Analysis:\nColor: Yellow, Appearance: Slightly turbid\npH: 6.5, Specific Gravity: 1.020\nWBC: 20-25/HPF (H), RBC: 2-3/HPF\nBacteria: Many\nNitrites: Positive\nLeukocyte Esterase: Positive\nConclusion: Findings consistent with urinary tract infection. Culture sensitivity pending.",
            ],
            // Mariam: CBC + ESR — PAID + COMPLETED
            [
                'patient' => 'Mariam', 'service' => $cbc, 'doctor' => $this->drAyesha,
                'status' => 'paid_completed', 'days_ago' => 3,
                'report' => "WBC: 9.8 ×10³/µL, RBC: 4.5 ×10⁶/µL, Hb: 12.8 g/dL, Platelets: 310 ×10³/µL\nEosinophils: 12% (H) — elevated, suggesting allergic etiology\nConclusion: Eosinophilia consistent with allergic dermatitis diagnosis.",
            ],
            [
                'patient' => 'Mariam', 'service' => $esr, 'doctor' => $this->drAyesha,
                'status' => 'paid_completed', 'days_ago' => 3,
                'report' => "ESR: 28 mm/hr (mildly elevated)\nConclusion: Mild elevation consistent with ongoing inflammatory process.",
            ],
            // Waqar: CBC — PAID, pending results
            [
                'patient' => 'Waqar', 'service' => $cbc, 'doctor' => $this->drAyesha,
                'status' => 'paid_pending', 'days_ago' => 2,
            ],
            // Hassan: HbA1c + RFT + Glucose — some paid, some pending
            [
                'patient' => 'Hassan', 'service' => $hba1c, 'doctor' => $this->drImran,
                'status' => 'pending', 'days_ago' => 0,
            ],
            [
                'patient' => 'Hassan', 'service' => $rft, 'doctor' => $this->drImran,
                'status' => 'pending', 'days_ago' => 0,
            ],
        ];

        foreach ($labInvoices as $ld) {
            $patient = $patients[$ld['patient']] ?? null;
            $service = $ld['service'];
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
                    'prescribing_doctor_id' => $ld['doctor']->id,
                    'created_by_user_id'    => $this->receptionist->id,
                    'created_at'            => now()->subDays($ld['days_ago']),
                ]
            );

            if (in_array($ld['status'], ['paid_completed', 'paid_pending']) && $invoice->status !== Invoice::STATUS_PAID) {
                try {
                    $invoice->markPaid('cash', $this->receptionist->id);
                } catch (\Exception $e) {
                    $this->command->warn("  Lab invoice #{$invoice->id}: {$e->getMessage()}");
                }
            }

            if ($ld['status'] === 'paid_completed') {
                $invoice->refresh();
                if ($invoice->status === Invoice::STATUS_PAID && !$invoice->report_text) {
                    try {
                        $invoice->update([
                            'performed_by_user_id' => $this->labTech->id,
                            'report_text'          => $ld['report'],
                        ]);
                        $invoice->completeAndDistribute();
                    } catch (\Exception $e) {
                        $this->command->warn("  Lab invoice #{$invoice->id} complete: {$e->getMessage()}");
                    }
                }
            }
        }
    }

    // ═══ Phase 6: Radiology Requests ═══

    private function seedNewRadiologyRequests(array $patients): void
    {
        $kneeXray  = ServiceCatalog::where('code', 'RAD-XRA')->first() ?? ServiceCatalog::where('department', 'radiology')->where('name', 'like', '%X-ray%')->first();
        $chestXray = ServiceCatalog::where('code', 'RAD-CXR')->first();
        $ultrasound = ServiceCatalog::where('code', 'RAD-UAB')->first();

        $radData = [
            // Kamran: Knee X-ray — PAID + COMPLETED
            [
                'patient' => 'Kamran', 'service' => $kneeXray ?? $chestXray, 'doctor' => $this->drImran,
                'status' => 'paid_completed', 'days_ago' => 2,
                'report' => "AP and lateral views of bilateral knees.\nJoint space: Moderate narrowing of medial compartment bilaterally.\nOsteophytes: Present at medial tibial plateau and margins of patella.\nSubchondral sclerosis: Mild, bilateral.\nNo fractures, dislocations, or loose bodies.\nImpression: Moderate bilateral knee osteoarthritis (Kellgren-Lawrence Grade III).",
            ],
            // Waqar: Chest X-ray — PAID + COMPLETED (urgent)
            [
                'patient' => 'Waqar', 'service' => $chestXray, 'doctor' => $this->drAyesha,
                'status' => 'paid_completed', 'days_ago' => 2,
                'report' => "PA chest radiograph.\nHeart: Normal cardiothoracic ratio.\nMediastinum: Mildly prominent right hilar lymphadenopathy.\nLungs: 3cm irregular opacity in right upper lobe with spiculated margins. No pleural effusion.\nBony thorax: Intact.\nImpression: Suspicious right upper lobe mass lesion with hilar lymphadenopathy. Recommend CT thorax with contrast for further evaluation. Clinical correlation with sputum for AFB essential.",
            ],
            // Rubina: Knee ultrasound — PAID, pending report
            [
                'patient' => 'Rubina', 'service' => $ultrasound, 'doctor' => $this->drFatima,
                'status' => 'paid_pending', 'days_ago' => 1,
            ],
        ];

        foreach ($radData as $rd) {
            $patient = $patients[$rd['patient']] ?? null;
            $service = $rd['service'];
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
                    'prescribing_doctor_id' => $rd['doctor']->id,
                    'created_by_user_id'    => $this->receptionist->id,
                    'created_at'            => now()->subDays($rd['days_ago']),
                ]
            );

            if (in_array($rd['status'], ['paid_completed', 'paid_pending']) && $invoice->status !== Invoice::STATUS_PAID) {
                try {
                    $invoice->markPaid($rd['days_ago'] % 2 === 0 ? 'card' : 'cash', $this->receptionist->id);
                } catch (\Exception $e) {
                    $this->command->warn("  Rad invoice #{$invoice->id}: {$e->getMessage()}");
                }
            }

            if ($rd['status'] === 'paid_completed') {
                $invoice->refresh();
                if ($invoice->status === Invoice::STATUS_PAID && !$invoice->report_text) {
                    try {
                        $invoice->update([
                            'performed_by_user_id' => $this->radiologist->id,
                            'report_text'          => $rd['report'],
                        ]);
                        $invoice->completeAndDistribute();
                    } catch (\Exception $e) {
                        $this->command->warn("  Rad invoice #{$invoice->id} complete: {$e->getMessage()}");
                    }
                }
            }
        }
    }

    // ═══ Phase 7: Pharmacy Dispensing ═══

    private function seedNewPharmacyDispensing(array $patients): void
    {
        $dispensingService = ServiceCatalog::where('code', 'PHR-DIS')->first();

        $dispenses = [
            ['patient' => 'Kamran', 'doctor' => $this->drImran, 'days_ago' => 2, 'payment' => 'card',
             'items' => [['sku' => 'PH-IBU400', 'qty' => 42], ['sku' => 'PH-OMP020', 'qty' => 14]]],
            ['patient' => 'Nadia', 'doctor' => $this->drImran, 'days_ago' => 1, 'payment' => 'cash',
             'items' => [['sku' => 'PH-CIP500', 'qty' => 14], ['sku' => 'PH-PCM500', 'qty' => 14]]],
            ['patient' => 'Mariam', 'doctor' => $this->drAyesha, 'days_ago' => 3, 'payment' => 'cash',
             'items' => [['sku' => 'PH-CTZ010', 'qty' => 14], ['sku' => 'PH-PRD005', 'qty' => 15]]],
            ['patient' => 'Rubina', 'doctor' => $this->drFatima, 'days_ago' => 1, 'payment' => 'transfer',
             'items' => [['sku' => 'PH-PCM500', 'qty' => 42], ['sku' => 'PH-DCL050', 'qty' => 28], ['sku' => 'PH-OMP020', 'qty' => 14]]],
        ];

        foreach ($dispenses as $disp) {
            $patient = $patients[$disp['patient']] ?? null;
            if (!$patient) continue;

            $totalAmount = 0;
            $totalCogs = 0;
            $invoiceItems = [];

            foreach ($disp['items'] as $itemData) {
                $invItem = InventoryItem::where('sku', $itemData['sku'])->first();
                if (!$invItem) continue;

                $lt = $invItem->selling_price * $itemData['qty'];
                $lc = $invItem->weighted_avg_cost * $itemData['qty'];
                $totalAmount += $lt;
                $totalCogs += $lc;
                $invoiceItems[] = [
                    'inventory_item_id' => $invItem->id,
                    'description'       => $invItem->name,
                    'quantity'          => $itemData['qty'],
                    'unit_price'        => $invItem->selling_price,
                    'cost_price'        => $invItem->weighted_avg_cost,
                    'line_total'        => $lt,
                    'line_cogs'         => $lc,
                ];
            }

            if (empty($invoiceItems)) continue;

            $invoice = Invoice::firstOrCreate(
                ['patient_id' => $patient->id, 'department' => 'pharmacy', 'prescribing_doctor_id' => $disp['doctor']->id],
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

            if ($invoice->items()->count() === 0) {
                foreach ($invoiceItems as $ii) {
                    InvoiceItem::create(array_merge($ii, ['invoice_id' => $invoice->id]));
                }
            }

            // Stock outbound
            foreach ($disp['items'] as $itemData) {
                $invItem = InventoryItem::where('sku', $itemData['sku'])->first();
                if (!$invItem) continue;
                $exists = StockMovement::where('inventory_item_id', $invItem->id)
                    ->where('reference_type', 'invoice')->where('reference_id', $invoice->id)->exists();
                if (!$exists) {
                    try {
                        $this->inventoryService->recordOutbound($invItem, $itemData['qty'], 'invoice', $invoice->id, $this->pharmacist);
                    } catch (\Exception $e) {
                        $this->command->warn("  Stock outbound {$invItem->name}: {$e->getMessage()}");
                    }
                }
            }

            if ($invoice->status !== Invoice::STATUS_PAID) {
                try {
                    $invoice->update(['status' => Invoice::STATUS_IN_PROGRESS]);
                    $invoice->update(['status' => Invoice::STATUS_COMPLETED]);
                    $invoice->refresh();
                    $invoice->markPaid($disp['payment'], $this->pharmacist->id);
                } catch (\Exception $e) {
                    $this->command->warn("  Pharmacy invoice #{$invoice->id}: {$e->getMessage()}");
                }
            }
        }
    }

    // ═══ Phase 8: Lab Procurement (Test Kit Top-up) ═══

    private function seedLabProcurement(): void
    {
        // 1. Pending: Lab tech requests reagent replenishment
        $pending = ProcurementRequest::firstOrCreate(
            ['department' => 'laboratory', 'notes' => 'Urgent: CBC and Urinalysis reagent kits running low'],
            [
                'department'   => 'laboratory',
                'type'         => ProcurementRequest::TYPE_INVENTORY,
                'requested_by' => $this->labTech->id,
                'status'       => 'pending',
                'notes'        => 'Urgent: CBC and Urinalysis reagent kits running low',
            ]
        );

        $cbcKit = InventoryItem::where('department', 'laboratory')->where('name', 'like', '%CBC%')->first();
        $urineKit = InventoryItem::where('department', 'laboratory')->where('name', 'like', '%Urine%')->orWhere('name', 'like', '%Urinalysis%')->where('department', 'laboratory')->first();

        if ($cbcKit && $pending->items()->count() === 0) {
            ProcurementRequestItem::create(['procurement_request_id' => $pending->id, 'inventory_item_id' => $cbcKit->id, 'quantity_requested' => 100, 'quoted_unit_price' => 150]);
        }
        if ($urineKit && $pending->items()->where('inventory_item_id', $urineKit->id)->count() === 0) {
            ProcurementRequestItem::create(['procurement_request_id' => $pending->id, 'inventory_item_id' => $urineKit->id, 'quantity_requested' => 200, 'quoted_unit_price' => 50]);
        }

        // 2. Approved: Lipid test kits approved, awaiting delivery
        $approved = ProcurementRequest::firstOrCreate(
            ['department' => 'laboratory', 'notes' => 'Lipid profile and HbA1c reagent kits for quarterly restock'],
            [
                'department'   => 'laboratory',
                'type'         => ProcurementRequest::TYPE_INVENTORY,
                'requested_by' => $this->labTech->id,
                'approved_by'  => $this->owner->id,
                'status'       => 'approved',
                'notes'        => 'Lipid profile and HbA1c reagent kits for quarterly restock',
            ]
        );

        $lipidKit = InventoryItem::where('department', 'laboratory')->where('name', 'like', '%Lipid%')->first();
        $hba1cKit = InventoryItem::where('department', 'laboratory')->where('name', 'like', '%HbA1c%')->first();

        if ($lipidKit && $approved->items()->count() === 0) {
            ProcurementRequestItem::create(['procurement_request_id' => $approved->id, 'inventory_item_id' => $lipidKit->id, 'quantity_requested' => 150, 'quoted_unit_price' => 200]);
        }
        if ($hba1cKit && $approved->items()->where('inventory_item_id', $hba1cKit?->id)->count() === 0 && $hba1cKit) {
            ProcurementRequestItem::create(['procurement_request_id' => $approved->id, 'inventory_item_id' => $hba1cKit->id, 'quantity_requested' => 100, 'quoted_unit_price' => 250]);
        }

        // 3. Received: Thyroid kits already received and stocked
        $thyroidKit = InventoryItem::where('department', 'laboratory')->where('name', 'like', '%Thyroid%')->first();

        if ($thyroidKit) {
            $received = ProcurementRequest::firstOrCreate(
                ['department' => 'laboratory', 'notes' => 'Thyroid function test kits — emergency restock delivered'],
                [
                    'department'   => 'laboratory',
                    'type'         => ProcurementRequest::TYPE_INVENTORY,
                    'requested_by' => $this->labTech->id,
                    'approved_by'  => $this->owner->id,
                    'status'       => 'received',
                    'received_at'  => now()->subDays(3),
                    'notes'        => 'Thyroid function test kits — emergency restock delivered',
                ]
            );

            if ($received->items()->count() === 0) {
                ProcurementRequestItem::create([
                    'procurement_request_id' => $received->id,
                    'inventory_item_id'      => $thyroidKit->id,
                    'quantity_requested'     => 200,
                    'quoted_unit_price'      => 180,
                    'quantity_invoiced'      => 200,
                    'unit_price_invoiced'    => 175,
                    'quantity_received'      => 200,
                    'unit_price'             => 175,
                ]);

                $exists = StockMovement::where('reference_type', 'procurement_request')
                    ->where('reference_id', $received->id)->where('inventory_item_id', $thyroidKit->id)->exists();
                if (!$exists) {
                    $this->inventoryService->recordInbound($thyroidKit, 200, 175.00, 'procurement_request', $received->id, $this->owner);
                }
            }
        }
    }

    // ═══ Phase 9: Additional Expenses ═══

    private function seedAdditionalExpenses(): void
    {
        $expenses = [
            // Monthly operational expenses
            ['dept' => 'general',      'cat' => 'Rent',         'desc' => 'Monthly clinic rent — July 2025',            'cost' => 50000,  'days_ago' => 0],
            ['dept' => 'general',      'cat' => 'Utilities',    'desc' => 'Electricity bill — July 2025 (AC running)',  'cost' => 18000,  'days_ago' => 1],
            ['dept' => 'general',      'cat' => 'Internet',     'desc' => 'PTCL fiber + 4G backup — July 2025',         'cost' => 4500,   'days_ago' => 2],
            ['dept' => 'general',      'cat' => 'Security',     'desc' => 'Security guard salary — July 2025',          'cost' => 20000,  'days_ago' => 0],
            ['dept' => 'general',      'cat' => 'Cleaning',     'desc' => 'Deep cleaning + sanitization contract',       'cost' => 10000,  'days_ago' => 3],

            // Department-specific
            ['dept' => 'laboratory',   'cat' => 'Consumables',  'desc' => 'Blood collection tubes, syringes, gloves',    'cost' => 5500,   'days_ago' => 4],
            ['dept' => 'laboratory',   'cat' => 'Maintenance',  'desc' => 'Hematology analyzer monthly service',          'cost' => 12000,  'days_ago' => 7],
            ['dept' => 'radiology',    'cat' => 'Consumables',  'desc' => 'X-ray film + developing chemicals',            'cost' => 7000,   'days_ago' => 5],
            ['dept' => 'radiology',    'cat' => 'Equipment',    'desc' => 'Portable ultrasound probe replacement',        'cost' => 25000,  'days_ago' => 8],
            ['dept' => 'pharmacy',     'cat' => 'Equipment',    'desc' => 'Mini fridge for temperature-sensitive drugs',  'cost' => 15000,  'days_ago' => 6],
            ['dept' => 'consultation', 'cat' => 'Equipment',    'desc' => 'Digital stethoscope for Dr. Imran',            'cost' => 8500,   'days_ago' => 9],
            ['dept' => 'general',      'cat' => 'Stationery',   'desc' => 'Thermal receipt paper, folders, labels',       'cost' => 3200,   'days_ago' => 2],
            ['dept' => 'general',      'cat' => 'Tea/Kitchen',  'desc' => 'Monthly kitchen supplies + water dispenser',   'cost' => 4000,   'days_ago' => 1],
            ['dept' => 'general',      'cat' => 'Maintenance',  'desc' => 'Generator diesel + servicing',                 'cost' => 8000,   'days_ago' => 4],
        ];

        foreach ($expenses as $exp) {
            Expense::firstOrCreate(
                ['description' => $exp['desc']],
                [
                    'department'  => $exp['dept'],
                    'category'    => $exp['cat'],
                    'description' => $exp['desc'],
                    'cost'        => $exp['cost'],
                    'created_by'  => $this->owner->id,
                    'created_at'  => now()->subDays($exp['days_ago']),
                ]
            );
        }
    }

    // ═══ Phase 10: Enriched Payouts ═══

    private function seedEnrichedPayouts(): void
    {
        // Add payout for Radiologist Hassan (was missing)
        $radUnpaid = RevenueLedger::where('user_id', $this->radiologist->id)
            ->where('category', 'commission')
            ->where(function ($q) {
                $q->where('payout_status', '!=', 'paid')->orWhereNull('payout_status');
            })->sum('amount');

        if ($radUnpaid > 0) {
            DoctorPayout::firstOrCreate(
                ['doctor_id' => $this->radiologist->id, 'period_start' => now()->startOfMonth()->toDateString()],
                [
                    'doctor_id'       => $this->radiologist->id,
                    'period_start'    => now()->startOfMonth()->toDateString(),
                    'period_end'      => now()->endOfMonth()->toDateString(),
                    'total_amount'    => round($radUnpaid, 2),
                    'paid_amount'     => 0,
                    'salary_amount'   => 0,
                    'status'          => 'pending',
                    'payout_type'     => DoctorPayout::TYPE_COMMISSION,
                    'approval_status' => DoctorPayout::APPROVAL_PENDING,
                    'created_by'      => $this->owner->id,
                    'notes'           => "Commission payout for Radiologist Hassan — " . now()->format('F Y'),
                ]
            );
        }

        // Add payouts for Dr. Imran and Dr. Ayesha (new doctors)
        foreach ([$this->drImran, $this->drAyesha] as $doctor) {
            $unpaid = RevenueLedger::where('user_id', $doctor->id)
                ->where('category', 'commission')
                ->where(function ($q) {
                    $q->where('payout_status', '!=', 'paid')->orWhereNull('payout_status');
                })->sum('amount');

            if ($unpaid <= 0) continue;

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
                    'notes'           => "Commission payout for {$doctor->name} — " . now()->format('F Y'),
                ]
            );
        }

        // Approve Dr. Imran's payout to show lifecycle diversity
        $imranPayout = DoctorPayout::where('doctor_id', $this->drImran->id)
            ->where('approval_status', DoctorPayout::APPROVAL_PENDING)->first();
        if ($imranPayout) {
            $imranPayout->update([
                'approval_status' => DoctorPayout::APPROVAL_APPROVED,
                'approved_by'     => $this->owner->id,
                'approved_at'     => now(),
            ]);
        }

        // Confirm Dr. Ahmed's existing approved payout (mark as confirmed/received)
        $ahmedPayout = DoctorPayout::where('doctor_id', $this->drAhmed->id)
            ->where('approval_status', DoctorPayout::APPROVAL_APPROVED)
            ->where('status', 'pending')
            ->first();
        if ($ahmedPayout) {
            $ahmedPayout->update([
                'status'       => 'confirmed',
                'paid_amount'  => $ahmedPayout->total_amount,
                'confirmed_by' => $this->drAhmed->id,
                'confirmed_at' => now(),
            ]);
        }
    }

    // ═══ Phase 11: Additional Discounts ═══

    private function seedAdditionalDiscounts(array $patients): void
    {
        // Pending discount on Kamran's consultation
        $kamranInv = Invoice::where('patient_id', $patients['Kamran']?->id)
            ->where('department', 'consultation')
            ->where('status', Invoice::STATUS_PAID)
            ->first();

        // We can't discount a paid invoice after distribution — skip paid ones.
        // Instead, create a new pending invoice for discount demo on Hassan (still pending)
        $hassanLabInvoice = Invoice::where('patient_id', $patients['Hassan']?->id ?? 0)
            ->where('department', 'lab')
            ->where('status', Invoice::STATUS_PENDING)
            ->first();

        if ($hassanLabInvoice && ($hassanLabInvoice->discount_status ?? Invoice::DISCOUNT_NONE) === Invoice::DISCOUNT_NONE) {
            try {
                $hassanLabInvoice->requestDiscount(100, $this->receptionist->id, 'Diabetic patient on multiple medications, financial hardship');
            } catch (\Exception $e) {
                $this->command->warn("  Discount on Hassan lab: {$e->getMessage()}");
            }
        }
    }

    // ═══ Summary ═══

    private function printSummary(): void
    {
        $this->command->newLine();
        $this->command->info('╔═══════════════════════════════════════════╗');
        $this->command->info('║    ENRICHMENT SEEDER — FINAL SUMMARY      ║');
        $this->command->info('╠═══════════════════════════════════════════╣');
        $this->command->info('║ Patients:       ' . str_pad(Patient::count(), 20)          . '  ║');
        $this->command->info('║ Triage Vitals:  ' . str_pad(TriageVital::count(), 20)      . '  ║');
        $this->command->info('║ Visits:         ' . str_pad(Visit::count(), 20)             . '  ║');
        $this->command->info('║ Prescriptions:  ' . str_pad(Prescription::count(), 20)      . '  ║');
        $this->command->info('║ Invoices:       ' . str_pad(Invoice::count(), 20)           . '  ║');
        $this->command->info('║   - Paid:       ' . str_pad(Invoice::where('status', 'paid')->count(), 20) . '  ║');
        $this->command->info('║   - Pending:    ' . str_pad(Invoice::where('status', 'pending')->count(), 20) . '  ║');
        $this->command->info('║ Inventory:      ' . str_pad(InventoryItem::count(), 20)     . '  ║');
        $this->command->info('║ Stock Moves:    ' . str_pad(StockMovement::count(), 20)     . '  ║');
        $this->command->info('║ Procurements:   ' . str_pad(ProcurementRequest::count(), 20) . '  ║');
        $this->command->info('║ Expenses:       ' . str_pad(Expense::count(), 20)           . '  ║');
        $this->command->info('║ Ledger Entries: ' . str_pad(RevenueLedger::count(), 20)     . '  ║');
        $this->command->info('║ Payouts:        ' . str_pad(DoctorPayout::count(), 20)      . '  ║');
        $this->command->info('╚═══════════════════════════════════════════╝');

        // Financial health check
        $totalRev = Invoice::where('status', 'paid')->sum('net_amount');
        $totalExp = Expense::sum('cost');
        $credits  = RevenueLedger::where('entry_type', 'credit')->sum('amount');
        $debits   = RevenueLedger::where('entry_type', 'debit')->sum('amount');
        $balance  = round($credits - $debits, 2);

        $this->command->newLine();
        $this->command->info('=== FINANCIAL HEALTH ===');
        $this->command->info("Revenue (paid invoices):  PKR " . number_format($totalRev, 2));
        $this->command->info("Total Expenses:           PKR " . number_format($totalExp, 2));
        $this->command->info("Net Profit:               PKR " . number_format($totalRev - $totalExp, 2));
        $this->command->info("Ledger Balance (Cr - Dr): PKR " . number_format($balance, 2) . ($balance == 0 ? ' ✓ BALANCED' : ' ✗ IMBALANCED'));

        // Payout summary
        $this->command->newLine();
        $this->command->info('=== PAYOUT STATUS ===');
        $payouts = DoctorPayout::with('doctor:id,name')->get();
        foreach ($payouts as $p) {
            $this->command->info(sprintf('  %-22s Status: %-10s Approval: %-10s Amount: PKR %s',
                $p->doctor?->name ?? 'N/A', $p->status, $p->approval_status, number_format($p->total_amount, 2)));
        }

        // Discount summary
        $this->command->newLine();
        $this->command->info('=== DISCOUNT SUMMARY ===');
        $discounts = Invoice::whereNotNull('discount_status')->where('discount_status', '!=', 'none')->get();
        foreach ($discounts as $d) {
            $this->command->info(sprintf('  Invoice #%d (%s): %s — PKR %s', $d->id, $d->department, $d->discount_status, number_format($d->discount_amount, 2)));
        }

        // Stock alerts
        $this->command->newLine();
        $this->command->info('=== LOW STOCK ALERTS ===');
        $lowCount = 0;
        $items = InventoryItem::where('is_active', true)->get();
        foreach ($items as $item) {
            $stock = $item->stockMovements()->sum('quantity');
            if ($stock <= $item->minimum_stock_level) {
                $this->command->warn(sprintf('  ⚠ %-35s Stock: %d (min: %d)', $item->name, $stock, $item->minimum_stock_level));
                $lowCount++;
            }
        }
        if ($lowCount === 0) {
            $this->command->info('  All items above minimum stock levels ✓');
        }
    }
}
