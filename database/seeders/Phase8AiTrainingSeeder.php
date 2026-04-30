<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\InventoryItem;
use App\Models\Expense;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Phase 8 AI Training Data Seeder
 *
 * Populates 90 days of realistic clinic data to give Admin AI, Ops AI,
 * and Compliance AI meaningful signals to analyse.
 *
 * Run: php artisan db:seed --class=Phase8AiTrainingSeeder
 */
class Phase8AiTrainingSeeder extends Seeder
{
    // ── actor IDs (resolved at runtime) ───────────────────────────────────────
    private int $ownerId;
    private int $doctorId;
    private int $doctor2Id;
    private int $receptionistId;
    private int $pharmacistId;
    private int $labTechId;
    private int $triageId;

    // ── suspicious actor (for anomaly signals) ─────────────────────────────
    private int $suspiciousUserId; // high discount requester + PHI scanner

    public function run(): void
    {
        $this->resolveActors();

        $this->command->info('Phase8AiTrainingSeeder — generating 90 days of training data…');

        $this->seedInventory();
        $this->seedExpenses();
        $this->seedAuditLogs();
        $this->seedAiInvocations();
        $this->seedSoc2Evidence();

        $this->command->info('Done. Admin / Ops / Compliance AI now have rich signal data.');
    }

    // ── actors ────────────────────────────────────────────────────────────────

    private function resolveActors(): void
    {
        $this->ownerId        = User::where('email', 'owner@clinic.com')->value('id');
        $this->doctorId       = User::where('email', 'doctor@clinic.com')->value('id');
        $this->doctor2Id      = User::where('email', 'doctor2@clinic.com')->value('id');
        $this->receptionistId = User::where('email', 'receptionist@clinic.com')->value('id');
        $this->pharmacistId   = User::where('email', 'pharmacy@clinic.com')->value('id');
        $this->labTechId      = User::where('email', 'lab@clinic.com')->value('id');
        $this->triageId       = User::where('email', 'triage@clinic.com')->value('id');

        // Make one of these the "suspicious" actor (receptionist — frequent discounts + PHI scans)
        $this->suspiciousUserId = $this->receptionistId;
    }

    // ── inventory ─────────────────────────────────────────────────────────────

