<?php

namespace App\Console\Commands;

use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\ExpiryAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Scan stock movements with expiry_date set and alert Owner + department
 * staff for batches expiring within N days (default: 30) or already expired.
 *
 * Runs daily at 07:00 via the scheduler (routes/console.php).
 *
 * Usage:
 *   php artisan inventory:check-expiry          # Default 30-day window
 *   php artisan inventory:check-expiry --days=7 # 7-day window
 *   php artisan inventory:check-expiry --dry-run # Preview only
 */
class CheckExpiryAlerts extends Command
{
    protected $signature = 'inventory:check-expiry
                            {--days=30 : Warn for batches expiring within this many days}
                            {--dry-run : List items without sending notifications}';

    protected $description = 'Send expiry alerts for stock batches approaching or past their expiry date';

    public function handle(): int
    {
        $days   = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->addDays($days)->endOfDay();

        $expiring = StockMovement::with('inventoryItem')
            ->whereNotNull('expiry_date')
            ->where('quantity', '>', 0)          // Inbound movements only
            ->where('expiry_date', '<=', $cutoff)
            ->orderBy('expiry_date')
            ->get();

        if ($expiring->isEmpty()) {
            $this->info('No expiring stock found.');
            return self::SUCCESS;
        }

        $this->info("Found {$expiring->count()} batch(es) expiring on or before " . $cutoff->format('d M Y') . '.');

        $sent = 0;

        foreach ($expiring as $movement) {
            $item = $movement->inventoryItem;
            if (!$item) {
                continue;
            }

            $daysLeft = (int) now()->diffInDays($movement->expiry_date, false);
            $batchTag = $movement->batch_number ? " [Batch: {$movement->batch_number}]" : '';
            $label    = $daysLeft < 0
                ? "EXPIRED on {$movement->expiry_date->format('d M Y')}"
                : "Expires in {$daysLeft} day(s) ({$movement->expiry_date->format('d M Y')})";

            if ($dryRun) {
                $this->line("  {$item->name}{$batchTag} — {$label}");
                continue;
            }

            // Throttle: one alert per movement per 24h
            $cacheKey = "expiry_alert_{$movement->id}";
            if (Cache::has($cacheKey)) {
                continue;
            }
            Cache::put($cacheKey, true, now()->addHours(24));

            $recipients = User::role($item->department)->get()
                ->merge(User::role('Owner')->get())
                ->unique('id');

            Notification::send($recipients, new ExpiryAlert($item, $movement));

            $this->line("  Notified: {$item->name}{$batchTag} — {$label}");
            $sent++;
        }

        if (!$dryRun) {
            $this->info("Sent {$sent} expiry notification(s).");
        }

        return self::SUCCESS;
    }
}
