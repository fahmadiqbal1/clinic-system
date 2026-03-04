<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\DoctorPayout;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InventoryItem;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\RevenueLedger;
use App\Models\ServiceCatalog;
use App\Models\TriageVital;
use App\Models\User;
use App\Models\Visit;
use App\Models\ZakatTransaction;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Final Quality Control seeder — validates all financial workflows end-to-end.
 *
 * Wipes old transactional data (keeps users, config, inventory, catalog),
 * creates 10 patients across 2 doctors, walks each through the full
 * clinical workflow, and prints a verification report.
 *
 * Run:  php artisan db:seed --class=QualityControlSeeder
 */
class QualityControlSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('╔══════════════════════════════════════════════════════════╗');
        $this->command->warn('║       QUALITY CONTROL SEEDER — Final System Audit       ║');
        $this->command->warn('╚══════════════════════════════════════════════════════════╝');
        $this->command->newLine();

        // ──────────────────────────────────────────────
        // PHASE 1: Wipe transactional data
        // ──────────────────────────────────────────────
        $this->command->info('Phase 1: Wiping old transactional data…');
        $this->wipeTransactionalData();

        // ──────────────────────────────────────────────
        // PHASE 2: Load system actors & set commission rates
        // ──────────────────────────────────────────────
        $this->command->info('Phase 2: Loading system actors…');
        $doctor1      = User::where('email', 'doctor@clinic.com')->firstOrFail();
        $doctor2      = User::where('email', 'doctor2@clinic.com')->firstOrFail();
        $receptionist = User::where('email', 'receptionist@clinic.com')->firstOrFail();
        $triageNurse  = User::where('email', 'triage@clinic.com')->firstOrFail();
        $labTech      = User::where('email', 'lab@clinic.com')->firstOrFail();
        $radTech      = User::where('email', 'radiology@clinic.com')->firstOrFail();
        $pharmacist   = User::where('email', 'pharmacy@clinic.com')->firstOrFail();
        $owner        = User::where('email', 'owner@clinic.com')->firstOrFail();

        // Set owner-defined commission rates (simulates what owner would do via UI)
        $doctor1->update([
            'compensation_type' => 'commission',
            'commission_consultation' => 60, 'commission_lab' => 5,
            'commission_radiology' => 10, 'commission_pharmacy' => 2,
        ]);
        $doctor2->update([
            'compensation_type' => 'hybrid', 'base_salary' => 5000,
            'commission_consultation' => 70, 'commission_lab' => 10,
            'commission_radiology' => 10, 'commission_pharmacy' => 3,
        ]);
        $labTech->update([
            'compensation_type' => 'hybrid', 'base_salary' => 15000,
            'commission_lab' => 5,
        ]);
        $radTech->update([
            'compensation_type' => 'hybrid', 'base_salary' => 15000,
            'commission_radiology' => 5,
        ]);
        $pharmacist->update([
            'compensation_type' => 'hybrid', 'base_salary' => 15000,
            'commission_pharmacy' => 2,
        ]);

        // Refresh models
        $doctor1->refresh(); $doctor2->refresh(); $labTech->refresh(); $radTech->refresh(); $pharmacist->refresh();

        $this->command->info("  Dr. Ahmed    ({$doctor1->compensation_type}) — consult: {$doctor1->commissionRateFor('consultation')}%");
        $this->command->info("  Dr. Fatima   ({$doctor2->compensation_type}) — consult: {$doctor2->commissionRateFor('consultation')}%");
        $this->command->info("  Lab Tech     ({$labTech->compensation_type}) — lab: {$labTech->commissionRateFor('lab')}%");
        $this->command->info("  Rad Tech     ({$radTech->compensation_type}) — rad: {$radTech->commissionRateFor('radiology')}%");
        $this->command->info("  Pharmacist   ({$pharmacist->compensation_type}) — pharm: {$pharmacist->commissionRateFor('pharmacy')}%");

        // ──────────────────────────────────────────────
        // PHASE 3: Load service catalog & inventory items
        // ──────────────────────────────────────────────
        $this->command->info('Phase 3: Loading service catalog & inventory…');

        // Consultation services
        $consultGeneral   = ServiceCatalog::where('code', 'CON-GEN')->firstOrFail();
        $consultFollowUp  = ServiceCatalog::where('code', 'CON-FUP')->firstOrFail();
        $consultPediatric = ServiceCatalog::where('code', 'CON-PED')->firstOrFail();
        $consultEmergency = ServiceCatalog::where('code', 'CON-EMG')->firstOrFail();

        // Lab services
        $labCBC     = ServiceCatalog::where('code', 'LAB-CBC')->firstOrFail();
        $labLFT     = ServiceCatalog::where('code', 'LAB-LFT')->firstOrFail();
        $labRFT     = ServiceCatalog::where('code', 'LAB-RFT')->firstOrFail();
        $labLipid   = ServiceCatalog::where('code', 'LAB-LPD')->firstOrFail();
        $labThyroid = ServiceCatalog::where('code', 'LAB-THY')->firstOrFail();
        $labUrine   = ServiceCatalog::where('code', 'LAB-URN')->firstOrFail();

        // Radiology services
        $radChest = ServiceCatalog::where('code', 'RAD-CXR')->firstOrFail();
        $radAbdUS = ServiceCatalog::where('code', 'RAD-UAB')->firstOrFail();
        $radECG   = ServiceCatalog::where('code', 'RAD-ECG')->firstOrFail();
        $radPelUS = ServiceCatalog::where('code', 'RAD-UPL')->firstOrFail();

        // Pharmacy inventory
        $medParacetamol  = InventoryItem::where('sku', 'PH-PCM500')->firstOrFail();
        $medAmoxicillin  = InventoryItem::where('sku', 'PH-AMX500')->firstOrFail();
        $medOmeprazole   = InventoryItem::where('sku', 'PH-OMP020')->firstOrFail();
        $medCetirizine   = InventoryItem::where('sku', 'PH-CTZ010')->firstOrFail();
        $medAmlodipine   = InventoryItem::where('sku', 'PH-AML005')->firstOrFail();
        $medMetformin    = InventoryItem::where('sku', 'PH-MET500')->firstOrFail();
        $medIbuprofen    = InventoryItem::where('sku', 'PH-IBU400')->firstOrFail();
        $medAzithromycin = InventoryItem::where('sku', 'PH-AZT500')->firstOrFail();
        $medLosartan     = InventoryItem::where('sku', 'PH-LOS050')->firstOrFail();
        $medPrednisolone = InventoryItem::where('sku', 'PH-PRD005')->firstOrFail();

        // ──────────────────────────────────────────────
        // PHASE 4: Create 10 patients (5 per doctor)
        // ──────────────────────────────────────────────
        $this->command->info('Phase 4: Creating 10 patients…');

        $patientData = [
            // Dr. Ahmed's patients
            ['first_name' => 'Mohammed', 'last_name' => 'Hassan',  'phone' => '0711000001', 'gender' => 'Male',   'date_of_birth' => '1985-03-15', 'doctor_id' => $doctor1->id],
            ['first_name' => 'Amina',    'last_name' => 'Osman',   'phone' => '0711000002', 'gender' => 'Female', 'date_of_birth' => '1990-07-22', 'doctor_id' => $doctor1->id],
            ['first_name' => 'Yusuf',    'last_name' => 'Ali',     'phone' => '0711000003', 'gender' => 'Male',   'date_of_birth' => '1978-11-05', 'doctor_id' => $doctor1->id],
            ['first_name' => 'Halima',   'last_name' => 'Ibrahim', 'phone' => '0711000004', 'gender' => 'Female', 'date_of_birth' => '1995-01-30', 'doctor_id' => $doctor1->id],
            ['first_name' => 'Omar',     'last_name' => 'Khalid',  'phone' => '0711000005', 'gender' => 'Male',   'date_of_birth' => '2018-06-10', 'doctor_id' => $doctor1->id],
            // Dr. Fatima's patients
            ['first_name' => 'Fatuma',   'last_name' => 'Abdi',    'phone' => '0711000006', 'gender' => 'Female', 'date_of_birth' => '1988-09-14', 'doctor_id' => $doctor2->id],
            ['first_name' => 'Ahmed',    'last_name' => 'Mwangi',  'phone' => '0711000007', 'gender' => 'Male',   'date_of_birth' => '1972-04-03', 'doctor_id' => $doctor2->id],
            ['first_name' => 'Zainab',   'last_name' => 'Mohamed', 'phone' => '0711000008', 'gender' => 'Female', 'date_of_birth' => '1992-12-25', 'doctor_id' => $doctor2->id],
            ['first_name' => 'Ibrahim',  'last_name' => 'Juma',    'phone' => '0711000009', 'gender' => 'Male',   'date_of_birth' => '1965-08-17', 'doctor_id' => $doctor2->id],
            ['first_name' => 'Khadija',  'last_name' => 'Saidi',   'phone' => '0711000010', 'gender' => 'Female', 'date_of_birth' => '2000-02-28', 'doctor_id' => $doctor2->id],
        ];

        $patients = [];
        foreach ($patientData as $pd) {
            $patients[] = Patient::create(array_merge($pd, [
                'status'        => 'registered',
                'registered_at' => now()->subHours(rand(1, 8)),
            ]));
        }

        // ──────────────────────────────────────────────
        // PHASE 5: Clinical Scenarios
        // ──────────────────────────────────────────────
        $this->command->info('Phase 5: Running clinical scenarios…');

        // ── P1: Mohammed (Dr. Ahmed) — Full workup: All 4 departments ──
        $this->command->info('  [1/10] Mohammed Hassan — Consult + Lab + Rad + Pharmacy');
        $v1 = $this->triagePatient($patients[0], $triageNurse, '130/85', 37.2, 78, 18, 82.5, 175, 97.5, 'Persistent cough and fever for 5 days', 'normal');
        $this->advanceToDoctor($patients[0], $v1);
        $this->createConsultationInvoice($patients[0], $v1, $doctor1, $receptionist, $consultGeneral);
        $this->createLabInvoice($patients[0], $v1, $doctor1, $receptionist, $labTech, $labCBC, 'CBC: WBC 12.5, RBC 4.8, Hb 14.2, Plt 280');
        $this->createRadiologyInvoice($patients[0], $v1, $doctor1, $receptionist, $radTech, $radChest, 'Bilateral lower lobe infiltrates consistent with pneumonia');
        $this->createPharmacyInvoice($patients[0], $v1, $doctor1, $pharmacist, [
            ['item' => $medAmoxicillin, 'qty' => 21, 'name' => 'Amoxicillin 500mg', 'dosage' => '500mg', 'freq' => 'TDS', 'duration' => '7 days'],
            ['item' => $medParacetamol, 'qty' => 20, 'name' => 'Paracetamol 500mg', 'dosage' => '1g', 'freq' => 'QID PRN', 'duration' => '5 days'],
        ], 'Community-acquired pneumonia');
        $this->completePatient($patients[0], $v1);

        // ── P2: Amina (Dr. Ahmed) — Consult + Lab×2 + Pharmacy ──
        $this->command->info('  [2/10] Amina Osman — Consult + LFT + RFT + Pharmacy');
        $v2 = $this->triagePatient($patients[1], $triageNurse, '120/78', 36.8, 72, 16, 65.0, 162, 98.0, 'Abdominal pain and nausea for 3 days', 'normal');
        $this->advanceToDoctor($patients[1], $v2);
        $this->createConsultationInvoice($patients[1], $v2, $doctor1, $receptionist, $consultGeneral);
        $this->createLabInvoice($patients[1], $v2, $doctor1, $receptionist, $labTech, $labLFT, 'LFT: ALT 35, AST 28, ALP 95, Bilirubin 0.8 — normal');
        $this->createLabInvoice($patients[1], $v2, $doctor1, $receptionist, $labTech, $labRFT, 'RFT: Creatinine 0.9, BUN 15, eGFR >90 — normal renal function');
        $this->createPharmacyInvoice($patients[1], $v2, $doctor1, $pharmacist, [
            ['item' => $medOmeprazole, 'qty' => 14, 'name' => 'Omeprazole 20mg', 'dosage' => '20mg', 'freq' => 'BD', 'duration' => '7 days'],
        ], 'Acute gastritis');
        $this->completePatient($patients[1], $v2);

        // ── P3: Yusuf (Dr. Ahmed) — Cardiac workup: Consult + Lab + Rad + Pharmacy ──
        $this->command->info('  [3/10] Yusuf Ali — Consult + Lipid + ECG + Pharmacy');
        $v3 = $this->triagePatient($patients[2], $triageNurse, '165/100', 36.9, 88, 20, 95.0, 170, 96.0, 'Routine checkup, headaches recently', 'high');
        $this->advanceToDoctor($patients[2], $v3);
        $this->createConsultationInvoice($patients[2], $v3, $doctor1, $receptionist, $consultGeneral);
        $this->createLabInvoice($patients[2], $v3, $doctor1, $receptionist, $labTech, $labLipid, 'Lipid: TC 260, LDL 180, HDL 38, TG 210 — dyslipidemia');
        $this->createRadiologyInvoice($patients[2], $v3, $doctor1, $receptionist, $radTech, $radECG, 'Normal sinus rhythm, LVH by voltage criteria');
        $this->createPharmacyInvoice($patients[2], $v3, $doctor1, $pharmacist, [
            ['item' => $medAmlodipine, 'qty' => 30, 'name' => 'Amlodipine 5mg', 'dosage' => '5mg', 'freq' => 'OD', 'duration' => '30 days'],
            ['item' => $medLosartan,   'qty' => 30, 'name' => 'Losartan 50mg', 'dosage' => '50mg', 'freq' => 'OD', 'duration' => '30 days'],
        ], 'Newly diagnosed hypertension with dyslipidemia');
        $this->completePatient($patients[2], $v3);

        // ── P4: Halima (Dr. Ahmed) — Follow-up + Thyroid lab + Pharmacy ──
        $this->command->info('  [4/10] Halima Ibrahim — Follow-up + Thyroid + Pharmacy');
        $v4 = $this->triagePatient($patients[3], $triageNurse, '110/70', 36.5, 68, 16, 58.0, 160, 99.0, 'Follow-up for fatigue and weight gain', 'low');
        $this->advanceToDoctor($patients[3], $v4);
        $this->createConsultationInvoice($patients[3], $v4, $doctor1, $receptionist, $consultFollowUp);
        $this->createLabInvoice($patients[3], $v4, $doctor1, $receptionist, $labTech, $labThyroid, 'TSH 8.5 (elevated), Free T4 0.7 — subclinical hypothyroidism');
        $this->createPharmacyInvoice($patients[3], $v4, $doctor1, $pharmacist, [
            ['item' => $medCetirizine, 'qty' => 10, 'name' => 'Cetirizine 10mg', 'dosage' => '10mg', 'freq' => 'OD', 'duration' => '10 days'],
        ], 'Thyroid follow-up, antihistamine for allergic rhinitis');
        $this->completePatient($patients[3], $v4);

        // ── P5: Omar (Dr. Ahmed) — Pediatric consult + Pharmacy only ──
        $this->command->info('  [5/10] Omar Khalid — Pediatric consult + Pharmacy');
        $v5 = $this->triagePatient($patients[4], $triageNurse, '90/60', 38.1, 110, 22, 22.0, 110, 97.0, 'Fever, runny nose, mild ear pain', 'normal');
        $this->advanceToDoctor($patients[4], $v5);
        $this->createConsultationInvoice($patients[4], $v5, $doctor1, $receptionist, $consultPediatric);
        $this->createPharmacyInvoice($patients[4], $v5, $doctor1, $pharmacist, [
            ['item' => $medParacetamol, 'qty' => 10, 'name' => 'Paracetamol 500mg', 'dosage' => '250mg', 'freq' => 'TDS', 'duration' => '3 days'],
        ], 'URTI with acute otitis media');
        $this->completePatient($patients[4], $v5);

        // ── P6: Fatuma (Dr. Fatima) — Full workup: All 4 departments ──
        $this->command->info('  [6/10] Fatuma Abdi — Full workup (Dr. Fatima)');
        $v6 = $this->triagePatient($patients[5], $triageNurse, '125/82', 37.0, 76, 18, 70.0, 165, 98.5, 'Persistent abdominal discomfort, bloating', 'normal');
        $this->advanceToDoctor($patients[5], $v6);
        $this->createConsultationInvoice($patients[5], $v6, $doctor2, $receptionist, $consultGeneral);
        $this->createLabInvoice($patients[5], $v6, $doctor2, $receptionist, $labTech, $labCBC, 'CBC: WBC 7.2, RBC 4.1, Hb 11.8, Plt 310 — mild anemia');
        $this->createRadiologyInvoice($patients[5], $v6, $doctor2, $receptionist, $radTech, $radAbdUS, 'No organomegaly, mild fatty liver, no free fluid');
        $this->createPharmacyInvoice($patients[5], $v6, $doctor2, $pharmacist, [
            ['item' => $medOmeprazole, 'qty' => 14, 'name' => 'Omeprazole 20mg', 'dosage' => '20mg', 'freq' => 'BD', 'duration' => '7 days'],
            ['item' => $medMetformin,  'qty' => 30, 'name' => 'Metformin 500mg', 'dosage' => '500mg', 'freq' => 'BD', 'duration' => '15 days'],
        ], 'Functional dyspepsia, prediabetes');
        $this->completePatient($patients[5], $v6);

        // ── P7: Ahmed Mwangi (Dr. Fatima) — EXTERNAL REFERRER scenario ──
        $this->command->info('  [7/10] Ahmed Mwangi — EXTERNAL REFERRER (Dr. Kariuki @ 5%)');
        $v7 = $this->triagePatient($patients[6], $triageNurse, '150/95', 37.1, 82, 18, 88.0, 172, 96.5, 'Referred from Dr. Kariuki for cardiac evaluation', 'high');
        $this->advanceToDoctor($patients[6], $v7);
        $this->createConsultationInvoice($patients[6], $v7, $doctor2, $receptionist, $consultGeneral);
        $this->createLabInvoice($patients[6], $v7, $doctor2, $receptionist, $labTech, $labLipid, 'Lipid: TC 240, LDL 160, HDL 42, TG 190', 'Dr. Kariuki', 5.0);
        $this->createRadiologyInvoice($patients[6], $v7, $doctor2, $receptionist, $radTech, $radECG, 'Sinus tachycardia, premature ventricular complexes', 'Dr. Kariuki', 5.0);
        $this->completePatient($patients[6], $v7);

        // ── P8: Zainab (Dr. Fatima) — Consult + Urinalysis + Pharmacy ──
        $this->command->info('  [8/10] Zainab Mohamed — Consult + Urinalysis + Pharmacy');
        $v8 = $this->triagePatient($patients[7], $triageNurse, '118/75', 37.5, 80, 16, 62.0, 158, 98.0, 'Painful urination, increased frequency', 'normal');
        $this->advanceToDoctor($patients[7], $v8);
        $this->createConsultationInvoice($patients[7], $v8, $doctor2, $receptionist, $consultGeneral);
        $this->createLabInvoice($patients[7], $v8, $doctor2, $receptionist, $labTech, $labUrine, 'Urine: Leukocytes ++, Nitrites +, RBC 5-10/hpf — UTI confirmed');
        $this->createPharmacyInvoice($patients[7], $v8, $doctor2, $pharmacist, [
            ['item' => $medAzithromycin, 'qty' => 6,  'name' => 'Azithromycin 500mg', 'dosage' => '500mg', 'freq' => 'OD', 'duration' => '3 days'],
            ['item' => $medParacetamol,  'qty' => 15, 'name' => 'Paracetamol 500mg', 'dosage' => '1g', 'freq' => 'TDS PRN', 'duration' => '5 days'],
        ], 'Urinary tract infection');
        $this->completePatient($patients[7], $v8);

        // ── P9: Ibrahim (Dr. Fatima) — Emergency + Lab + Rad + Pharmacy (3 meds) ──
        $this->command->info('  [9/10] Ibrahim Juma — Emergency + CBC + CXR + 3 meds');
        $v9 = $this->triagePatient($patients[8], $triageNurse, '90/55', 38.8, 105, 28, 72.0, 168, 91.0, 'Severe SOB, productive cough, fever', 'urgent');
        $this->advanceToDoctor($patients[8], $v9);
        $this->createConsultationInvoice($patients[8], $v9, $doctor2, $receptionist, $consultEmergency);
        $this->createLabInvoice($patients[8], $v9, $doctor2, $receptionist, $labTech, $labCBC, 'CBC: WBC 18.5, Hb 12.1, Plt 420 — leukocytosis');
        $this->createRadiologyInvoice($patients[8], $v9, $doctor2, $receptionist, $radTech, $radChest, 'Right middle/lower lobe consolidation, small pleural effusion');
        $this->createPharmacyInvoice($patients[8], $v9, $doctor2, $pharmacist, [
            ['item' => $medAzithromycin,  'qty' => 5,  'name' => 'Azithromycin 500mg', 'dosage' => '500mg', 'freq' => 'OD', 'duration' => '5 days'],
            ['item' => $medIbuprofen,     'qty' => 15, 'name' => 'Ibuprofen 400mg', 'dosage' => '400mg', 'freq' => 'TDS', 'duration' => '5 days'],
            ['item' => $medPrednisolone,  'qty' => 10, 'name' => 'Prednisolone 5mg', 'dosage' => '20mg', 'freq' => 'OD', 'duration' => '5 days'],
        ], 'Severe community-acquired pneumonia');
        $this->completePatient($patients[8], $v9);

        // ── P10: Khadija (Dr. Fatima) — Follow-up + Radiology only ──
        $this->command->info('  [10/10] Khadija Saidi — Follow-up + Pelvic US only');
        $v10 = $this->triagePatient($patients[9], $triageNurse, '115/72', 36.6, 70, 16, 55.0, 155, 99.0, 'Follow-up for pelvic pain', 'low');
        $this->advanceToDoctor($patients[9], $v10);
        $this->createConsultationInvoice($patients[9], $v10, $doctor2, $receptionist, $consultFollowUp);
        $this->createRadiologyInvoice($patients[9], $v10, $doctor2, $receptionist, $radTech, $radPelUS, 'Normal uterus and ovaries, no adnexal masses');
        $this->completePatient($patients[9], $v10);

        // ──────────────────────────────────────────────
        // PHASE 6: Verification Report
        // ──────────────────────────────────────────────
        $this->printVerificationReport($doctor1, $doctor2, $labTech, $radTech, $pharmacist);
    }

    // ────────────────────────────────────────────────────────────────
    // DATA WIPE
    // ────────────────────────────────────────────────────────────────

    private function wipeTransactionalData(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Financial
        DB::table('revenue_ledgers')->truncate();
        DB::table('doctor_payouts')->truncate();
        DB::table('audit_logs')->truncate();
        DB::table('zakat_transactions')->truncate();

        // Clinical (order matters even with FK checks off — be thorough)
        DB::table('invoice_items')->truncate();
        DB::table('invoices')->truncate();
        DB::table('prescription_items')->truncate();
        DB::table('prescriptions')->truncate();
        DB::table('triage_vitals')->truncate();
        DB::table('visits')->truncate();
        DB::table('patients')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->info('  ✓ Wiped invoices, ledger, payouts, prescriptions, triage, visits, patients');
    }

    // ────────────────────────────────────────────────────────────────
    // WORKFLOW HELPERS
    // ────────────────────────────────────────────────────────────────

    /**
     * Register → Triage: create Visit + TriageVital, return Visit.
     */
    private function triagePatient(
        Patient $patient, User $nurse,
        string $bp, float $temp, int $pulse, int $rr,
        float $weight, float $height, float $spo2,
        string $complaint, string $priority
    ): Visit {
        $visit = Visit::create([
            'patient_id'        => $patient->id,
            'doctor_id'         => $patient->doctor_id,
            'triage_nurse_id'   => $nurse->id,
            'visit_date'        => now()->toDateString(),
            'status'            => 'triage',
            'registered_at'     => $patient->registered_at,
            'triage_started_at' => now(),
        ]);

        TriageVital::create([
            'patient_id'        => $patient->id,
            'visit_id'          => $visit->id,
            'blood_pressure'    => $bp,
            'temperature'       => $temp,
            'pulse_rate'        => $pulse,
            'respiratory_rate'  => $rr,
            'weight'            => $weight,
            'height'            => $height,
            'oxygen_saturation' => $spo2,
            'chief_complaint'   => $complaint,
            'priority'          => $priority,
            'recorded_by'       => $nurse->id,
        ]);

        $patient->update([
            'status'            => 'triage',
            'triage_started_at' => now(),
        ]);

        return $visit;
    }

    /**
     * Triage → With Doctor: advance both patient + visit status.
     */
    private function advanceToDoctor(Patient $patient, Visit $visit): void
    {
        $patient->update([
            'status'            => 'with_doctor',
            'doctor_started_at' => now(),
        ]);
        $visit->update([
            'status'            => 'with_doctor',
            'doctor_started_at' => now(),
        ]);
    }

    /**
     * Complete: mark patient + visit as completed.
     */
    private function completePatient(Patient $patient, Visit $visit): void
    {
        $patient->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
        $visit->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // INVOICE CREATORS
    // ────────────────────────────────────────────────────────────────

    /**
     * Consultation — upfront payment, doctor is performer.
     * markPaid() triggers distribute() immediately.
     */
    private function createConsultationInvoice(
        Patient $patient, Visit $visit, User $doctor, User $receptionist, ServiceCatalog $service
    ): void {
        $invoice = Invoice::create([
            'patient_id'            => $patient->id,
            'patient_type'          => 'clinic',
            'department'            => 'consultation',
            'service_name'          => $service->name,
            'total_amount'          => $service->price,
            'prescribing_doctor_id' => $doctor->id,
            'performed_by_user_id'  => $doctor->id,
            'status'                => Invoice::STATUS_PENDING,
            'has_prescribed_items'  => false,
            'service_catalog_id'    => $service->id,
            'visit_id'              => $visit->id,
        ]);

        // Link consultation fee to visit
        $visit->update(['consultation_fee_invoice_id' => $invoice->id]);

        // Receptionist collects upfront payment → triggers full distribution
        $invoice->markPaid('cash', $receptionist->id);
    }

    /**
     * Lab — upfront payment flow:
     *   1. Create invoice (pending, no performer)
     *   2. Receptionist marks paid → distributes revenue, COGS, doctor referral, owner remainder
     *   3. Lab tech starts work (assigns performer on paid invoice)
     *   4. Lab tech saves report → completeAndDistribute() adds performer commission
     */
    private function createLabInvoice(
        Patient $patient, Visit $visit, User $doctor, User $receptionist, User $labTech,
        ServiceCatalog $service, string $report,
        ?string $referrerName = null, ?float $referrerPct = null
    ): void {
        $invoice = Invoice::create([
            'patient_id'            => $patient->id,
            'patient_type'          => 'clinic',
            'department'            => 'lab',
            'service_name'          => $service->name,
            'total_amount'          => $service->price,
            'prescribing_doctor_id' => $doctor->id,
            'status'                => Invoice::STATUS_PENDING,
            'has_prescribed_items'  => false,
            'service_catalog_id'    => $service->id,
            'visit_id'              => $visit->id,
            'referrer_name'         => $referrerName,
            'referrer_percentage'   => $referrerPct,
        ]);

        // 1. Upfront payment
        $invoice->markPaid('cash', $receptionist->id);

        // 2. Lab tech starts work
        $invoice->refresh();
        $invoice->startWork($labTech->id);

        // 3. Save report and complete
        $invoice->refresh();
        $invoice->update(['report_text' => $report]);
        $invoice->refresh();
        $invoice->completeAndDistribute();
    }

    /**
     * Radiology — same upfront flow as Lab.
     */
    private function createRadiologyInvoice(
        Patient $patient, Visit $visit, User $doctor, User $receptionist, User $radTech,
        ServiceCatalog $service, string $report,
        ?string $referrerName = null, ?float $referrerPct = null
    ): void {
        $invoice = Invoice::create([
            'patient_id'            => $patient->id,
            'patient_type'          => 'clinic',
            'department'            => 'radiology',
            'service_name'          => $service->name,
            'total_amount'          => $service->price,
            'prescribing_doctor_id' => $doctor->id,
            'status'                => Invoice::STATUS_PENDING,
            'has_prescribed_items'  => false,
            'service_catalog_id'    => $service->id,
            'visit_id'              => $visit->id,
            'referrer_name'         => $referrerName,
            'referrer_percentage'   => $referrerPct,
        ]);

        // 1. Upfront payment
        $invoice->markPaid('cash', $receptionist->id);

        // 2. Rad tech starts work
        $invoice->refresh();
        $invoice->startWork($radTech->id);

        // 3. Save report and complete
        $invoice->refresh();
        $invoice->update(['report_text' => $report]);
        $invoice->refresh();
        $invoice->completeAndDistribute();
    }

    /**
     * Pharmacy — traditional flow with COGS:
     *   1. Create prescription + items
     *   2. Create invoice + invoice items (with COGS from WAC)
     *   3. pending → in_progress → completed → paid
     *   4. Stock deducted via InventoryService::recordOutbound()
     */
    private function createPharmacyInvoice(
        Patient $patient, Visit $visit, User $doctor, User $pharmacist,
        array $medications, string $diagnosis
    ): void {
        $inventoryService = app(InventoryService::class);

        // Create prescription
        $prescription = Prescription::create([
            'patient_id' => $patient->id,
            'doctor_id'  => $doctor->id,
            'visit_id'   => $visit->id,
            'diagnosis'  => $diagnosis,
            'status'     => 'active',
        ]);

        foreach ($medications as $med) {
            $prescription->items()->create([
                'inventory_item_id' => $med['item']->id,
                'medication_name'   => $med['name'],
                'quantity'          => $med['qty'],
                'dosage'            => $med['dosage'],
                'frequency'         => $med['freq'],
                'duration'          => $med['duration'],
            ]);
        }

        // Build invoice items from inventory selling prices
        $totalAmount = 0;
        $invoiceItemsData = [];

        foreach ($medications as $med) {
            $item = $med['item']->fresh();
            $lineTotal = $med['qty'] * (float) $item->selling_price;
            $lineCogs  = $med['qty'] * (float) $item->weighted_avg_cost;
            $totalAmount += $lineTotal;

            $invoiceItemsData[] = [
                'inventory_item_id' => $item->id,
                'description'       => $med['name'],
                'quantity'          => $med['qty'],
                'unit_price'        => (float) $item->selling_price,
                'cost_price'        => (float) $item->weighted_avg_cost,
                'line_total'        => $lineTotal,
                'line_cogs'         => $lineCogs,
            ];
        }

        // Create invoice
        $invoice = Invoice::create([
            'patient_id'            => $patient->id,
            'patient_type'          => 'clinic',
            'department'            => 'pharmacy',
            'service_name'          => 'Prescription Dispensing',
            'total_amount'          => $totalAmount,
            'prescribing_doctor_id' => $doctor->id,
            'performed_by_user_id'  => $pharmacist->id,
            'status'                => Invoice::STATUS_PENDING,
            'has_prescribed_items'  => true,
            'prescription_id'       => $prescription->id,
            'visit_id'              => $visit->id,
        ]);

        // Add invoice items
        foreach ($invoiceItemsData as $iiData) {
            InvoiceItem::create(array_merge($iiData, ['invoice_id' => $invoice->id]));
        }

        // Pharmacy workflow: pending → in_progress
        $invoice->startWork($pharmacist->id);
        $invoice->refresh();

        // Dispense from stock
        foreach ($medications as $med) {
            $inventoryService->recordOutbound(
                $med['item']->fresh(),
                $med['qty'],
                'invoice',
                $invoice->id,
                $pharmacist
            );
        }

        // in_progress → completed → paid
        $invoice->markCompleted();
        $invoice->refresh();
        $invoice->markPaid('cash', $pharmacist->id);

        // Mark prescription dispensed
        $prescription->update(['status' => 'dispensed']);
    }

    // ────────────────────────────────────────────────────────────────
    // VERIFICATION REPORT
    // ────────────────────────────────────────────────────────────────

    private function printVerificationReport(User $doctor1, User $doctor2, User $labTech, User $radTech, User $pharmacist): void
    {
        $this->command->newLine();
        $this->command->warn('╔══════════════════════════════════════════════════════════╗');
        $this->command->warn('║              VERIFICATION REPORT                        ║');
        $this->command->warn('╚══════════════════════════════════════════════════════════╝');

        $totalInvoices = Invoice::where('status', Invoice::STATUS_PAID)->count();
        $this->command->info("Total Paid Invoices: {$totalInvoices}");
        $this->command->newLine();

        // ── Revenue by department ──
        $this->command->info('═══ REVENUE BY DEPARTMENT (Revenue Ledger Credits) ═══');
        $departments = ['consultation', 'lab', 'radiology', 'pharmacy'];
        $totalRevenue = 0;

        foreach ($departments as $dept) {
            $rev = (float) RevenueLedger::where('entry_type', 'credit')
                ->where('category', 'revenue')
                ->whereHas('invoice', fn ($q) => $q->where('department', $dept))
                ->sum('amount');
            $totalRevenue += $rev;
            $this->command->info(sprintf('  %-14s %s', ucfirst($dept) . ':', number_format($rev, 2)));
        }
        $this->command->info(sprintf('  %-14s %s', 'TOTAL:', number_format($totalRevenue, 2)));

        // ── COGS ──
        $this->command->newLine();
        $this->command->info('═══ COGS (Revenue Ledger Debits) ═══');
        $totalCogs = 0;

        foreach ($departments as $dept) {
            $cogs = (float) RevenueLedger::where('entry_type', 'debit')
                ->where('category', 'cogs')
                ->whereHas('invoice', fn ($q) => $q->where('department', $dept))
                ->sum('amount');
            if ($cogs > 0) {
                $totalCogs += $cogs;
                $this->command->info(sprintf('  %-14s %s', ucfirst($dept) . ':', number_format($cogs, 2)));
            }
        }
        $this->command->info(sprintf('  %-14s %s', 'TOTAL:', number_format($totalCogs, 2)));

        // ── Commissions per staff ──
        $this->command->newLine();
        $this->command->info('═══ COMMISSIONS BY STAFF ═══');
        $staffList = [$doctor1, $doctor2, $labTech, $radTech, $pharmacist];
        $totalCommissions = 0;

        foreach ($staffList as $user) {
            $comm = (float) RevenueLedger::where('user_id', $user->id)
                ->where('category', 'commission')
                ->sum('amount');
            if ($comm > 0) {
                $unpaid = (float) RevenueLedger::where('user_id', $user->id)
                    ->where('category', 'commission')
                    ->whereNull('payout_id')
                    ->sum('amount');
                $totalCommissions += $comm;
                $this->command->info(sprintf(
                    '  %-20s %s  (unpaid: %s)',
                    $user->name . ':',
                    number_format($comm, 2),
                    number_format($unpaid, 2)
                ));
            }
        }

        // External referrer commissions
        $extRefComm = (float) RevenueLedger::whereNull('user_id')
            ->where('category', 'commission')
            ->where('role_type', 'Referrer')
            ->sum('amount');
        if ($extRefComm > 0) {
            $totalCommissions += $extRefComm;
            $this->command->info(sprintf('  %-20s %s', 'Ext Referrers:', number_format($extRefComm, 2)));
        }
        $this->command->info(sprintf('  %-20s %s', 'TOTAL:', number_format($totalCommissions, 2)));

        // ── Owner remainder ──
        $ownerRemainder = (float) RevenueLedger::where('category', 'owner_remainder')->sum('amount');
        $this->command->newLine();
        $this->command->info('═══ OWNER ═══');
        $this->command->info(sprintf('  Owner Remainder:    %s', number_format($ownerRemainder, 2)));
        $this->command->info(sprintf('  Gross Profit:       %s', number_format($totalRevenue - $totalCogs, 2)));

        // ── Balance check ──
        $this->command->newLine();
        $this->command->info('═══ LEDGER BALANCE CHECK ═══');
        $totalDebits  = (float) RevenueLedger::where('entry_type', 'debit')->sum('amount');
        $totalCredits = (float) RevenueLedger::where('entry_type', 'credit')->sum('amount');
        $imbalance    = abs($totalCredits - $totalDebits);

        $this->command->info(sprintf('  Total Credits: %s', number_format($totalCredits, 2)));
        $this->command->info(sprintf('  Total Debits:  %s', number_format($totalDebits, 2)));

        if ($imbalance < 0.05) {
            $this->command->info('  ✅ BALANCED — Credits = Debits (within rounding tolerance)');
        } else {
            $this->command->error("  ❌ IMBALANCE: {$this->fmt($imbalance)}");
        }

        // ── Split verification: Revenue = COGS + Commissions + Owner ──
        $sumParts = $totalCogs + $totalCommissions + $ownerRemainder;
        $splitDiff = abs($totalRevenue - $sumParts);

        $this->command->info(sprintf(
            '  Revenue(%s) = COGS(%s) + Comm(%s) + Owner(%s)',
            $this->fmt($totalRevenue), $this->fmt($totalCogs),
            $this->fmt($totalCommissions), $this->fmt($ownerRemainder)
        ));

        if ($splitDiff < 0.05) {
            $this->command->info('  ✅ SPLIT CORRECT — All revenue accounted for');
        } else {
            $this->command->error("  ❌ SPLIT MISMATCH: diff = {$this->fmt($splitDiff)}");
        }

        // ── Payout readiness ──
        $this->command->newLine();
        $this->command->info('═══ PAYOUT READINESS ═══');
        foreach ($staffList as $user) {
            $unpaid = (float) RevenueLedger::where('user_id', $user->id)
                ->where('category', 'commission')
                ->whereNull('payout_id')
                ->sum('amount');
            if ($unpaid > 0) {
                $this->command->info(sprintf('  %-20s %s awaiting payout', $user->name . ':', number_format($unpaid, 2)));
            }
        }

        // ── Department P&L ──
        $this->command->newLine();
        $this->command->info('═══ DEPARTMENT P&L ═══');

        foreach ($departments as $dept) {
            $rev = (float) RevenueLedger::where('entry_type', 'credit')
                ->where('category', 'revenue')
                ->whereHas('invoice', fn ($q) => $q->where('department', $dept))
                ->sum('amount');
            $cogs = (float) RevenueLedger::where('entry_type', 'debit')
                ->where('category', 'cogs')
                ->whereHas('invoice', fn ($q) => $q->where('department', $dept))
                ->sum('amount');
            $comm = (float) RevenueLedger::where('entry_type', 'debit')
                ->where('category', 'commission')
                ->whereHas('invoice', fn ($q) => $q->where('department', $dept))
                ->sum('amount');
            $ownerNet = $rev - $cogs - $comm;

            $this->command->info(sprintf(
                '  %-14s Rev %s | COGS %s | Comm %s | Owner Net %s',
                ucfirst($dept) . ':',
                $this->fmt($rev), $this->fmt($cogs), $this->fmt($comm), $this->fmt($ownerNet)
            ));
        }

        $this->command->newLine();
        $this->command->warn('═══ QC SEEDER COMPLETE ═══');
        $this->command->info("  {$totalInvoices} invoices across 10 patients, 2 doctors");
        $this->command->info('  Check: Owner Dashboard, Department P&L, Payout Analytics');
        $this->command->newLine();
    }

    private function fmt(float $n): string
    {
        return number_format($n, 2);
    }
}
