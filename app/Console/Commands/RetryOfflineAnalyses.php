<?php

namespace App\Console\Commands;

use App\Jobs\AnalyseConsultationJob;
use App\Models\AiAnalysis;
use App\Services\MedGemmaService;
use Illuminate\Console\Command;

/**
 * Finds AiAnalysis records stuck at 'offline_pending' and re-dispatches them
 * once the Ollama tunnel is reachable.
 *
 * Runs every 5 minutes via the scheduler (routes/console.php).
 *
 * Usage:
 *   php artisan ai:retry-pending           # Normal run
 *   php artisan ai:retry-pending --dry-run # List without dispatching
 */
class RetryOfflineAnalyses extends Command
{
    protected $signature = 'ai:retry-pending
                            {--dry-run : List pending analyses without dispatching}';

    protected $description = 'Re-dispatch AI analyses that were queued while Ollama was offline';

    public function handle(MedGemmaService $medGemma): int
    {
        $pending = AiAnalysis::where('status', 'offline_pending')->get();

        if ($pending->isEmpty()) {
            $this->info('No offline-pending analyses found.');
            return self::SUCCESS;
        }

        $this->info("Found {$pending->count()} offline-pending analysis(es).");

        if ($this->option('dry-run')) {
            foreach ($pending as $analysis) {
                $this->line("  [{$analysis->id}] {$analysis->context_type} — patient #{$analysis->patient_id}");
            }
            return self::SUCCESS;
        }

        if (! $medGemma->isReachable()) {
            $this->warn('Ollama tunnel is still unreachable. Skipping retry.');
            return self::SUCCESS;
        }

        $retried = 0;

        foreach ($pending as $analysis) {
            $analysis->update(['status' => 'pending', 'ai_response' => null]);
            AnalyseConsultationJob::dispatch($analysis->id);
            $retried++;
            $this->line("  Retried [{$analysis->id}] {$analysis->context_type} — patient #{$analysis->patient_id}");
        }

        $this->info("Dispatched {$retried} analysis job(s).");

        return self::SUCCESS;
    }
}
