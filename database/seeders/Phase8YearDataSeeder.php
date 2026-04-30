<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\InventoryItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Phase 8 — Full-Year AI Training Data Seeder
 *
 * 365 days of realistic clinic scenarios across five story arcs:
 *   Arc A (days 365-270): Baseline steady-state — healthy operations, normal variance
 *   Arc B (days 269-180): Ramadan / seasonal spike — high volume, supply pressure
 *   Arc C (days 179-120): Post-Ramadan correction — overstocking, discount surge
 *   Arc D (days 119-60):  Rogue actor — insider discount abuse + PHI over-access
 *   Arc E (days 59-0):    Recovery + audit — compliance review, restock, normalisation
 *
 * Tools exercised:
 *   Admin AI  → revenue_anomaly, discount_risk, fbr_status, payout_audit
 *   Ops AI    → inventory_velocity, procurement, expense_category, queue_health
 *   Compliance→ audit_chain_verify, phi_access_scan, flag_snapshot, evidence_gap
 */
class Phase8YearDataSeeder extends Seeder
{
    private int $ownerId;
    private int $doctorId;
    private int $doctor2Id;
    private int $receptionistId;
    private int $pharmacistId;
    private int $labTechId;
    private int $triageId;
    private int $rogueUserId;   // becomes abusive in Arc D

    // Inventory item IDs resolved after upsert
    private array $itemIds = [];

    public function run(): void
    {
        $this->resolveActors();

        $this->command->info('Phase8YearDataSeeder — 365 days across 5 story arcs');

        DB::statement('SET foreign_key_checks = 0');

        $this->seedInventory();
        $this->seedExpenses();
        $this->seedAuditLogs();
        $this->seedAiInvocations();
        $this->seedSoc2Evidence();

        DB::statement('SET foreign_key_checks = 1');

        $this->command->info('Done — 1 year of rich AI training data loaded.');
    }

    // ── actors ────────────────────────────────────────────────────────────

    private function resolveActors(): void
    {
        $this->ownerId        = User::where('email', 'owner@clinic.com')->value('id');
        $this->doctorId       = User::where('email', 'doctor@clinic.com')->value('id');
        $this->doctor2Id      = User::where('email', 'doctor2@clinic.com')->value('id');
        $this->receptionistId = User::where('email', 'receptionist@clinic.com')->value('id');
        $this->pharmacistId   = User::where('email', 'pharmacy@clinic.com')->value('id');
        $this->labTechId      = User::where('email', 'lab@clinic.com')->value('id');
        $this->triageId       = User::where('email', 'triage@clinic.com')->value('id');
        $this->rogueUserId    = $this->receptionistId; // receptionist turns rogue in Arc D
    }

    // ── inventory ─────────────────────────────────────────────────────────