    private function seedInventory(): void
    {
        $this->command->info('  → Inventory items…');

        $items = [
            // PHARMACY — mix of healthy, warning, critical
            ['department' => 'pharmacy', 'name' => 'Paracetamol 500mg',      'sku' => 'PH-001', 'unit' => 'tablet',  'qty' => 0,   'min' => 200, 'pp' => 2.50,   'sp' => 5.00],
            ['department' => 'pharmacy', 'name' => 'Amoxicillin 500mg',      'sku' => 'PH-002', 'unit' => 'capsule', 'qty' => 45,  'min' => 100, 'pp' => 8.00,   'sp' => 18.00],
            ['department' => 'pharmacy', 'name' => 'Metformin 500mg',        'sku' => 'PH-003', 'unit' => 'tablet',  'qty' => 320, 'min' => 150, 'pp' => 3.00,   'sp' => 7.00],
            ['department' => 'pharmacy', 'name' => 'Atorvastatin 10mg',      'sku' => 'PH-004', 'unit' => 'tablet',  'qty' => 80,  'min' => 100, 'pp' => 12.00,  'sp' => 28.00],
            ['department' => 'pharmacy', 'name' => 'Amlodipine 5mg',         'sku' => 'PH-005', 'unit' => 'tablet',  'qty' => 200, 'min' => 80,  'pp' => 6.00,   'sp' => 14.00],
            ['department' => 'pharmacy', 'name' => 'Omeprazole 20mg',        'sku' => 'PH-006', 'unit' => 'capsule', 'qty' => 0,   'min' => 120, 'pp' => 5.00,   'sp' => 12.00],
            ['department' => 'pharmacy', 'name' => 'Insulin Glargine 100IU', 'sku' => 'PH-007', 'unit' => 'vial',    'qty' => 8,   'min' => 30,  'pp' => 450.00, 'sp' => 950.00],
            ['department' => 'pharmacy', 'name' => 'Ibuprofen 400mg',        'sku' => 'PH-008', 'unit' => 'tablet',  'qty' => 600, 'min' => 200, 'pp' => 2.00,   'sp' => 4.50],
            ['department' => 'pharmacy', 'name' => 'Ceftriaxone 1g Inj',    'sku' => 'PH-009', 'unit' => 'vial',    'qty' => 12,  'min' => 25,  'pp' => 180.00, 'sp' => 380.00],
            ['department' => 'pharmacy', 'name' => 'Salbutamol Inhaler',     'sku' => 'PH-010', 'unit' => 'unit',    'qty' => 22,  'min' => 30,  'pp' => 220.00, 'sp' => 480.00],
            ['department' => 'pharmacy', 'name' => 'Dexamethasone 4mg',      'sku' => 'PH-011', 'unit' => 'ampoule', 'qty' => 40,  'min' => 50,  'pp' => 35.00,  'sp' => 75.00],
            ['department' => 'pharmacy', 'name' => 'Warfarin 5mg',           'sku' => 'PH-012', 'unit' => 'tablet',  'qty' => 150, 'min' => 100, 'pp' => 15.00,  'sp' => 32.00],
            ['department' => 'pharmacy', 'name' => 'Hydrochlorothiazide 25mg','sku'=>'PH-013',  'unit' => 'tablet',  'qty' => 90,  'min' => 100, 'pp' => 3.50,   'sp' => 8.00],
            ['department' => 'pharmacy', 'name' => 'Azithromycin 250mg',     'sku' => 'PH-014', 'unit' => 'tablet',  'qty' => 60,  'min' => 80,  'pp' => 22.00,  'sp' => 48.00],
            ['department' => 'pharmacy', 'name' => 'Ranitidine 150mg',       'sku' => 'PH-015', 'unit' => 'tablet',  'qty' => 400, 'min' => 120, 'pp' => 4.00,   'sp' => 9.00],

            // LABORATORY
            ['department' => 'laboratory', 'name' => 'CBC Reagent Kit',           'sku' => 'LB-001', 'unit' => 'kit',   'qty' => 3,  'min' => 10, 'pp' => 4500.00, 'sp' => 9000.00],
            ['department' => 'laboratory', 'name' => 'Glucose Test Strips',       'sku' => 'LB-002', 'unit' => 'strip', 'qty' => 200,'min' => 300,'pp' => 8.00,    'sp' => 18.00],
            ['department' => 'laboratory', 'name' => 'HbA1c Reagent',             'sku' => 'LB-003', 'unit' => 'kit',   'qty' => 5,  'min' => 8,  'pp' => 3200.00, 'sp' => 6500.00],
            ['department' => 'laboratory', 'name' => 'Urine Dipstick',            'sku' => 'LB-004', 'unit' => 'strip', 'qty' => 500,'min' => 200,'pp' => 3.00,    'sp' => 7.00],
            ['department' => 'laboratory', 'name' => 'Thyroid TSH Kit',           'sku' => 'LB-005', 'unit' => 'kit',   'qty' => 2,  'min' => 6,  'pp' => 5800.00, 'sp' => 11500.00],
            ['department' => 'laboratory', 'name' => 'Lipid Panel Reagent',       'sku' => 'LB-006', 'unit' => 'kit',   'qty' => 4,  'min' => 8,  'pp' => 3800.00, 'sp' => 7500.00],
            ['department' => 'laboratory', 'name' => 'Blood Culture Bottles',     'sku' => 'LB-007', 'unit' => 'bottle','qty' => 30, 'min' => 20, 'pp' => 180.00,  'sp' => 380.00],
            ['department' => 'laboratory', 'name' => 'Microscope Slides',         'sku' => 'LB-008', 'unit' => 'box',   'qty' => 8,  'min' => 5,  'pp' => 350.00,  'sp' => 750.00],
            ['department' => 'laboratory', 'name' => 'Malaria RDT Kit',           'sku' => 'LB-009', 'unit' => 'kit',   'qty' => 0,  'min' => 15, 'pp' => 280.00,  'sp' => 580.00],
            ['department' => 'laboratory', 'name' => 'Hepatitis B Surface Ag Kit','sku' => 'LB-010', 'unit' => 'kit',   'qty' => 6,  'min' => 10, 'pp' => 1200.00, 'sp' => 2400.00],

            // RADIOLOGY
            ['department' => 'radiology', 'name' => 'X-Ray Film 14x17',        'sku' => 'RD-001', 'unit' => 'sheet', 'qty' => 50, 'min' => 100,'pp' => 120.00, 'sp' => 250.00],
            ['department' => 'radiology', 'name' => 'Contrast Dye (Iohexol)',  'sku' => 'RD-002', 'unit' => 'vial',  'qty' => 8,  'min' => 20, 'pp' => 850.00, 'sp' => 1800.00],
            ['department' => 'radiology', 'name' => 'Ultrasound Gel',          'sku' => 'RD-003', 'unit' => 'litre', 'qty' => 6,  'min' => 10, 'pp' => 280.00, 'sp' => 0.00],
            ['department' => 'radiology', 'name' => 'Developer Solution',      'sku' => 'RD-004', 'unit' => 'litre', 'qty' => 15, 'min' => 10, 'pp' => 450.00, 'sp' => 0.00],
            ['department' => 'radiology', 'name' => 'Fixer Solution',          'sku' => 'RD-005', 'unit' => 'litre', 'qty' => 0,  'min' => 10, 'pp' => 380.00, 'sp' => 0.00],
        ];

        foreach ($items as $item) {
            InventoryItem::updateOrCreate(
                ['sku' => $item['sku']],
                [
                    'department'          => $item['department'],
                    'name'                => $item['name'],
                    'unit'                => $item['unit'],
                    'quantity_in_stock'   => $item['qty'],
                    'minimum_stock_level' => $item['min'],
                    'purchase_price'      => $item['pp'],
                    'selling_price'       => $item['sp'],
                    'weighted_avg_cost'   => $item['pp'],
                    'requires_prescription' => in_array($item['department'], ['pharmacy']),
                    'is_active'           => true,
                ]
            );
        }

        $this->command->info('     ' . count($items) . ' inventory items seeded.');
    }

