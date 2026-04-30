<?php

namespace App\Console\Commands;

use App\Models\PlatformSetting;
use App\Services\AiSidecarClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Generates knowledge documents from live DB patterns and indexes them into RAGFlow.
 * Run after seeding or after significant operational data accumulates.
 *
 * Usage:
 *   php artisan ragflow:index-knowledge            # push all three corpora
 *   php artisan ragflow:index-knowledge --dry-run  # print without sending
 *   php artisan ragflow:index-knowledge --dataset=admin  # single dataset
 */
class RagflowIndexKnowledge extends Command
{
    protected $signature = 'ragflow:index-knowledge
                            {--dry-run : Print corpus documents without sending to RAGFlow}
                            {--dataset= : Only index one dataset: admin, ops, or compliance}';

    protected $description = 'Index clinic knowledge (policies + DB-derived patterns) into RAGFlow for admin/ops/compliance AI personas';

    public function handle(AiSidecarClient $client): int
    {
        if (!PlatformSetting::isEnabled('ai.ragflow.enabled')) {
            $this->info('ragflow:index-knowledge skipped — ai.ragflow.enabled is OFF.');
            return self::SUCCESS;
        }

        $only = $this->option('dataset');

        if (!$only || $only === 'admin') {
            $this->indexDataset($client, 'admin', $this->buildAdminCorpus());
        }
        if (!$only || $only === 'ops') {
            $this->indexDataset($client, 'ops', $this->buildOpsCorpus());
        }
        if (!$only || $only === 'compliance') {
            $this->indexDataset($client, 'compliance', $this->buildComplianceCorpus());
        }

        return self::SUCCESS;
    }

    // ── Admin corpus ─────────────────────────────────────────────────────────

