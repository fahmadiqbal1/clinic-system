<?php

namespace App\Console\Commands;

use App\Mail\MorningBriefMail;
use App\Models\AiActionRequest;
use App\Models\InventoryItem;
use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the 07:00 Morning Brief email to all active Owner accounts.
 *
 * Aggregates overnight activity:
 *   - Unresolved AI action requests (pending_approval / pending)
 *   - Critical & warning inventory items
 *   - Pending procurement requests
 *   - Revenue summary (invoices issued since midnight)
 */
class SendMorningBriefCommand extends Command
{
    protected $signature   = 'ai:morning-brief';
    protected $description = 'Send the daily morning-brief email to all Owner accounts.';

    public function handle(): int
    {
        $owners = User::role('Owner')->whereNotNull('email')->get();

        if ($owners->isEmpty()) {
            $this->info('No active Owner accounts — skipping.');
            return self::SUCCESS;
        }

        $brief = $this->compileBrief();

        foreach ($owners as $owner) {
            try {
                Mail::to($owner->email)->send(new MorningBriefMail($owner, $brief));
            } catch (\Throwable $e) {
                Log::warning('morning_brief: mail failed', ['user_id' => $owner->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Morning brief sent to {$owners->count()} owner(s).");
        return self::SUCCESS;
    }

    private function compileBrief(): array
    {
        $yesterday = now()->subDay();
        $midnight  = now()->startOfDay();

        // Pending AI action requests
        $pendingAiRequests = AiActionRequest::where('status', 'pending')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['requested_by_source', 'proposed_action', 'created_at']);

        // Critical stock (qty = 0)
        $criticalStock = InventoryItem::where('is_active', true)
            ->where('quantity_in_stock', 0)
            ->orderBy('name')
            ->limit(10)
            ->get(['name', 'quantity_in_stock', 'minimum_stock_level']);

        // Warning stock (qty > 0 but ≤ min level)
        $warningStock = InventoryItem::where('is_active', true)
            ->where('quantity_in_stock', '>', 0)
            ->whereColumn('quantity_in_stock', '<=', 'minimum_stock_level')
            ->orderBy('name')
            ->limit(10)
            ->get(['name', 'quantity_in_stock', 'minimum_stock_level']);

        // Pending procurement requests
        $pendingProcurement = ProcurementRequest::where('status', 'pending_approval')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['department', 'created_at', 'notes']);

        // Revenue since midnight (invoices finalized today)
        $revenueToday = DB::table('invoices')
            ->where('created_at', '>=', $midnight)
            ->whereIn('status', ['paid', 'partially_paid'])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total')
            ->first();

        // Overnight anomalies — admin AI action requests from last 24h
        $overnightAnomalies = AiActionRequest::where('requested_by_source', 'admin_ai')
            ->where('created_at', '>=', $yesterday)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['proposed_action', 'proposed_payload', 'created_at']);

        return [
            'date'                => now()->format('l, d F Y'),
            'pending_ai_requests' => $pendingAiRequests,
            'critical_stock'      => $criticalStock,
            'warning_stock'       => $warningStock,
            'pending_procurement' => $pendingProcurement,
            'revenue_today'       => [
                'count' => $revenueToday?->count ?? 0,
                'total' => number_format((float) ($revenueToday?->total ?? 0), 2),
            ],
            'overnight_anomalies' => $overnightAnomalies,
        ];
    }
}
