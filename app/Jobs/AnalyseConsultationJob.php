<?php

namespace App\Jobs;

use App\Models\AiAnalysis;
use App\Models\Invoice;
use App\Models\Patient;
use App\Services\MedGemmaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to process MedGemma AI analysis asynchronously.
 * 
 * AI analysis can take 10-60+ seconds depending on the provider,
 * so we queue it to prevent HTTP timeouts and blocking workers.
 */
class AnalyseConsultationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public AiAnalysis $analysis,
        public string $contextType,
        public ?int $invoiceId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MedGemmaService $service): void
    {
        try {
            $patient = Patient::find($this->analysis->patient_id);
            
            if (!$patient) {
                $this->fail(new \Exception('Patient not found'));
                return;
            }

            // Delegate to appropriate analysis method based on context
            match ($this->contextType) {
                'consultation' => $service->processConsultationAnalysis($this->analysis, $patient),
                'lab' => $this->processLabAnalysis($service),
                'radiology' => $this->processRadiologyAnalysis($service),
                default => throw new \InvalidArgumentException("Unknown context type: {$this->contextType}"),
            };

        } catch (\Exception $e) {
            Log::error('AI Analysis job failed', [
                'analysis_id' => $this->analysis->id,
                'context_type' => $this->contextType,
                'error' => $e->getMessage(),
            ]);

            $this->analysis->update([
                'status' => 'failed',
                'ai_response' => 'Analysis failed: ' . $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process lab analysis.
     */
    private function processLabAnalysis(MedGemmaService $service): void
    {
        if (!$this->invoiceId) {
            throw new \InvalidArgumentException('Invoice ID required for lab analysis');
        }

        $invoice = Invoice::with(['patient', 'items.serviceCatalog'])->find($this->invoiceId);
        
        if (!$invoice) {
            throw new \Exception('Invoice not found');
        }

        $service->processLabAnalysis($this->analysis, $invoice);
    }

    /**
     * Process radiology analysis.
     */
    private function processRadiologyAnalysis(MedGemmaService $service): void
    {
        if (!$this->invoiceId) {
            throw new \InvalidArgumentException('Invoice ID required for radiology analysis');
        }

        $invoice = Invoice::with(['patient', 'items.serviceCatalog'])->find($this->invoiceId);
        
        if (!$invoice) {
            throw new \Exception('Invoice not found');
        }

        $service->processRadiologyAnalysis($this->analysis, $invoice);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AI Analysis job permanently failed', [
            'analysis_id' => $this->analysis->id,
            'error' => $exception->getMessage(),
        ]);

        $this->analysis->update([
            'status' => 'failed',
            'ai_response' => 'Analysis permanently failed after retries.',
        ]);
    }
}
