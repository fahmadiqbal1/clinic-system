<?php

namespace App\Console\Commands;

use App\Models\AiActionRequest;
use App\Models\AuditLog;
use App\Models\ProcurementRequest;
use App\Models\User;
use App\Notifications\ProcurementStatusUpdated;
use Illuminate\Console\Command;

class ProcurementCheckReceiptsCommand extends Command
{
    protected $signature   = 'procurement:check-receipts';
    protected $description = 'Flag inventory procurement requests where receipt is overdue (>48 hours past approval).';

    public function handle(): int
    {
        $overdue = ProcurementRequest::where('type', ProcurementRequest::TYPE_INVENTORY)
            ->where('status', 'approved')
            ->whereNull('received_at')
            ->whereNotNull('receipt_deadline_at')
            ->where('receipt_deadline_at', '<', now())
            ->where(function ($q): void {
                $q->whereNull('receipt_overdue_notified_at')
                  ->orWhere('receipt_overdue_notified_at', '<', now()->subHours(24));
            })
            ->with('requester')
            ->get();

        if ($overdue->isEmpty()) {
            $this->info('No overdue receipts found.');
            return self::SUCCESS;
        }

        $owners = User::role('Owner')->get();

        foreach ($overdue as $pr) {
            $hoursOverdue = (int) now()->diffInHours($pr->receipt_deadline_at);

            AiActionRequest::create([
                'requested_by_source' => 'ops_ai',
                'target_type'         => 'ProcurementRequest',
                'target_id'           => $pr->id,
                'proposed_action'     => 'flag_unrecorded_receipt',
                'proposed_payload'    => [
                    'hours_overdue' => $hoursOverdue,
                    'dept'          => $pr->department,
                    'requester'     => $pr->requester?->name,
                ],
                'status'     => 'pending',
                'created_at' => now(),
            ]);

            $overdueMessage = "Receipt overdue #{$pr->id} — {$pr->department} — {$hoursOverdue}h past 48hr deadline. Record receipt: /procurement/{$pr->id}";

            $owners->each(fn (User $owner) => $owner->notify(
                new ProcurementStatusUpdated($pr, ProcurementStatusUpdated::EVENT_RECEIPT_OVERDUE, $overdueMessage)
            ));

            if ($pr->requester) {
                $pr->requester->notify(new ProcurementStatusUpdated(
                    $pr,
                    ProcurementStatusUpdated::EVENT_RECEIPT_OVERDUE,
                    "Reminder: Record receipt for procurement #{$pr->id} immediately — {$hoursOverdue}h past deadline."
                ));
            }

            $pr->update(['receipt_overdue_notified_at' => now()]);

            AuditLog::log(
                'procurement.receipt_overdue_flagged',
                ProcurementRequest::class,
                $pr->id,
                null,
                ['hours_overdue' => $hoursOverdue, 'department' => $pr->department]
            );

            $this->line("Flagged #{$pr->id} ({$pr->department}) — {$hoursOverdue}h overdue");
        }

        $this->info("Flagged {$overdue->count()} overdue receipt(s).");
        return self::SUCCESS;
    }
}