    // ── expenses ──────────────────────────────────────────────────────────────

    private function seedExpenses(): void
    {
        $this->command->info('  → Expenses (90 days)…');

        $categories = ['variable', 'procurement', 'variable', 'variable', 'procurement'];
        $descriptions = [
            'variable'    => ['Staff refreshments', 'Office supplies', 'Utility bill', 'Cleaning supplies', 'Printer cartridges', 'Stationery', 'Tea/coffee supplies', 'Minor repairs'],
            'procurement' => ['Lab reagent restock', 'Pharmacy bulk order', 'Radiology supplies', 'Equipment maintenance', 'Instrument calibration'],
        ];
        $departments = ['pharmacy', 'laboratory', 'radiology', 'consultation'];

        $rows = [];
        $now = Carbon::now();

        for ($d = 89; $d >= 0; $d--) {
            $date = $now->copy()->subDays($d)->format('Y-m-d');
            $count = rand(1, 4);
            for ($i = 0; $i < $count; $i++) {
                $cat  = $categories[array_rand($categories)];
                $descs = $descriptions[$cat];
                $rows[] = [
                    'department'   => $departments[array_rand($departments)],
                    'description'  => $descs[array_rand($descs)],
                    'cost'         => rand(200, 8000) + (rand(0, 99) / 100),
                    'created_by'   => $this->ownerId,
                    'category'     => $cat,
                    'expense_date' => $date,
                    'created_at'   => $date . ' ' . rand(8, 17) . ':' . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00',
                    'updated_at'   => $date . ' ' . rand(8, 17) . ':' . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00',
                ];
            }
        }

        // Insert in chunks
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('expenses')->insert($chunk);
        }