    private function buildAdminCorpus(): string
    {
        $docs = [];

        // Static policy documents
        $docs[] = $this->doc('Discount Approval Policy', <<<'TEXT'
Discounts on invoices must follow these rules:
- Discounts ≤ 10%: any doctor or receptionist may apply.
- Discounts 11–25%: requires department head approval before finalising the invoice.
- Discounts > 25%: requires Owner sign-off. Applying without approval is a policy violation.
- Discounts on lab or radiology invoices are never permitted without Owner approval.
- Suspicious patterns: same doctor applying max discounts repeatedly to the same patient warrants review.
TEXT);

        $docs[] = $this->doc('Doctor Payout Rules', <<<'TEXT'
Doctor payouts are calculated monthly:
- Consultation fee: 60% of the consultation invoice value goes to the consulting doctor.
- Procedure fee: 50% of any procedure invoice value.
- Lab referral: flat PKR 200 per lab referral that results in a paid invoice.
- Payouts are processed only after the invoice status is "paid".
- Disputed invoices are excluded from the current payout cycle and carried forward.
- Payouts are reviewed by Owner before release. Any variance > PKR 5,000 from previous month requires explanation.
TEXT);

        $docs[] = $this->doc('Revenue Anomaly Thresholds', <<<'TEXT'
Flag for review when:
- Daily revenue drops > 40% compared to the 7-day rolling average.
- A single invoice exceeds PKR 50,000 (unusually large, verify services rendered).
- Invoice count < 3 on a weekday (possible system or recording issue).
- Monthly revenue drops > 25% vs the prior month without a known cause (holiday, shutdown).
TEXT);

        // Dynamic: top expense categories
        $expensePatterns = DB::table('expenses')
            ->selectRaw('category, COUNT(*) as cnt, SUM(amount) as total, AVG(amount) as avg_amount')
            ->whereNotNull('category')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        if ($expensePatterns->isNotEmpty()) {
            $rows = $expensePatterns->map(fn ($r) =>
                "- {$r->category}: {$r->cnt} transactions, total PKR " . number_format($r->total) .
                ", avg PKR " . number_format($r->avg_amount)
            )->join("\n");

            $docs[] = $this->doc('Expense Category Patterns (from clinic data)', <<<TEXT
Observed expense distribution across all recorded periods:
{$rows}

Anomaly signals: any single expense in a category that exceeds 3× the category average warrants approval.
TEXT);
        }

        // Dynamic: discount frequency by doctor
        $discountStats = DB::table('invoices')
            ->join('users', 'invoices.doctor_id', '=', 'users.id')
            ->selectRaw('users.name as doctor, COUNT(*) as total_invoices,
                         SUM(CASE WHEN invoices.discount > 0 THEN 1 ELSE 0 END) as discounted,
                         AVG(CASE WHEN invoices.discount > 0 THEN invoices.discount ELSE NULL END) as avg_discount')
            ->whereNotNull('invoices.doctor_id')
            ->groupBy('users.id', 'users.name')
            ->having('total_invoices', '>', 2)
            ->orderByDesc('discounted')
            ->limit(8)
            ->get();

        if ($discountStats->isNotEmpty()) {
            $rows = $discountStats->map(fn ($r) =>
                "- {$r->doctor}: {$r->total_invoices} invoices, {$r->discounted} discounted" .
                ($r->avg_discount ? ", avg discount " . round($r->avg_discount, 1) . "%" : "")
            )->join("\n");

            $docs[] = $this->doc('Discount Usage by Doctor (from clinic data)', <<<TEXT
Historical discount application rates per doctor:
{$rows}

Baseline for anomaly detection: a doctor applying discounts on > 50% of their invoices should be reviewed.
TEXT);
        }

        return implode("\n\n" . str_repeat('─', 60) . "\n\n", $docs);
    }

    // ── Ops corpus ────────────────────────────────────────────────────────────

    private function buildOpsCorpus(): string
    {
        $docs = [];

        $docs[] = $this->doc('Stock Management Policy', <<<'TEXT'
Inventory reorder rules:
- Critical threshold: when quantity_in_stock = 0, raise emergency procurement immediately.
- Warning threshold: when quantity_in_stock ≤ minimum_stock_level, raise standard procurement within 48 hours.
- Pharmacy items: minimum stock must cover at least 3 days of average dispensing volume.
- Lab reagents: minimum stock must cover at least 5 working days of test volume.
- Procurement requests require department head approval before the order is placed.
- Approved requests must be received and stock updated within 7 days or the request is escalated.
TEXT);

        $docs[] = $this->doc('Queue and Appointment Management', <<<'TEXT'
Operational targets:
- Patient wait time from check-in to triage: target < 15 minutes.
- Triage to doctor consultation: target < 30 minutes for normal priority; < 10 minutes for urgent.
- Lab result turnaround: target < 2 hours for routine; < 30 minutes for stat.
- Radiology report turnaround: target < 4 hours.
- No-show rate > 20% in a week requires review of the booking confirmation process.
- Appointment slots should not exceed 80% of doctor capacity to allow walk-ins.
TEXT);

        // Dynamic: current stock status
        $criticalItems = DB::table('inventory_items')
            ->where('is_active', true)
            ->whereRaw('quantity_in_stock = 0')
            ->get(['name', 'category', 'unit']);

        $warningItems = DB::table('inventory_items')
            ->where('is_active', true)
            ->whereRaw('quantity_in_stock > 0 AND quantity_in_stock <= minimum_stock_level')
            ->get(['name', 'category', 'quantity_in_stock', 'minimum_stock_level', 'unit']);

        $stockSection = "Current inventory status snapshot:\n";
        if ($criticalItems->isNotEmpty()) {
            $stockSection .= "\nCRITICAL (out of stock):\n";
            foreach ($criticalItems as $item) {
                $stockSection .= "- {$item->name} ({$item->category})\n";
            }
        }
        if ($warningItems->isNotEmpty()) {
            $stockSection .= "\nWARNING (below minimum):\n";
            foreach ($warningItems as $item) {
                $stockSection .= "- {$item->name}: {$item->quantity_in_stock} {$item->unit} (min: {$item->minimum_stock_level})\n";
            }
        }
        if ($criticalItems->isEmpty() && $warningItems->isEmpty()) {
            $stockSection .= "All items above minimum stock levels.\n";
        }

        $docs[] = $this->doc('Current Stock Snapshot', $stockSection);

        // Dynamic: procurement patterns
        $procStats = DB::table('procurement_requests')
            ->selectRaw('status, COUNT(*) as cnt, AVG(DATEDIFF(updated_at, created_at)) as avg_days')
            ->groupBy('status')
            ->get();

        if ($procStats->isNotEmpty()) {
            $rows = $procStats->map(fn ($r) =>
                "- {$r->status}: {$r->cnt} requests, avg " . round($r->avg_days ?? 0, 1) . " days to resolution"
            )->join("\n");

            $docs[] = $this->doc('Procurement Request Patterns', <<<TEXT
Historical procurement request resolution times:
{$rows}

Target: all approved requests fulfilled within 7 days. Pending > 14 days should be escalated.
TEXT);
        }

        return implode("\n\n" . str_repeat('─', 60) . "\n\n", $docs);
    }

    // ── Compliance corpus ─────────────────────────────────────────────────────

    private function buildComplianceCorpus(): string
    {
        $docs = [];

        $docs[] = $this->doc('PHI Protection Policy', <<<'TEXT'
Protected Health Information (PHI) rules:
- Patient names, CNICs, phone numbers, and dates of birth must never appear in AI model inputs or outputs.
- All AI analyses use anonymized case tokens (HMAC-SHA256 of patient_id + secret).
- Audit logs record every access to patient records; bulk reads (> 50 records without a clinical reason) are flagged.
- PHI must not be stored in plain text in any log, cache, or AI invocation record.
- Any staff member accessing records outside their department scope is a policy violation.
TEXT);

        $docs[] = $this->doc('Audit Chain Integrity Rules', <<<'TEXT'
The audit_logs table is hash-chained (SHA-256). Rules:
- Each row carries prev_hash (hash of the prior row) and row_hash (hash of the current row content).
- An UPDATE or DELETE trigger at DB level prevents modification after insertion.
- Chain verification must pass after every deployment: php artisan audit:verify-chain
- Any gap or mismatch in the chain is a critical incident requiring immediate investigation.
- Chain breaks must be reported to the Owner and documented within 24 hours.
TEXT);

        $docs[] = $this->doc('Feature Flag Governance', <<<'TEXT'
AI and admin feature flags are managed through platform_settings (provider=feature_flag).
Default state for all AI flags: OFF.
Enabling a flag in production requires:
1. Owner review and explicit activation via Platform Settings.
2. Documentation of the activation reason in the change log.
3. Monitoring for the first 24 hours after activation.
Disabling a flag is immediate and does not require approval.
TEXT);

        // Dynamic: audit event distribution
        $auditSummary = DB::table('audit_logs')
            ->selectRaw('action, COUNT(*) as cnt')
            ->whereDate('created_at', '>=', now()->subDays(30))
            ->groupBy('action')
            ->orderByDesc('cnt')
            ->limit(15)
            ->get();

        if ($auditSummary->isNotEmpty()) {
            $rows = $auditSummary->map(fn ($r) => "- {$r->action}: {$r->cnt} events")->join("\n");
            $docs[] = $this->doc('Audit Event Distribution (last 30 days)', <<<TEXT
Observed audit log events in the past 30 days:
{$rows}

Baseline for anomaly detection: events > 3× the 30-day average for that action type warrant investigation.
TEXT);
        }

        // Dynamic: AI invocation stats
        $aiStats = DB::table('ai_invocations')
            ->selectRaw('endpoint, COUNT(*) as cnt, AVG(latency_ms) as avg_latency,
                         SUM(CASE WHEN status = "error" THEN 1 ELSE 0 END) as errors')
            ->whereDate('created_at', '>=', now()->subDays(30))
            ->groupBy('endpoint')
            ->get();

        if ($aiStats->isNotEmpty()) {
            $rows = $aiStats->map(fn ($r) =>
                "- {$r->endpoint}: {$r->cnt} calls, avg " . round($r->avg_latency ?? 0) . "ms, {$r->errors} errors"
            )->join("\n");

            $docs[] = $this->doc('AI Invocation Stats (last 30 days)', <<<TEXT
AI sidecar usage summary:
{$rows}

Error rate > 5% on any endpoint requires investigation. High latency (> 30s avg) indicates model or network issues.
TEXT);
        }

        return implode("\n\n" . str_repeat('─', 60) . "\n\n", $docs);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function doc(string $title, string $body): string
    {
        return "# {$title}\n\n" . trim($body);
    }

    private function indexDataset(AiSidecarClient $client, string $dataset, string $corpus): void
    {
        $wordCount = str_word_count($corpus);
        $this->info("Building {$dataset} corpus ({$wordCount} words)…");

        if ($this->option('dry-run')) {
            $this->line($corpus);
            $this->line('');
            return;
        }

        try {
            $client->ragIngestContent($corpus, $dataset);
            $this->info("  ✓ {$dataset} indexed into RAGFlow.");
        } catch (\Exception $e) {
            $this->error("  ✗ {$dataset} failed: " . $e->getMessage());
        }
    }
}
