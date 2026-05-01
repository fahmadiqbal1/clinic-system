<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Populates one full year of realistic clinic data (May 2025 – Apr 2026).
 *
 * Anomaly scenarios embedded for AI intelligence-layer training:
 *  A1  Aug–Sep 2025  – Dengue/Malaria epidemic: lab-test spike (×3 volume)
 *  A2  Nov 2025      – Discount anomaly: 20 % of invoices discounted >25 %
 *  A3  Feb 2026      – Revenue dip: 2-week maintenance closure
 *  A4  Jun 2025      – Expense spike: PKR 550 k X-ray machine capital purchase
 *  A5  Aug 2025      – Pharmacy stockout: Paracetamol / Ciprofloxacin depleted
 *  A6  Jan 2026      – Over-procurement: bulk reagent order during slow season
 *  A7  Scattered     – FBR non-compliance: high-value invoices without FBR submission
 */
class FullYearDataSeeder extends Seeder
{
    // ── Pakistani name pools ──────────────────────────────────────────────
    private array $maleFNs = [
        'Ahmed', 'Muhammad', 'Ali', 'Hassan', 'Usman', 'Bilal', 'Tariq', 'Khalid',
        'Imran', 'Zubair', 'Faisal', 'Arif', 'Nawaz', 'Iqbal', 'Asif', 'Hamid',
        'Zahid', 'Kamran', 'Irfan', 'Rashid', 'Sajid', 'Tahir', 'Shafiq', 'Wasim',
        'Amir', 'Raza', 'Naeem', 'Waqar', 'Adeel', 'Babar', 'Farhan', 'Umer',
        'Zain', 'Sohail', 'Jawad', 'Naveed', 'Salman', 'Danish', 'Aamir', 'Noman',
    ];
    private array $femaleFNs = [
        'Fatima', 'Ayesha', 'Zainab', 'Maryam', 'Sara', 'Sana', 'Hira', 'Nadia',
        'Rabia', 'Amna', 'Sobia', 'Rukhsana', 'Shaista', 'Naila', 'Faiza', 'Sadia',
        'Asma', 'Iram', 'Uzma', 'Shazia', 'Saira', 'Mahnoor', 'Iqra', 'Nimra',
        'Rida', 'Aroha', 'Sumbal', 'Laiba', 'Aliya', 'Zara', 'Hafsa', 'Khadija',
        'Saimah', 'Misbah', 'Abida', 'Najma', 'Bushra', 'Tahira', 'Lubna', 'Samra',
    ];
    private array $lastNames = [
        'Khan', 'Shah', 'Ahmed', 'Malik', 'Qureshi', 'Chaudhry', 'Siddiqui', 'Ansari',
        'Rizvi', 'Butt', 'Rana', 'Sheikh', 'Mirza', 'Baig', 'Hashmi', 'Abbasi',
        'Farooqi', 'Gillani', 'Naqvi', 'Zaidi', 'Javed', 'Aslam', 'Nawaz', 'Cheema',
        'Gondal', 'Bhatti', 'Gul', 'Wazir', 'Afridi', 'Yousuf', 'Rauf', 'Hussain',
        'Sardar', 'Channa', 'Memon', 'Patel', 'Khawaja', 'Toor', 'Bajwa', 'Virk',
    ];

    // ── Clinic staff IDs (from actual DB) ────────────────────────────────
    private const OWNER_ID        = 1;
    private const RECEPTIONIST_ID = 4;
    private const NURSE_ID        = 5;
    private const LAB_ID          = 6;
    private const RADIOLOGY_ID    = 7;
    private const PHARMACIST_ID   = 8;
    private array $doctorIds = [2, 3, 10, 11];

    // ── Service catalog IDs ───────────────────────────────────────────────
    private array $consultSvcIds = [1, 2, 3, 4, 5];
    private array $labSvcIds     = [6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33];
    private array $radioSvcIds   = [34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51];
    private array $pharmaSvcIds  = [52,53,54,55,56,57];

    // Dengue/Malaria epidemic test IDs (Aug–Sep A1 anomaly)
    private array $epidemicLabIds = [22, 23, 24, 25, 26]; // Widal, Malaria, Dengue NS1, hCG, CRP

    // Runtime-loaded
    private array $servicePrices = [];
    private array $pharmaInvIds  = [];

    // ── Monthly visit targets (simulate seasonality + anomalies) ─────────
    private array $monthlyTargets = [
        '2025-05' => 210, // Normal spring
        '2025-06' => 185, // Summer heat lull
        '2025-07' => 230, // Pre-monsoon GI/heat
        '2025-08' => 390, // A1: Dengue/Malaria epidemic peak
        '2025-09' => 320, // A1: Epidemic continues
        '2025-10' => 205, // Post-monsoon recovery
        '2025-11' => 190, // A2: Discount anomaly month
        '2025-12' => 260, // Winter respiratory peak
        '2026-01' => 155, // Cold & slow
        '2026-02' => 110, // A3: Maintenance closure (2 weeks off)
        '2026-03' => 215, // Recovery
        '2026-04' => 200, // Spring normal
    ];