    private function seedInventory(): void
    {
        $this->command->info('  → Inventory (30 items with realistic stock levels)…');

        $catalog = [
            // PHARMACY
            ['dept'=>'pharmacy','name'=>'Paracetamol 500mg',       'sku'=>'PH-001','unit'=>'tablet', 'qty'=>0,  'min'=>200,'pp'=>2.50, 'sp'=>5.00],
            ['dept'=>'pharmacy','name'=>'Amoxicillin 500mg',        'sku'=>'PH-002','unit'=>'capsule','qty'=>45, 'min'=>100,'pp'=>8.00, 'sp'=>18.00],
            ['dept'=>'pharmacy','name'=>'Metformin 500mg',          'sku'=>'PH-003','unit'=>'tablet', 'qty'=>320,'min'=>150,'pp'=>3.00, 'sp'=>7.00],
            ['dept'=>'pharmacy','name'=>'Atorvastatin 10mg',        'sku'=>'PH-004','unit'=>'tablet', 'qty'=>80, 'min'=>100,'pp'=>12.00,'sp'=>28.00],
            ['dept'=>'pharmacy','name'=>'Amlodipine 5mg',           'sku'=>'PH-005','unit'=>'tablet', 'qty'=>200,'min'=>80, 'pp'=>6.00, 'sp'=>14.00],
            ['dept'=>'pharmacy','name'=>'Omeprazole 20mg',          'sku'=>'PH-006','unit'=>'capsule','qty'=>0,  'min'=>120,'pp'=>5.00, 'sp'=>12.00],
            ['dept'=>'pharmacy','name'=>'Insulin Glargine 100IU',   'sku'=>'PH-007','unit'=>'vial',   'qty'=>8,  'min'=>30, 'pp'=>450.00,'sp'=>950.00],
            ['dept'=>'pharmacy','name'=>'Ibuprofen 400mg',          'sku'=>'PH-008','unit'=>'tablet', 'qty'=>600,'min'=>200,'pp'=>2.00, 'sp'=>4.50],
            ['dept'=>'pharmacy','name'=>'Ceftriaxone 1g Inj',       'sku'=>'PH-009','unit'=>'vial',   'qty'=>12, 'min'=>25, 'pp'=>180.00,'sp'=>380.00],
            ['dept'=>'pharmacy','name'=>'Salbutamol Inhaler',        'sku'=>'PH-010','unit'=>'unit',   'qty'=>22, 'min'=>30, 'pp'=>220.00,'sp'=>480.00],
            ['dept'=>'pharmacy','name'=>'Dexamethasone 4mg',         'sku'=>'PH-011','unit'=>'ampoule','qty'=>40, 'min'=>50, 'pp'=>35.00, 'sp'=>75.00],
            ['dept'=>'pharmacy','name'=>'Warfarin 5mg',              'sku'=>'PH-012','unit'=>'tablet', 'qty'=>150,'min'=>100,'pp'=>15.00,'sp'=>32.00],
            ['dept'=>'pharmacy','name'=>'Azithromycin 250mg',        'sku'=>'PH-013','unit'=>'tablet', 'qty'=>60, 'min'=>80, 'pp'=>22.00,'sp'=>48.00],
            ['dept'=>'pharmacy','name'=>'Ranitidine 150mg',          'sku'=>'PH-014','unit'=>'tablet', 'qty'=>400,'min'=>120,'pp'=>4.00, 'sp'=>9.00],
            ['dept'=>'pharmacy','name'=>'Loperamide 2mg',            'sku'=>'PH-015','unit'=>'tablet', 'qty'=>0,  'min'=>100,'pp'=>3.50, 'sp'=>8.00],
            // LABORATORY
            ['dept'=>'laboratory','name'=>'CBC Reagent Kit',         'sku'=>'LB-001','unit'=>'kit',   'qty'=>3,  'min'=>10, 'pp'=>4500.00,'sp'=>9000.00],
            ['dept'=>'laboratory','name'=>'Glucose Test Strips',     'sku'=>'LB-002','unit'=>'strip', 'qty'=>200,'min'=>300,'pp'=>8.00,  'sp'=>18.00],
            ['dept'=>'laboratory','name'=>'HbA1c Reagent',           'sku'=>'LB-003','unit'=>'kit',   'qty'=>5,  'min'=>8,  'pp'=>3200.00,'sp'=>6500.00],
            ['dept'=>'laboratory','name'=>'Urine Dipstick',          'sku'=>'LB-004','unit'=>'strip', 'qty'=>500,'min'=>200,'pp'=>3.00,  'sp'=>7.00],
            ['dept'=>'laboratory','name'=>'Thyroid TSH Kit',         'sku'=>'LB-005','unit'=>'kit',   'qty'=>2,  'min'=>6,  'pp'=>5800.00,'sp'=>11500.00],
            ['dept'=>'laboratory','name'=>'Lipid Panel Reagent',     'sku'=>'LB-006','unit'=>'kit',   'qty'=>4,  'min'=>8,  'pp'=>3800.00,'sp'=>7500.00],
            ['dept'=>'laboratory','name'=>'Blood Culture Bottles',   'sku'=>'LB-007','unit'=>'bottle','qty'=>30, 'min'=>20, 'pp'=>180.00,'sp'=>380.00],
            ['dept'=>'laboratory','name'=>'Malaria RDT Kit',         'sku'=>'LB-008','unit'=>'kit',   'qty'=>0,  'min'=>15, 'pp'=>280.00,'sp'=>580.00],
            ['dept'=>'laboratory','name'=>'Hepatitis B Ag Kit',      'sku'=>'LB-009','unit'=>'kit',   'qty'=>6,  'min'=>10, 'pp'=>1200.00,'sp'=>2400.00],
            ['dept'=>'laboratory','name'=>'Dengue NS1 Ag Kit',       'sku'=>'LB-010','unit'=>'kit',   'qty'=>0,  'min'=>12, 'pp'=>950.00,'sp'=>1900.00],
            // RADIOLOGY
            ['dept'=>'radiology','name'=>'X-Ray Film 14x17',         'sku'=>'RD-001','unit'=>'sheet', 'qty'=>50, 'min'=>100,'pp'=>120.00,'sp'=>250.00],
            ['dept'=>'radiology','name'=>'Contrast Dye (Iohexol)',   'sku'=>'RD-002','unit'=>'vial',  'qty'=>8,  'min'=>20, 'pp'=>850.00,'sp'=>1800.00],
            ['dept'=>'radiology','name'=>'Ultrasound Gel',           'sku'=>'RD-003','unit'=>'litre', 'qty'=>6,  'min'=>10, 'pp'=>280.00,'sp'=>0.00],
            ['dept'=>'radiology','name'=>'Developer Solution',        'sku'=>'RD-004','unit'=>'litre', 'qty'=>15, 'min'=>10, 'pp'=>450.00,'sp'=>0.00],
            ['dept'=>'radiology','name'=>'Fixer Solution',           'sku'=>'RD-005','unit'=>'litre', 'qty'=>0,  'min'=>10, 'pp'=>380.00,'sp'=>0.00],
        ];

        foreach ($catalog as $item) {
            $id = InventoryItem::updateOrCreate(
                ['sku' => $item['sku']],
                [
                    'department'            => $item['dept'],
                    'name'                  => $item['name'],
                    'unit'                  => $item['unit'],
                    'quantity_in_stock'     => $item['qty'],
                    'minimum_stock_level'   => $item['min'],
                    'purchase_price'        => $item['pp'],
                    'selling_price'         => $item['sp'],
                    'weighted_avg_cost'     => $item['pp'],
                    'requires_prescription' => $item['dept'] === 'pharmacy',
                    'is_active'             => true,
                ]
            )->id;
            $this->itemIds[$item['sku']] = $id;
        }

        $this->command->info('     30 inventory items seeded.');
    }

