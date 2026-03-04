<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupRadiologyImages extends Command
{
    protected $signature = 'radiology:cleanup-images {--days=30 : Delete images older than this many days}';
    protected $description = 'Delete radiology images older than the specified retention period';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $invoices = Invoice::where('department', 'radiology')
            ->whereNotNull('radiology_images')
            ->where('created_at', '<', $cutoff)
            ->get();

        $totalDeleted = 0;

        foreach ($invoices as $invoice) {
            $images = $invoice->radiology_images ?? [];

            if (empty($images)) {
                continue;
            }

            foreach ($images as $path) {
                Storage::disk('public')->delete($path);
            }

            $totalDeleted += count($images);
            $invoice->update(['radiology_images' => null]);
        }

        $this->info("Cleaned up {$totalDeleted} image(s) from {$invoices->count()} invoice(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