    public function run(): void
    {
        $this->command->info('Loading reference data...');
        $svcs = DB::table('service_catalog')->select('id', 'name', 'price')->get();
        foreach ($svcs as $s) {
            $this->servicePrices[$s->id] = ['price' => (float)$s->price, 'name' => $s->name];
        }
        $this->pharmaInvIds = DB::table('inventory_items')
            ->where('department', 'pharmacy')->where('is_active', true)
            ->pluck('id')->toArray();

        $this->command->info('Creating 300 patients...');
        $patientIds = $this->createPatients(300);

        $this->command->info('Creating full-year clinic activity...');
        $this->createClinicActivity($patientIds);

        $this->command->info('Creating operational expenses...');
        $this->createExpenses();

        $this->command->info('Creating procurement requests...');
        $this->createProcurements();

        $this->command->info('Seeding stock replenishment movements...');
        $this->createStockMovements();

        $this->command->info('Full year data seeded successfully.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // PATIENTS
    // ═══════════════════════════════════════════════════════════════════
    private function createPatients(int $count): array
    {
        $rows = [];
        $now  = now()->toDateTimeString();
        for ($i = 0; $i < $count; $i++) {
            $gender = $i % 2 === 0 ? 'male' : 'female';
            $fn     = $gender === 'male'
                ? $this->maleFNs[array_rand($this->maleFNs)]
                : $this->femaleFNs[array_rand($this->femaleFNs)];
            $rows[] = [
                'first_name'        => $fn,
                'last_name'         => $this->lastNames[array_rand($this->lastNames)],
                'gender'            => $gender,
                'phone'             => '03' . rand(0, 4) . rand(10000000, 99999999),
                'email'             => null,
                'cnic'              => null,
                'date_of_birth'     => Carbon::now()->subYears(rand(5, 75))->subDays(rand(0, 365))->toDateString(),
                'doctor_id'         => $this->doctorIds[array_rand($this->doctorIds)],
                'status'            => 'active',
                'registration_type' => 'walkin',
                'deleted_at'        => null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
        }

        DB::table('patients')->insert($rows);
        return DB::table('patients')->orderByDesc('id')->limit($count)->pluck('id')->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════
    // MAIN ACTIVITY LOOP
    // ═══════════════════════════════════════════════════════════════════
    private function createClinicActivity(array $patientIds): void
    {
        $start = Carbon::create(2025, 5, 1);
        $end   = Carbon::create(2026, 4, 30);

        $invoicesBatch   = [];
        $itemsBatch      = [];
        $ledgersBatch    = [];
        $visitsBatch     = [];
        $triagebatch     = [];
        $prescribeBatch  = [];

        $cursor = $start->copy();
        $totalVisits = 0;

        while ($cursor->lte($end)) {
            // Skip Sundays (Pakistani clinics typically closed)
            if ($cursor->dayOfWeek === Carbon::SUNDAY) {
                $cursor->addDay();
                continue;
            }

            $monthKey    = $cursor->format('Y-m');
            $target      = $this->monthlyTargets[$monthKey] ?? 200;
            $workingDays = $this->workingDaysInMonth($cursor);
            $dailyTarget = max(1, (int)round($target / $workingDays));

            // A3: Feb maintenance — 2-week closure (days 1–14)
            if ($monthKey === '2026-02' && $cursor->day <= 14) {
                $cursor->addDay();
                continue;
            }

            for ($v = 0; $v < $dailyTarget; $v++) {
                $patientId  = $patientIds[array_rand($patientIds)];
                $doctorId   = $this->doctorIds[array_rand($this->doctorIds)];
                $visitTime  = $cursor->copy()->addHours(rand(8, 17))->addMinutes(rand(0, 59));

                // Create visit
                $visitId = DB::table('visits')->insertGetId([
                    'patient_id'    => $patientId,
                    'doctor_id'     => $doctorId,
                    'triage_nurse_id' => self::NURSE_ID,
                    'visit_date'    => $cursor->toDateString(),
                    'status'        => 'completed',
                    'registered_at' => $visitTime->toDateTimeString(),
                    'triage_started_at' => $visitTime->copy()->addMinutes(rand(5, 20))->toDateTimeString(),
                    'doctor_started_at' => $visitTime->copy()->addMinutes(rand(25, 45))->toDateTimeString(),
                    'completed_at'  => $visitTime->copy()->addMinutes(rand(50, 90))->toDateTimeString(),
                    'consultation_notes' => $this->randomConsultNote($monthKey),
                    'created_at'    => $visitTime->toDateTimeString(),
                    'updated_at'    => $visitTime->toDateTimeString(),
                ]);

                // Triage vitals
                DB::table('triage_vitals')->insert($this->buildVitals($patientId, $visitId, $visitTime, $monthKey));

                // Consultation invoice
                $isFollowUp = rand(0, 4) === 0;
                $consultId  = $isFollowUp ? 2 : 1; // Follow-up vs General
                if ($monthKey === '2025-08' || $monthKey === '2025-09') {
                    $consultId = rand(0, 4) === 0 ? 5 : 1; // More emergency consults
                }
                $consultInvoice = $this->buildInvoice($patientId, $visitId, $doctorId, 'consultation', $consultId, $visitTime, $monthKey);
                $consultInvId   = DB::table('invoices')->insertGetId($consultInvoice);
                DB::table('invoice_items')->insert($this->buildItem($consultInvId, $consultId, 1));
                DB::table('revenue_ledgers')->insert($this->buildLedger($consultInvId, $doctorId, 'doctor', $consultInvoice['net_amount'], $visitTime));

                // Lab invoice (A1: epidemic months get high lab volume)
                $labChance = in_array($monthKey, ['2025-08', '2025-09']) ? 70 : 30;
                if (rand(1, 100) <= $labChance) {
                    $labIds = $this->pickLabTests($monthKey);
                    $labTotal = 0;
                    foreach ($labIds as $lid) {
                        $labTotal += $this->servicePrices[$lid]['price'] ?? 800;
                    }
                    $labDiscount = 0;
                    $labNet      = $labTotal - $labDiscount;
                    $labInv      = $this->buildRawInvoice($patientId, $visitId, self::LAB_ID, 'lab', 'Lab Tests', null, $visitTime, $labTotal, $labDiscount, $labNet, $monthKey);
                    $labInvId    = DB::table('invoices')->insertGetId($labInv);
                    foreach ($labIds as $lid) {
                        DB::table('invoice_items')->insert($this->buildItem($labInvId, $lid, 1));
                    }
                    DB::table('revenue_ledgers')->insert($this->buildLedger($labInvId, self::LAB_ID, 'lab_technician', $labNet, $visitTime));
                }

                // Radiology invoice (10% chance)
                if (rand(1, 100) <= 10) {
                    $radId  = $this->radioSvcIds[array_rand($this->radioSvcIds)];
                    $radAmt = $this->servicePrices[$radId]['price'] ?? 2500;
                    $radInv = $this->buildRawInvoice($patientId, $visitId, self::RADIOLOGY_ID, 'radiology', 'Radiology', null, $visitTime, $radAmt, 0, $radAmt, $monthKey);
                    $radId2 = DB::table('invoices')->insertGetId($radInv);
                    DB::table('invoice_items')->insert($this->buildItem($radId2, $radId, 1));
                    DB::table('revenue_ledgers')->insert($this->buildLedger($radId2, self::RADIOLOGY_ID, 'radiologist', $radAmt, $visitTime));
                }

                // Prescription + pharmacy invoice (35% of visits)
                if (rand(1, 100) <= 35) {
                    $rxId = DB::table('prescriptions')->insertGetId([
                        'patient_id' => $patientId,
                        'visit_id'   => $visitId,
                        'doctor_id'  => $doctorId,
                        'diagnosis'  => $this->randomDiagnosis($monthKey),
                        'status'     => 'dispensed',
                        'created_at' => $visitTime->toDateTimeString(),
                        'updated_at' => $visitTime->toDateTimeString(),
                    ]);

                    // Pharmacy invoice (linked to prescription)
                    $invCount = rand(1, 4);
                    $pharmaTotal = 0;
                    $pharmaItems = [];
                    for ($pi = 0; $pi < $invCount; $pi++) {
                        $invItemId = $this->pharmaInvIds[array_rand($this->pharmaInvIds)];
                        $invItem   = DB::table('inventory_items')->where('id', $invItemId)->first();
                        $qty       = rand(1, 3) * 10; // strips/tablets
                        $pharmaItems[] = ['inv_id' => $invItemId, 'qty' => $qty, 'price' => (float)($invItem->selling_price ?? 10), 'cost' => (float)($invItem->purchase_price ?? 5)];
                        $pharmaTotal  += ($invItem->selling_price ?? 10) * $qty;
                    }

                    // A5: stockout scenario — in Aug, occasionally sell at zero stock (anomaly)
                    $pharmaNet = $pharmaTotal;
                    $pharmaInv = $this->buildRawInvoice($patientId, $visitId, self::PHARMACIST_ID, 'pharmacy', 'Pharmacy Dispensing', $rxId, $visitTime, $pharmaTotal, 0, $pharmaNet, $monthKey);
                    $pharmaInv['has_prescribed_items'] = 1;
                    $pharmaInvId = DB::table('invoices')->insertGetId($pharmaInv);

                    foreach ($pharmaItems as $pi) {
                        DB::table('invoice_items')->insert([
                            'invoice_id'         => $pharmaInvId,
                            'service_catalog_id' => null,
                            'inventory_item_id'  => $pi['inv_id'],
                            'description'        => 'Medication dispensed',
                            'quantity'           => $pi['qty'],
                            'unit_price'         => $pi['price'],
                            'cost_price'         => $pi['cost'],
                            'line_total'         => $pi['price'] * $pi['qty'],
                            'line_cogs'          => $pi['cost'] * $pi['qty'],
                            'created_at'         => $visitTime->toDateTimeString(),
                            'updated_at'         => $visitTime->toDateTimeString(),
                        ]);
                    }
                    DB::table('revenue_ledgers')->insert($this->buildLedger($pharmaInvId, self::PHARMACIST_ID, 'pharmacist', $pharmaNet, $visitTime));
                }

                $totalVisits++;
            }

            $cursor->addDay();
        }

        $this->command->info("  Created {$totalVisits} visits across 12 months.");
    }

    // ═══════════════════════════════════════════════════════════════════
    // BUILDERS
    // ═══════════════════════════════════════════════════════════════════

    private function buildInvoice(int $patientId, int $visitId, int $userId, string $dept, int $svcId, Carbon $at, string $monthKey): array
    {
        $price   = $this->servicePrices[$svcId]['price'] ?? 1500;
        $name    = $this->servicePrices[$svcId]['name'] ?? 'Consultation';
        [$disc, $net] = $this->applyDiscount($price, $monthKey);

        return $this->buildRawInvoice($patientId, $visitId, $userId, $dept, $name, null, $at, $price, $disc, $net, $monthKey, $svcId);
    }

    private function buildRawInvoice(int $patientId, int $visitId, int $userId, string $dept, string $svcName, ?int $rxId, Carbon $at, float $total, float $disc, float $net, string $monthKey, ?int $svcCatalogId = null): array
    {
        // A7: FBR non-compliance — invoices > PKR 50000 occasionally left unsubmitted
        $fbrStatus = 'pending';
        if ($net > 50000 && rand(0, 2) === 0) {
            $fbrStatus = 'pending'; // intentionally not submitted — compliance anomaly
        } elseif ($net > 5000) {
            $fbrStatus = rand(0, 1) ? 'submitted' : 'pending';
        }

        return [
            'patient_id'          => $patientId,
            'visit_id'            => $visitId,
            'department'          => $dept,
            'service_name'        => $svcName,
            'service_catalog_id'  => $svcCatalogId,
            'prescription_id'     => $rxId,
            'total_amount'        => round($total, 2),
            'discount_amount'     => round($disc, 2),
            'discount_status'     => $disc > 0 ? 'approved' : 'none',
            'discount_reason'     => $disc > 0 ? $this->discountReason($monthKey) : null,
            'net_amount'          => round($net, 2),
            'prescribing_doctor_id' => in_array($dept, ['consultation', 'pharmacy']) ? $userId : null,
            'has_prescribed_items'  => $rxId ? 1 : 0,
            'patient_type'        => 'walk_in',
            'status'              => 'paid',
            'payment_method'      => ['cash', 'card', 'jazzcash', 'easypaisa'][rand(0, 3)],
            'paid_at'             => $at->toDateTimeString(),
            'paid_by'             => self::RECEPTIONIST_ID,
            'fbr_status'          => $fbrStatus,
            'created_by_user_id'  => self::RECEPTIONIST_ID,
            'performed_by_user_id'=> $userId,
            'created_at'          => $at->toDateTimeString(),
            'updated_at'          => $at->toDateTimeString(),
        ];
    }

    private function buildItem(int $invoiceId, int $svcId, int $qty): array
    {
        $price = $this->servicePrices[$svcId]['price'] ?? 1000;
        $cost  = round($price * 0.38, 2);
        return [
            'invoice_id'        => $invoiceId,
            'service_catalog_id'=> $svcId,
            'inventory_item_id' => null,
            'description'       => $this->servicePrices[$svcId]['name'] ?? 'Service',
            'quantity'          => $qty,
            'unit_price'        => $price,
            'cost_price'        => $cost,
            'line_total'        => $price * $qty,
            'line_cogs'         => $cost * $qty,
            'created_at'        => now()->toDateTimeString(),
            'updated_at'        => now()->toDateTimeString(),
        ];
    }

    private function buildLedger(int $invoiceId, int $userId, string $roleType, float $amount, Carbon $at): array
    {
        $cost   = round($amount * 0.38, 2);
        $profit = round($amount - $cost, 2);
        $comm   = round($amount * (in_array($roleType, ['doctor']) ? 0.30 : 0.10), 2);
        return [
            'invoice_id'      => $invoiceId,
            'user_id'         => $userId,
            'role_type'       => $roleType,
            'entry_type'      => 'revenue',
            'category'        => $roleType,
            'percentage'      => in_array($roleType, ['doctor']) ? 30 : 10,
            'amount'          => round($amount, 2),
            'net_cost'        => $cost,
            'gross_profit'    => $profit,
            'commission_amount'=> $comm,
            'is_prescribed'   => 0,
            'payout_status'   => rand(0, 3) === 0 ? 'paid' : 'pending',
            'paid_at'         => rand(0, 3) === 0 ? $at->copy()->addDays(rand(15, 45))->toDateTimeString() : null,
            'created_at'      => $at->toDateTimeString(),
            'updated_at'      => $at->toDateTimeString(),
        ];
    }

    private function buildVitals(int $patientId, int $visitId, Carbon $at, string $monthKey): array
    {
        // A1: Dengue months — more patients present with fever/low SpO2
        $isEpidemic = in_array($monthKey, ['2025-08', '2025-09']);
        $temp   = $isEpidemic ? rand(380, 403) / 10 : rand(366, 381) / 10;
        $spo2   = $isEpidemic && rand(0, 3) === 0 ? rand(90, 95) : rand(96, 100);
        $priority = ($temp > 39.0 || $spo2 < 93) ? 'urgent' : ($temp > 38.0 ? 'high' : 'normal');
        return [
            'patient_id'        => $patientId,
            'visit_id'          => $visitId,
            'blood_pressure'    => rand(110, 145) . '/' . rand(70, 95),
            'temperature'       => $temp,
            'pulse_rate'        => $isEpidemic ? rand(85, 115) : rand(68, 90),
            'respiratory_rate'  => rand(14, 22),
            'weight'            => rand(45, 95),
            'height'            => rand(148, 185),
            'oxygen_saturation' => $spo2,
            'chief_complaint'   => $this->chiefComplaint($monthKey),
            'priority'          => $priority,
            'recorded_by'       => self::NURSE_ID,
            'created_at'        => $at->toDateTimeString(),
            'updated_at'        => $at->toDateTimeString(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // DISCOUNT LOGIC (A2 anomaly)
    // ═══════════════════════════════════════════════════════════════════
    private function applyDiscount(float $price, string $monthKey): array
    {
        // A2: November 2025 — elevated discount rates (suspicious pattern)
        if ($monthKey === '2025-11' && rand(1, 100) <= 20) {
            $pct  = rand(25, 45) / 100; // 25–45% discount (anomalous)
            $disc = round($price * $pct, 2);
            return [$disc, $price - $disc];
        }
        // Normal: 5% of invoices get a small discount
        if (rand(1, 100) <= 5) {
            $disc = round($price * (rand(5, 15) / 100), 2);
            return [$disc, $price - $disc];
        }
        return [0.0, $price];
    }

    private function discountReason(string $monthKey): string
    {
        if ($monthKey === '2025-11') {
            return collect(['Staff relative', 'Owner approved', 'Hardship case', 'VIP patient'])->random();
        }
        return collect(['Senior citizen', 'Regular patient', 'Medical staff family', 'Financial hardship'])->random();
    }

    // ═══════════════════════════════════════════════════════════════════
    // LAB TEST PICKER (A1 anomaly)
    // ═══════════════════════════════════════════════════════════════════
    private function pickLabTests(string $monthKey): array
    {
        $isEpidemic = in_array($monthKey, ['2025-08', '2025-09']);
        if ($isEpidemic) {
            // High probability of dengue/malaria panel
            $panel = [6, 24, 23]; // CBC + Dengue NS1 + Malaria Rapid
            if (rand(0, 1)) $panel[] = 26; // CRP
            if (rand(0, 2) === 0) $panel[] = 22; // Widal (typhoid co-infection)
            return $panel;
        }
        // Normal: 1–3 random lab tests
        $count = rand(1, 3);
        $ids   = [];
        $pool  = $this->labSvcIds;
        shuffle($pool);
        return array_slice($pool, 0, $count);
    }

    // ═══════════════════════════════════════════════════════════════════
    // EXPENSES (A4 anomaly)
    // ═══════════════════════════════════════════════════════════════════
    private function createExpenses(): void
    {
        $base = [
            'Rent'         => 95000,
            'Utilities'    => 28000,
            'Internet'     => 8500,
            'Cleaning'     => 12000,
            'Stationery'   => 6500,
            'Security'     => 15000,
            'Tea/Kitchen'  => 5500,
        ];

        $cursor = Carbon::create(2025, 5, 1);
        $end    = Carbon::create(2026, 4, 30);

        while ($cursor->lte($end)) {
            $monthKey = $cursor->format('Y-m');
            $monthEnd = $cursor->copy()->endOfMonth()->toDateString();

            // Fixed monthly expenses
            foreach ($base as $cat => $amt) {
                $variance = rand(-8, 8) / 100;
                DB::table('expenses')->insert([
                    'department'  => 'general',
                    'category'    => $cat,
                    'expense_date'=> $monthEnd,
                    'description' => $cat . ' — ' . $cursor->format('M Y'),
                    'cost'        => round($amt * (1 + $variance), 2),
                    'created_by'  => self::OWNER_ID,
                    'created_at'  => $cursor->toDateTimeString(),
                    'updated_at'  => $cursor->toDateTimeString(),
                ]);
            }

            // Variable: consumables
            $consumables = $monthKey === '2025-08' ? 55000 : rand(18000, 32000);
            DB::table('expenses')->insert([
                'department'  => 'laboratory',
                'category'    => 'Consumables',
                'expense_date'=> $monthEnd,
                'description' => 'Lab consumables & reagents — ' . $cursor->format('M Y'),
                'cost'        => $consumables,
                'created_by'  => self::LAB_ID,
                'created_at'  => $cursor->toDateTimeString(),
                'updated_at'  => $cursor->toDateTimeString(),
            ]);

            // Variable: maintenance
            $maint = rand(5000, 18000);
            if ($monthKey === '2026-02') $maint = 285000; // A3: clinic overhaul costs
            DB::table('expenses')->insert([
                'department'  => 'general',
                'category'    => 'Maintenance',
                'expense_date'=> $monthEnd,
                'description' => 'Equipment maintenance — ' . $cursor->format('M Y'),
                'cost'        => $maint,
                'created_by'  => self::OWNER_ID,
                'created_at'  => $cursor->toDateTimeString(),
                'updated_at'  => $cursor->toDateTimeString(),
            ]);

            // A4: June 2025 — capital equipment spike
            if ($monthKey === '2025-06') {
                DB::table('expenses')->insert([
                    'department'  => 'radiology',
                    'category'    => 'Equipment',
                    'expense_date'=> '2025-06-15',
                    'description' => 'Digital X-ray machine upgrade (DR system)',
                    'cost'        => 548000.00,
                    'created_by'  => self::OWNER_ID,
                    'created_at'  => '2025-06-15 10:00:00',
                    'updated_at'  => '2025-06-15 10:00:00',
                ]);
            }

            // Pharmacy expenses (monthly stock cost proxy)
            DB::table('expenses')->insert([
                'department'  => 'pharmacy',
                'category'    => 'Consumables',
                'expense_date'=> $monthEnd,
                'description' => 'Pharmacy stock purchase — ' . $cursor->format('M Y'),
                'cost'        => round(rand(35000, 70000) * ($monthKey === '2025-08' ? 1.9 : 1), 2),
                'created_by'  => self::PHARMACIST_ID,
                'created_at'  => $cursor->toDateTimeString(),
                'updated_at'  => $cursor->toDateTimeString(),
            ]);

            $cursor->addMonth()->startOfMonth();
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // PROCUREMENTS (A6 anomaly)
    // ═══════════════════════════════════════════════════════════════════
    private function createProcurements(): void
    {
        $entries = [
            // Normal quarterly re-orders
            ['2025-06-10', 'pharmacy',   'Regular pharma restock Q2',       'approved', 42000],
            ['2025-07-08', 'laboratory', 'Lab reagents — pre-monsoon',       'approved', 68000],
            // A5: Emergency order after August stockout
            ['2025-08-22', 'pharmacy',   'URGENT: Paracetamol & Cipro stockout reorder', 'approved', 95000],
            ['2025-09-05', 'laboratory', 'Dengue/Malaria rapid test kits — emergency',   'approved', 115000],
            // Normal
            ['2025-10-15', 'pharmacy',   'Regular pharma restock Q4',        'approved', 48000],
            ['2025-11-20', 'radiology',  'X-ray film & contrast dye',        'approved', 35000],
            ['2025-12-12', 'pharmacy',   'Year-end pharma restock',          'approved', 55000],
            // A6: Jan 2026 — over-procurement during slow month
            ['2026-01-08', 'laboratory', 'Lab reagent bulk order (over-stocked for slow season)', 'approved', 195000],
            ['2026-01-18', 'pharmacy',   'Bulk medication order Jan 2026 — excess',       'approved', 145000],
            // Recovery
            ['2026-02-28', 'pharmacy',   'Post-maintenance supplies restocking', 'approved', 38000],
            ['2026-03-10', 'pharmacy',   'Regular pharma restock Q1 2026',  'approved', 50000],
            ['2026-04-05', 'laboratory', 'Lab consumables Q2 2026',         'pending',  42000],
        ];

        foreach ($entries as [$date, $dept, $notes, $status, $cost]) {
            DB::table('procurement_requests')->insert([
                'department'   => $dept,
                'type'         => 'inventory',
                'requested_by' => match($dept) {
                    'pharmacy'   => self::PHARMACIST_ID,
                    'laboratory' => self::LAB_ID,
                    'radiology'  => self::RADIOLOGY_ID,
                    default      => self::OWNER_ID,
                },
                'approved_by'  => $status === 'approved' ? self::OWNER_ID : null,
                'status'       => $status,
                'notes'        => $notes . ' — estimated PKR ' . number_format($cost),
                'created_at'   => $date . ' 09:00:00',
                'updated_at'   => $date . ' 09:00:00',
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // STOCK MOVEMENTS (A5 anomaly visible in depletion pattern)
    // ═══════════════════════════════════════════════════════════════════
    private function createStockMovements(): void
    {
        $pharmaItems = DB::table('inventory_items')
            ->where('department', 'pharmacy')
            ->where('is_active', true)
            ->get(['id', 'purchase_price', 'quantity_in_stock']);

        $now = now();

        foreach ($pharmaItems as $item) {
            // Initial stock-in at start of year
            DB::table('stock_movements')->insert([
                'inventory_item_id' => $item->id,
                'type'              => 'purchase',
                'quantity'          => rand(200, 1000),
                'unit_cost'         => $item->purchase_price,
                'batch_number'      => 'BATCH-2025-' . strtoupper(substr(md5($item->id . 'A'), 0, 6)),
                'expiry_date'       => '2027-06-30',
                'manufacturer'      => 'Ferozsons / Getz / AGP',
                'notes'             => 'Opening stock May 2025',
                'reference_type'    => 'procurement',
                'created_by'        => self::PHARMACIST_ID,
                'created_at'        => '2025-05-01 08:00:00',
                'updated_at'        => '2025-05-01 08:00:00',
            ]);

            // Mid-year restock (July)
            DB::table('stock_movements')->insert([
                'inventory_item_id' => $item->id,
                'type'              => 'purchase',
                'quantity'          => rand(100, 500),
                'unit_cost'         => $item->purchase_price,
                'batch_number'      => 'BATCH-2025-' . strtoupper(substr(md5($item->id . 'B'), 0, 6)),
                'expiry_date'       => '2027-12-31',
                'notes'             => 'Q3 restock Jul 2025',
                'reference_type'    => 'procurement',
                'created_by'        => self::PHARMACIST_ID,
                'created_at'        => '2025-07-10 08:00:00',
                'updated_at'        => '2025-07-10 08:00:00',
            ]);

            // A5: IDs 1 (Paracetamol) & 8 (Ciprofloxacin) — show high depletion in Aug
            if (in_array($item->id, [1, 8])) {
                DB::table('stock_movements')->insert([
                    'inventory_item_id' => $item->id,
                    'type'              => 'dispense',
                    'quantity'          => rand(800, 1200), // Depletes stock
                    'unit_cost'         => $item->purchase_price,
                    'notes'             => 'High August demand (Dengue epidemic)',
                    'reference_type'    => 'dispensing',
                    'created_by'        => self::PHARMACIST_ID,
                    'created_at'        => '2025-08-25 14:00:00',
                    'updated_at'        => '2025-08-25 14:00:00',
                ]);
                // Emergency restock after stockout
                DB::table('stock_movements')->insert([
                    'inventory_item_id' => $item->id,
                    'type'              => 'purchase',
                    'quantity'          => rand(500, 800),
                    'unit_cost'         => $item->purchase_price,
                    'batch_number'      => 'EMRG-2025-' . strtoupper(substr(md5($item->id . 'C'), 0, 6)),
                    'expiry_date'       => '2027-09-30',
                    'notes'             => 'Emergency restock after stockout — A5 event',
                    'reference_type'    => 'procurement',
                    'created_by'        => self::PHARMACIST_ID,
                    'created_at'        => '2025-08-28 10:00:00',
                    'updated_at'        => '2025-08-28 10:00:00',
                ]);
            }

            // A6: Excess stock-in Jan 2026 for all items (over-procurement)
            DB::table('stock_movements')->insert([
                'inventory_item_id' => $item->id,
                'type'              => 'purchase',
                'quantity'          => rand(400, 900), // Disproportionately large
                'unit_cost'         => $item->purchase_price,
                'batch_number'      => 'BULK-2026-' . strtoupper(substr(md5($item->id . 'D'), 0, 6)),
                'expiry_date'       => '2028-03-31',
                'notes'             => 'Jan 2026 bulk order — A6 over-procurement',
                'reference_type'    => 'procurement',
                'created_by'        => self::PHARMACIST_ID,
                'created_at'        => '2026-01-15 08:00:00',
                'updated_at'        => '2026-01-15 08:00:00',
            ]);
        }

        // Lab reagent movements for key items
        $labItems = DB::table('inventory_items')->where('department', 'laboratory')->get(['id', 'purchase_price']);
        foreach ($labItems as $item) {
            DB::table('stock_movements')->insert([
                'inventory_item_id' => $item->id,
                'type'              => 'purchase',
                'quantity'          => rand(50, 200),
                'unit_cost'         => $item->purchase_price,
                'batch_number'      => 'LAB-2025-' . strtoupper(substr(md5($item->id), 0, 6)),
                'notes'             => 'Opening lab reagent stock',
                'reference_type'    => 'procurement',
                'created_by'        => self::LAB_ID,
                'created_at'        => '2025-05-01 08:00:00',
                'updated_at'        => '2025-05-01 08:00:00',
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════

    private function workingDaysInMonth(Carbon $cursor): int
    {
        $first = $cursor->copy()->startOfMonth();
        $last  = $cursor->copy()->endOfMonth();
        $days  = 0;
        while ($first->lte($last)) {
            if ($first->dayOfWeek !== Carbon::SUNDAY) $days++;
            $first->addDay();
        }
        return max(1, $days);
    }

    private function randomConsultNote(string $monthKey): string
    {
        $notes = [
            '2025-08' => ['Dengue fever suspected, sent for NS1 antigen test', 'High fever with thrombocytopenia, dengue workup ordered', 'Malaria symptoms post-travel, rapid test ordered'],
            '2025-09' => ['Typhoid fever — Widal positive', 'Dengue convalescent phase, platelet count improving', 'Post-monsoon GI infection'],
            '2025-12' => ['Upper respiratory tract infection', 'Community-acquired pneumonia, CXR ordered', 'Seasonal influenza — symptomatic management'],
            '2026-01' => ['Chronic condition follow-up', 'Hypertension management review', 'Diabetic annual review'],
        ];
        $pool = $notes[$monthKey] ?? [
            'Routine OPD consultation',
            'Follow-up visit for chronic condition',
            'Acute illness — symptomatic management',
            'Preventive health check',
            'Medication review and refill',
        ];
        return $pool[array_rand($pool)];
    }

    private function randomDiagnosis(string $monthKey): string
    {
        $diags = [
            '2025-08' => ['Dengue haemorrhagic fever (ICD-10: A91)', 'Malaria, unspecified (ICD-10: B54)', 'Viral fever NOS (ICD-10: A99)'],
            '2025-09' => ['Typhoid fever (ICD-10: A01.0)', 'Dengue fever (ICD-10: A97.0)', 'Acute gastroenteritis (ICD-10: A09)'],
            '2025-12' => ['Acute upper respiratory infection (ICD-10: J06.9)', 'Community-acquired pneumonia (ICD-10: J18.9)'],
        ];
        $pool = $diags[$monthKey] ?? [
            'Hypertension (ICD-10: I10)',
            'Type 2 Diabetes Mellitus (ICD-10: E11.9)',
            'Acute pharyngitis (ICD-10: J02.9)',
            'Urinary tract infection (ICD-10: N39.0)',
            'Iron deficiency anaemia (ICD-10: D50.9)',
            'Gastro-oesophageal reflux (ICD-10: K21.0)',
        ];
        return $pool[array_rand($pool)];
    }

    private function chiefComplaint(string $monthKey): string
    {
        $complaints = [
            '2025-08' => ['High fever and body aches', 'Fever with rash', 'Severe headache and eye pain', 'Bleeding gums', 'Joint pain and fever'],
            '2025-09' => ['Persistent fever', 'Abdominal pain and diarrhoea', 'Weakness and fatigue'],
            '2025-12' => ['Sore throat and cough', 'Difficulty breathing', 'Chest tightness'],
            '2026-01' => ['Blood pressure check', 'Diabetes follow-up', 'General wellness check'],
        ];
        $pool = $complaints[$monthKey] ?? [
            'Headache', 'Fever', 'Cough and cold', 'Stomach ache', 'Back pain',
            'Fatigue', 'Skin rash', 'Joint pain', 'Chest discomfort', 'Dizziness',
        ];
        return $pool[array_rand($pool)];
    }
}