    // ── expenses ──────────────────────────────────────────────────────────

    private function seedExpenses(): void
    {
        $this->command->info('  → Expenses (365 days)…');

        $now  = Carbon::now();
        $rows = [];

        for ($d = 364; $d >= 0; $d--) {
            $date = $now->copy()->subDays($d);
            $arc  = $this->arc($d);

            // Arc B (Ramadan): higher procurement costs
            // Arc C: overstock correction — more procurement expenses
            // Arc D: low expenses (rogue actor hiding spend)
            $baseCount = match($arc) {
                'B' => rand(3, 6),
                'C' => rand(4, 7),
                'D' => rand(1, 2),
                default => rand(2, 4),
            };

            for ($i = 0; $i < $baseCount; $i++) {
                $cat = rand(0, 2) === 0 ? 'procurement' : 'variable';
                $dept = $this->randomDept();
                $dateStr = $date->format('Y-m-d');
                $rows[] = [
                    'department'   => $dept,
                    'description'  => $this->expenseDesc($cat, $arc),
                    'cost'         => $this->expenseCost($cat, $arc),
                    'created_by'   => $this->ownerId,
                    'category'     => $cat,
                    'expense_date' => $dateStr,
                    'created_at'   => $dateStr . ' ' . rand(8, 17) . ':' . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00',
                    'updated_at'   => $dateStr . ' ' . rand(8, 17) . ':' . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00',
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('expenses')->insert($chunk);
        }

        $this->command->info('     ' . count($rows) . ' expense entries seeded.');
    }

    // ── audit logs ────────────────────────────────────────────────────────

    private function seedAuditLogs(): void
    {
        $this->command->info('  → Audit logs (365 days — ~20,000 events, inserting in batches)…');

        $events   = $this->buildTimeline();
        $total    = 0;
        $batch    = [];
        $prevHash = (string) (DB::table('audit_logs')->latest('id')->value('row_hash') ?? '');

        foreach ($events as $ev) {
            $ip     = '192.168.' . rand(1, 5) . '.' . rand(10, 250);
            $before = isset($ev['before']) ? json_encode($ev['before']) : null;
            $after  = isset($ev['after'])  ? json_encode($ev['after'])  : null;

            $canonical = [
                'action'         => $ev['action'],
                'after_state'    => $ev['after']  ?? null,
                'auditable_id'   => $ev['id'],
                'auditable_type' => $ev['type'],
                'before_state'   => $ev['before'] ?? null,
                'created_at'     => $ev['ts'],
                'ip_address'     => $ip,
                'prev_hash'      => $prevHash,
                'session_id'     => null,
                'user_agent'     => null,
                'user_id'        => $ev['user_id'],
            ];
            ksort($canonical);
            $rowHash = hash('sha256', $prevHash . '|' . json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $batch[] = [
                'user_id'        => $ev['user_id'],
                'action'         => $ev['action'],
                'auditable_type' => $ev['type'],
                'auditable_id'   => $ev['id'],
                'before_state'   => $before,
                'after_state'    => $after,
                'ip_address'     => $ip,
                'user_agent'     => null,
                'session_id'     => null,
                'prev_hash'      => $prevHash,
                'row_hash'       => $rowHash,
                'created_at'     => $ev['ts'],
            ];

            $prevHash = $rowHash;
            $total++;

            if (count($batch) >= 300) {
                DB::table('audit_logs')->insert($batch);
                $batch = [];
                $this->command->info("     {$total} events…");
            }
        }

        if ($batch) {
            DB::table('audit_logs')->insert($batch);
        }

        $this->command->info("     {$total} audit log entries seeded.");
    }

    private function buildTimeline(): array
    {
        $events = [];
        $now    = Carbon::now();

        for ($d = 364; $d >= 0; $d--) {
            $date = $now->copy()->subDays($d);
            $arc  = $this->arc($d);
            $isWeekend = in_array($date->dayOfWeek, [0, 6]);

            // ── Invoice volume by arc ─────────────────────────────────────
            $invoiceCount = match($arc) {
                'A' => $isWeekend ? rand(4, 10) : rand(10, 22),    // Baseline
                'B' => $isWeekend ? rand(12, 25) : rand(30, 55),   // Ramadan surge
                'C' => $isWeekend ? rand(3, 8)  : rand(8, 18),     // Post-Ramadan dip
                'D' => $isWeekend ? rand(5, 12) : rand(12, 28),    // Rogue actor — normal volume
                'E' => $isWeekend ? rand(6, 14) : rand(14, 26),    // Recovery
                default => rand(10, 20),
            };

            // Inject deliberate anomaly days
            if (in_array($d, [350, 300, 240])) $invoiceCount = rand(60, 85);  // Revenue spikes
            if (in_array($d, [320, 270])) $invoiceCount = rand(1, 4);          // Revenue crashes (public holidays)
            if (in_array($d, [100, 80])) $invoiceCount = rand(55, 70);        // Arc D spike (rogue + high volume)

            for ($i = 0; $i < $invoiceCount; $i++) {
                $ts = $date->copy()->setTime(rand(8, 19), rand(0, 59), rand(0, 59));
                $invId = rand(1000, 99999);
                $dept  = $this->randomDept();
                $amount = $this->invoiceAmount($arc, $dept);

                $events[] = ['action'=>'invoice.created','type'=>'App\Models\Invoice','id'=>$invId,
                    'user_id'=>$this->receptionistId,'after'=>['department'=>$dept,'net_amount'=>$amount],
                    'ts'=>$ts->format('Y-m-d H:i:s')];

                if (rand(1, 10) <= ($arc === 'C' ? 6 : 8)) {
                    $events[] = ['action'=>'invoice.paid','type'=>'App\Models\Invoice','id'=>$invId,
                        'user_id'=>$this->receptionistId,
                        'before'=>['status'=>'unpaid'],'after'=>['status'=>'paid','net_amount'=>$amount],
                        'ts'=>$ts->copy()->addMinutes(rand(5, 90))->format('Y-m-d H:i:s')];
                }
            }

            // ── Discount requests by arc ──────────────────────────────────
            // Arc A: Normal 1-3/day from receptionist
            // Arc B: Elevated — charity/Ramadan concessions 5-12/day
            // Arc C: Very high — overstocking discounts 8-20/day
            // Arc D: Rogue extreme — 15-30/day from rogueUser in last 60 days
            // Arc E: Being investigated, drops back to 2-6/day
            $discountBase = match($arc) {
                'A' => rand(1, 3),
                'B' => rand(5, 12),
                'C' => rand(8, 20),
                'D' => rand(15, 30),
                'E' => rand(2, 6),
                default => rand(2, 4),
            };

            $discountActor = in_array($arc, ['C', 'D']) ? $this->rogueUserId : $this->receptionistId;

            for ($j = 0; $j < $discountBase; $j++) {
                $ts = $date->copy()->setTime(rand(9, 17), rand(0, 59), rand(0, 59));
                $events[] = ['action'=>'invoice.discount.requested','type'=>'App\Models\Invoice',
                    'id'=>rand(1000, 99999),'user_id'=>$discountActor,
                    'after'=>['discount_amount'=>rand(100, 3000),'discount_reason'=>$this->discountReason($arc)],
                    'ts'=>$ts->format('Y-m-d H:i:s')];

                // Approval rate drops in Arc D (suspicious, owner starting to question)
                $approvalRate = match($arc) { 'D' => 4, 'B' => 9, 'C' => 7, default => 8 };
                if (rand(1, 10) <= $approvalRate) {
                    $events[] = ['action'=>'invoice.discount.approved','type'=>'App\Models\Invoice',
                        'id'=>rand(1000, 99999),'user_id'=>$this->ownerId,
                        'after'=>['approved'=>true],'ts'=>$ts->copy()->addMinutes(rand(10, 180))->format('Y-m-d H:i:s')];
                }
            }

            // ── FBR events ────────────────────────────────────────────────
            // Arc D: higher FBR failure rate (rogue actor creating irregular invoices)
            $fbrCount  = max(1, intval($invoiceCount * 0.9));
            $failRate  = match($arc) { 'D' => 0.15, 'B' => 0.03, default => 0.05 };
            $fbrFailed = (int) ceil($fbrCount * $failRate);

            for ($k = 0; $k < $fbrCount; $k++) {
                $ts = $date->copy()->setTime(rand(8, 19), rand(0, 59), rand(0, 59));
                $events[] = ['action'=>'fbr.invoice.submitted','type'=>'App\Models\Invoice',
                    'id'=>rand(1000, 99999),'user_id'=>$this->receptionistId,
                    'after'=>['fbr_invoice_seq'=>'FBR-'.rand(100000,999999)],
                    'ts'=>$ts->format('Y-m-d H:i:s')];

                $isFail = $k < $fbrFailed;
                $events[] = ['action'=>$isFail ? 'fbr.invoice.failed' : 'fbr.invoice.success',
                    'type'=>'App\Models\Invoice','id'=>rand(1000, 99999),'user_id'=>$this->receptionistId,
                    'after'=>$isFail ? ['error'=>'FBR timeout','retry'=>true] : ['qr_code'=>'QR-'.rand(100000,999999)],
                    'ts'=>$ts->copy()->addSeconds(rand(2, 20))->format('Y-m-d H:i:s')];
            }

            // ── Consultations ─────────────────────────────────────────────
            $consults = $isWeekend ? rand(2, 6) : rand(5, 18);
            for ($c = 0; $c < $consults; $c++) {
                $ts  = $date->copy()->setTime(rand(9, 16), rand(0, 59), rand(0, 59));
                $doc = rand(0, 1) ? $this->doctorId : $this->doctor2Id;
                $events[] = ['action'=>'consultation.completed','type'=>'App\Models\Patient',
                    'id'=>rand(1, 80),'user_id'=>$doc,
                    'after'=>['duration_minutes'=>rand(10,45),'diagnosis_coded'=>true],
                    'ts'=>$ts->format('Y-m-d H:i:s')];
            }

            // ── PHI access patterns ───────────────────────────────────────
            // Normal staff: 2-10 patient views/day
            foreach ([$this->doctorId, $this->doctor2Id, $this->labTechId, $this->triageId] as $viewer) {
                $viewCount = rand(2, 10);
                for ($v = 0; $v < $viewCount; $v++) {
                    $ts = $date->copy()->setTime(rand(8, 17), rand(0, 59), rand(0, 59));
                    $events[] = ['action'=>'patient.viewed','type'=>'App\Models\Patient',
                        'id'=>rand(1, 80),'user_id'=>$viewer,'ts'=>$ts->format('Y-m-d H:i:s')];
                }
            }

            // Rogue actor PHI anomaly — Arc D only, bulk access in one hour
            // Scenario: receptionist exporting patient list for external party
            if ($arc === 'D' && rand(0, 1)) {
                $anomalyHour = rand(10, 14);
                $bulkCount   = rand(28, 45); // Far exceeds the 20/hour threshold
                for ($a = 0; $a < $bulkCount; $a++) {
                    $ts = $date->copy()->setTime($anomalyHour, rand(0, 59), rand(0, 59));
                    $events[] = ['action'=>'patient.viewed','type'=>'App\Models\Patient',
                        'id'=>rand(1, 80),'user_id'=>$this->rogueUserId,
                        'ts'=>$ts->format('Y-m-d H:i:s')];
                }
            }

            // ── Monthly payout events ─────────────────────────────────────
            if ($d % 30 === 0) {
                $events[] = ['action'=>'payout.confirmed','type'=>'App\Models\User',
                    'id'=>$this->doctorId,'user_id'=>$this->ownerId,
                    'after'=>['amount'=>rand(50000, 130000),'period'=>'30d'],
                    'ts'=>$date->copy()->setTime(16, 0, 0)->format('Y-m-d H:i:s')];
            }

            // ── Arc E: compliance / investigation events ──────────────────
            if ($arc === 'E') {
                if ($d % 7 === 0) {
                    $events[] = ['action'=>'compliance.audit.initiated','type'=>'App\Models\User',
                        'id'=>$this->ownerId,'user_id'=>$this->ownerId,
                        'after'=>['scope'=>'discount_review','initiated_by'=>'owner'],
                        'ts'=>$date->copy()->setTime(9, 0, 0)->format('Y-m-d H:i:s')];
                }
                if ($d === 30) {
                    $events[] = ['action'=>'user.access.restricted','type'=>'App\Models\User',
                        'id'=>$this->rogueUserId,'user_id'=>$this->ownerId,
                        'before'=>['is_active'=>true],'after'=>['is_active'=>false,'reason'=>'pending investigation'],
                        'ts'=>$date->copy()->setTime(11, 0, 0)->format('Y-m-d H:i:s')];
                }
            }

            // ── System events ─────────────────────────────────────────────
            if (rand(0, 9) === 0) {
                $events[] = ['action'=>'flag.toggled','type'=>'App\Models\PlatformSetting',
                    'id'=>1,'user_id'=>$this->ownerId,
                    'after'=>['flag'=>'ai.sidecar.enabled','value'=>rand(0,1) === 1],
                    'ts'=>$date->copy()->setTime(8, 30, 0)->format('Y-m-d H:i:s')];
            }
        }

        usort($events, fn($a, $b) => strcmp($a['ts'], $b['ts']));
        return $events;
    }

    // ── AI invocations ────────────────────────────────────────────────────

    private function seedAiInvocations(): void
    {
        $this->command->info('  → AI invocations (90 days)…');

        $endpoints = ['/v1/admin/analyse','/v1/ops/analyse','/v1/compliance/analyse','/v1/consult'];
        $rows     = [];
        $prevHash = (string) (DB::table('ai_invocations')->latest('id')->value('row_hash') ?? '');
        $now      = Carbon::now();

        for ($d = 89; $d >= 0; $d--) {
            $date  = $now->copy()->subDays($d);
            $count = rand(5, 15);

            for ($i = 0; $i < $count; $i++) {
                $endpoint   = $endpoints[array_rand($endpoints)];
                $promptHash = bin2hex(random_bytes(32));
                $latencyMs  = rand(380, 4200);
                $outcome    = rand(1, 30) === 1 ? 'error' : 'ok';
                $ts         = $date->copy()->setTime(rand(8, 22), rand(0, 59), rand(0, 59))->format('Y-m-d H:i:s');
                $rowHash    = hash('sha256', $prevHash . '|' . $endpoint . '|' . $promptHash . '|' . $ts);

                $rows[] = [
                    'user_id'           => $this->ownerId,
                    'case_token'        => bin2hex(random_bytes(32)),
                    'endpoint'          => substr($endpoint, 0, 64),
                    'prompt_hash'       => $promptHash,
                    'retrieval_doc_ids' => json_encode([]),
                    'model_id'          => $endpoint === '/v1/consult' ? 'medgemma:v1' : 'llama3.2:3b:etcslv:v1',
                    'latency_ms'        => $latencyMs,
                    'outcome'           => $outcome,
                    'prev_hash'         => $prevHash,
                    'row_hash'          => $rowHash,
                    'created_at'        => $ts,
                ];
                $prevHash = $rowHash;
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('ai_invocations')->insert($chunk);
        }

        $this->command->info('     ' . count($rows) . ' AI invocation records seeded.');
    }

    // ── SOC2 evidence ─────────────────────────────────────────────────────

    private function seedSoc2Evidence(): void
    {
        $dir = storage_path('app/soc2');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $zip = new \ZipArchive();
        $path = $dir . '/evidence_' . now()->format('Y_m_d') . '.zip';
        if ($zip->open($path, \ZipArchive::CREATE) === true) {
            $zip->addFromString('README.txt', 'SOC2 bundle — Phase8YearDataSeeder ' . now()->toIso8601String());
            $zip->addFromString('flag_snapshot.json', json_encode(['generated_at' => now()->toIso8601String()]));
            $zip->close();
        }
        $this->command->info('  → SOC2 evidence zip seeded.');
    }

    // ── Arc helpers ───────────────────────────────────────────────────────

    /** Map days-ago to story arc label */
    private function arc(int $daysAgo): string
    {
        return match(true) {
            $daysAgo >= 270 => 'A',   // Baseline steady-state
            $daysAgo >= 180 => 'B',   // Ramadan / seasonal surge
            $daysAgo >= 120 => 'C',   // Post-Ramadan correction + discount surge
            $daysAgo >= 60  => 'D',   // Rogue actor arc
            default         => 'E',   // Recovery + audit
        };
    }

    private function invoiceAmount(string $arc, string $dept): int
    {
        $base = match($dept) {
            'consultation' => [800, 3000],
            'laboratory'   => [500, 4000],
            'radiology'    => [2000, 12000],
            'pharmacy'     => [200, 2500],
            default        => [500, 3000],
        };
        $multiplier = match($arc) { 'B' => 1.3, 'C' => 0.85, default => 1.0 };
        return (int) (rand($base[0], $base[1]) * $multiplier);
    }

    private function discountReason(string $arc): string
    {
        return match($arc) {
            'B' => collect(['Ramadan charity concession','Zakat beneficiary','Underprivileged patient','Community service'])->random(),
            'C' => collect(['Loyalty discount','Referral reward','Bulk visit discount','Staff family'])->random(),
            'D' => collect(['Patient request','Manager approved','VIP patient','Special case','Verbal approval'])->random(),
            default => collect(['Patient hardship','Senior citizen','Returning patient'])->random(),
        };
    }

    private function expenseDesc(string $cat, string $arc): string
    {
        $variable   = ['Staff refreshments','Office supplies','Utility bill','Cleaning supplies','Printer cartridges','Stationery','Tea/coffee','Minor repairs','Courier charges'];
        $procurement = [
            'A' => ['Standard reagent restock','Monthly pharmacy order','X-ray supplies'],
            'B' => ['Ramadan volume restock','Emergency reagent order','High-demand med bulk buy','Critical supply top-up'],
            'C' => ['Overstock correction write-off','Slow-moving inventory','Excess supply disposal'],
            'D' => ['Procurement irregularity','Off-schedule supply order','Unbudgeted equipment'],
            'E' => ['Post-audit reagent restock','Compliance-approved procurement','Verified vendor order'],
        ];

        if ($cat === 'procurement') {
            $descs = $procurement[$arc] ?? $procurement['A'];
            return $descs[array_rand($descs)];
        }
        return $variable[array_rand($variable)];
    }

    private function expenseCost(string $cat, string $arc): float
    {
        [$lo, $hi] = match(true) {
            $cat === 'procurement' && $arc === 'B' => [8000, 35000],
            $cat === 'procurement' && $arc === 'C' => [1000, 5000],
            $cat === 'procurement' => [3000, 15000],
            default => [200, 6000],
        };
        return round(rand($lo, $hi) + rand(0, 99) / 100, 2);
    }

    private function randomDept(): string
    {
        return ['consultation','laboratory','radiology','pharmacy'][rand(0,3)];
    }
}