        $this->command->info('     ' . count($rows) . ' expense entries seeded.');
    }

    // ── audit logs ────────────────────────────────────────────────────────────

    private function seedAuditLogs(): void
    {
        $this->command->info('  → Audit logs (90 days — building hash chain via direct inserts)…');

        $events = $this->buildEventTimeline();
        $total  = 0;
        $batch  = [];

        // Seed in one transaction per chunk; build chain incrementally.
        // Direct insert bypasses AuditLog::log() (which forces now()) while
        // still respecting the append-only trigger (INSERT is allowed, UPDATE/DELETE are not).
        $prevHash = (string) (DB::table('audit_logs')->latest('id')->value('row_hash') ?? '');

        foreach ($events as $event) {
            $ip     = $event['ip'] ?? ('192.168.1.' . rand(10, 50));
            $before = isset($event['before']) ? json_encode($event['before']) : null;
            $after  = isset($event['after'])  ? json_encode($event['after'])  : null;

            // Replicate AuditLog::canonicalJson() — alphabetically sorted keys
            $canonical = [
                'action'         => $event['action'],
                'after_state'    => $event['after']  ?? null,
                'auditable_id'   => $event['id'],
                'auditable_type' => $event['type'],
                'before_state'   => $event['before'] ?? null,
                'created_at'     => $event['ts'],
                'ip_address'     => $ip,
                'prev_hash'      => $prevHash,
                'session_id'     => null,
                'user_agent'     => null,
                'user_id'        => $event['user_id'],
            ];
            ksort($canonical);
            $rowHash = hash('sha256', $prevHash . '|' . json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $batch[] = [
                'user_id'        => $event['user_id'],
                'action'         => $event['action'],
                'auditable_type' => $event['type'],
                'auditable_id'   => $event['id'],
                'before_state'   => $before,
                'after_state'    => $after,
                'ip_address'     => $ip,
                'user_agent'     => null,
                'session_id'     => null,
                'prev_hash'      => $prevHash,
                'row_hash'       => $rowHash,
                'created_at'     => $event['ts'],
            ];

            $prevHash = $rowHash;
            $total++;

            // Flush every 200 rows in a single transaction
            if (count($batch) >= 200) {
                DB::table('audit_logs')->insert($batch);
                $batch = [];
                $this->command->info("     {$total} audit events logged…");
            }
        }

        if ($batch) {
            DB::table('audit_logs')->insert($batch);
        }

        $this->command->info("     {$total} audit log entries seeded.");
    }

    private function buildEventTimeline(): array
    {
        $events = [];
        $now    = Carbon::now();

        // ── 90 days of clinic activity ─────────────────────────────────────────
        for ($d = 89; $d >= 0; $d--) {
            $date    = $now->copy()->subDays($d);
            $dayOfWk = $date->dayOfWeek; // 0=Sun, 6=Sat
            $isWeekend = in_array($dayOfWk, [0, 6]);

            // Daily invoice volume: 8-25 weekday, 3-10 weekend
            $invoiceCount = $isWeekend ? rand(3, 10) : rand(8, 25);

            // Inject revenue spike anomalies (days 5, 22, 60 — big jump)
            if (in_array($d, [5, 22, 60])) {
                $invoiceCount = rand(45, 65);
            }
            // Inject revenue crash (day 10, 45)
            if (in_array($d, [10, 45])) {
                $invoiceCount = rand(1, 3);
            }

            for ($i = 0; $i < $invoiceCount; $i++) {
                $hour   = rand(8, 18);
                $minute = rand(0, 59);
                $ts     = $date->copy()->setTime($hour, $minute, rand(0, 59));
                $fakeInvoiceId = rand(1000, 9999);

                // invoice.created
                $events[] = [
                    'action'  => 'invoice.created',
                    'type'    => 'App\Models\Invoice',
                    'id'      => $fakeInvoiceId,
                    'user_id' => $this->receptionistId,
                    'after'   => ['department' => $this->randomDept(), 'net_amount' => rand(500, 8000)],
                    'ts'      => $ts->format('Y-m-d H:i:s'),
                ];

                // invoice.paid (80% of invoices paid same day)
                if (rand(1, 10) <= 8) {
                    $events[] = [
                        'action'  => 'invoice.paid',
                        'type'    => 'App\Models\Invoice',
                        'id'      => $fakeInvoiceId,
                        'user_id' => $this->receptionistId,
                        'before'  => ['status' => 'unpaid'],
                        'after'   => ['status' => 'paid', 'net_amount' => rand(500, 8000)],
                        'ts'      => $ts->copy()->addMinutes(rand(5, 60))->format('Y-m-d H:i:s'),
                    ];
                }
            }

            // ── Discount requests ─────────────────────────────────────────────
            // Normal pattern: 1-3/day from receptionist
            $discountCount = rand(1, 3);

            // Suspicious surge: receptionist does 12-18 discount requests on recent days (last 14 days)
            if ($d <= 14) {
                $discountCount = rand(10, 18);
            }

            for ($j = 0; $j < $discountCount; $j++) {
                $ts = $date->copy()->setTime(rand(9, 17), rand(0, 59), rand(0, 59));
                $events[] = [
                    'action'  => 'invoice.discount.requested',
                    'type'    => 'App\Models\Invoice',
                    'id'      => rand(1000, 9999),
                    'user_id' => $d <= 14 ? $this->suspiciousUserId : $this->receptionistId,
                    'after'   => ['discount_amount' => rand(100, 2000), 'discount_reason' => 'patient request'],
                    'ts'      => $ts->format('Y-m-d H:i:s'),
                ];

                // Discount approved by owner (70%)
                if (rand(1, 10) <= 7) {
                    $events[] = [
                        'action'  => 'invoice.discount.approved',
                        'type'    => 'App\Models\Invoice',
                        'id'      => rand(1000, 9999),
                        'user_id' => $this->ownerId,
                        'after'   => ['approved' => true],
                        'ts'      => $ts->copy()->addMinutes(rand(10, 120))->format('Y-m-d H:i:s'),
                    ];
                }
            }

            // ── FBR events ────────────────────────────────────────────────────
            // 90% of invoices submitted to FBR, 5% fail
            $fbrCount  = max(1, intval($invoiceCount * 0.9));
            $fbrFailed = max(0, intval($fbrCount * 0.05));

            for ($k = 0; $k < $fbrCount; $k++) {
                $ts = $date->copy()->setTime(rand(8, 18), rand(0, 59), rand(0, 59));
                $events[] = [
                    'action'  => 'fbr.invoice.submitted',
                    'type'    => 'App\Models\Invoice',
                    'id'      => rand(1000, 9999),
                    'user_id' => $this->receptionistId,
                    'after'   => ['fbr_invoice_seq' => 'FBR-' . rand(100000, 999999)],
                    'ts'      => $ts->format('Y-m-d H:i:s'),
                ];

                $isFailure = $k < $fbrFailed;
                $events[] = [
                    'action'  => $isFailure ? 'fbr.invoice.failed' : 'fbr.invoice.success',
                    'type'    => 'App\Models\Invoice',
                    'id'      => rand(1000, 9999),
                    'user_id' => $this->receptionistId,
                    'after'   => $isFailure
                        ? ['error' => 'FBR timeout', 'retry' => true]
                        : ['qr_code' => 'QR-' . rand(100000, 999999)],
                    'ts'      => $ts->copy()->addSeconds(rand(2, 15))->format('Y-m-d H:i:s'),
                ];
            }

            // ── Consultations completed ───────────────────────────────────────
            $consults = $isWeekend ? rand(2, 6) : rand(5, 15);
            for ($c = 0; $c < $consults; $c++) {
                $ts = $date->copy()->setTime(rand(9, 16), rand(0, 59), rand(0, 59));
                $docId = rand(0, 1) ? $this->doctorId : $this->doctor2Id;
                $events[] = [
                    'action'  => 'consultation.completed',
                    'type'    => 'App\Models\Patient',
                    'id'      => rand(1, 50),
                    'user_id' => $docId,
                    'after'   => ['duration_minutes' => rand(10, 45), 'diagnosis_coded' => true],
                    'ts'      => $ts->format('Y-m-d H:i:s'),
                ];
            }

            // ── PHI access — patient record views ────────────────────────────
            // Normal: each staff views 2-8 patient records/day
            $normalViewers = [$this->doctorId, $this->doctor2Id, $this->labTechId, $this->triageId];
            foreach ($normalViewers as $viewer) {
                $viewCount = rand(2, 8);
                for ($v = 0; $v < $viewCount; $v++) {
                    $ts = $date->copy()->setTime(rand(8, 17), rand(0, 59), rand(0, 59));
                    $events[] = [
                        'action'  => 'patient.viewed',
                        'type'    => 'App\Models\Patient',
                        'id'      => rand(1, 50),
                        'user_id' => $viewer,
                        'ts'      => $ts->format('Y-m-d H:i:s'),
                    ];
                }
            }

            // ANOMALY: suspicious user views 25-35 patient records within a 1-hour window on recent days
            if ($d <= 21) {
                $anomalyHour = rand(10, 14);
                $anomalyCount = rand(25, 35);
                for ($a = 0; $a < $anomalyCount; $a++) {
                    $ts = $date->copy()->setTime($anomalyHour, rand(0, 59), rand(0, 59));
                    $events[] = [
                        'action'  => 'patient.viewed',
                        'type'    => 'App\Models\Patient',
                        'id'      => rand(1, 50),
                        'user_id' => $this->suspiciousUserId,
                        'ts'      => $ts->format('Y-m-d H:i:s'),
                    ];
                }
            }

            // ── Payout-related ────────────────────────────────────────────────
            if ($d % 30 === 0) { // monthly payout events
                $events[] = [
                    'action'  => 'payout.confirmed',
                    'type'    => 'App\Models\User',
                    'id'      => $this->doctorId,
                    'user_id' => $this->ownerId,
                    'after'   => ['amount' => rand(50000, 120000), 'period' => '30d'],
                    'ts'      => $date->copy()->setTime(16, 0, 0)->format('Y-m-d H:i:s'),
                ];
            }
        }

        // Sort by timestamp so hash chain is chronological
        usort($events, fn($a, $b) => strcmp($a['ts'], $b['ts']));

        return $events;
    }

    // ── AI invocations ────────────────────────────────────────────────────────

    private function seedAiInvocations(): void
    {
        $this->command->info('  → AI invocations (last 30 days)…');

        $endpoints = [
            '/v1/admin/analyse',
            '/v1/ops/analyse',
            '/v1/compliance/analyse',
            '/v1/consult',
        ];

        $rows    = [];
        $prevHash = '';
        $now      = Carbon::now();

        for ($d = 29; $d >= 0; $d--) {
            $date  = $now->copy()->subDays($d);
            $count = rand(3, 10);

            for ($i = 0; $i < $count; $i++) {
                $endpoint    = $endpoints[array_rand($endpoints)];
                $promptHash  = bin2hex(random_bytes(32));
                $latencyMs   = rand(420, 3800);
                $outcome     = rand(1, 20) === 1 ? 'error' : 'ok';
                $ts          = $date->copy()->setTime(rand(8, 22), rand(0, 59), rand(0, 59))->format('Y-m-d H:i:s');

                $data     = $prevHash . '|' . $endpoint . '|' . $promptHash . '|' . $ts;
                $rowHash  = hash('sha256', $data);

                $rows[] = [
                    'user_id'          => $this->ownerId,
                    'case_token'       => bin2hex(random_bytes(32)),
                    'endpoint'         => substr($endpoint, 0, 64),
                    'prompt_hash'      => $promptHash,
                    'retrieval_doc_ids'=> json_encode([]),
                    'model_id'         => $endpoint === '/v1/consult' ? 'medgemma:v1' : 'llama3.1:8b',
                    'latency_ms'       => $latencyMs,
                    'outcome'          => $outcome,
                    'prev_hash'        => $prevHash,
                    'row_hash'         => $rowHash,
                    'created_at'       => $ts,
                ];

                $prevHash = $rowHash;
            }
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('ai_invocations')->insert($chunk);
        }

        $this->command->info('     ' . count($rows) . ' AI invocation records seeded.');
    }

    // ── SOC2 evidence ─────────────────────────────────────────────────────────

    private function seedSoc2Evidence(): void
    {
        $this->command->info('  → SOC2 evidence placeholder…');

        $dir  = storage_path('app/soc2');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $zipPath = $dir . '/evidence_' . now()->format('Y_m_d') . '.zip';
        if (!file_exists($zipPath)) {
            // Create a minimal valid zip placeholder
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE) === true) {
                $zip->addFromString('README.txt', 'SOC2 evidence bundle — generated by Phase8AiTrainingSeeder on ' . now()->toIso8601String());
                $zip->addFromString('flag_snapshot.json', json_encode(['generated_at' => now()->toIso8601String(), 'note' => 'placeholder']));
                $zip->close();
            }
        }

        $this->command->info('     SOC2 evidence zip created at storage/app/soc2/.');
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function randomDept(): string
    {
        return ['consultation', 'laboratory', 'radiology', 'pharmacy'][rand(0, 3)];
    }
}
