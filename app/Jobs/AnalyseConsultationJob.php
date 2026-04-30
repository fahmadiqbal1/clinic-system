<?php

namespace App\Jobs;

use App\Models\AiAnalysis;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PlatformSetting;
use App\Notifications\AiAnalysisCompleted;
use App\Services\AiSidecarClient;
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
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public AiAnalysis $analysis,
        public string $contextType,
        public ?int $invoiceId = null,
        public ?string $customQuestion = null,
    ) {}

    /**
     * Execute the job.
     * When ai.sidecar.enabled is ON: ALL context types route through the sidecar's
     * active model provider (online or Ollama). Reachability check is skipped —
     * the circuit breaker on AiSidecarClient handles outages.
     * When flag is OFF: direct MedGemma path (Ollama / HuggingFace via PHP).
     */
    public function handle(MedGemmaService $service, AiSidecarClient $sidecar): void
    {
        // When sidecar is ON, skip the Ollama reachability check — the sidecar uses
        // whatever online/offline provider is configured and has its own circuit breaker.
        if (PlatformSetting::isEnabled('ai.sidecar.enabled')) {
            try {
                $this->handleViaSidecar($sidecar, $service);
            } catch (\Exception $e) {
                Log::error('AI Analysis via sidecar failed', [
                    'analysis_id' => $this->analysis->id,
                    'context'     => $this->contextType,
                    'attempt'     => $this->attempts(),
                    'error'       => $e->getMessage(),
                ]);
                throw $e;
            }
            return;
        }

        // Flag-off path: direct PHP → Ollama / HuggingFace
        if (!$service->isReachable()) {
            $this->analysis->update([
                'status'      => 'offline_pending',
                'ai_response' => 'AI model went offline after the job was queued. Will retry automatically.',
            ]);
            return;
        }

        try {

            $patient = Patient::find($this->analysis->patient_id);

            if (!$patient) {
                $this->fail(new \Exception('Patient not found'));
                return;
            }

            // Direct MedGemma path (flag off, or non-consultation context)
            match ($this->contextType) {
                'consultation' => $service->processConsultationAnalysis($this->analysis, $patient, $this->customQuestion),
                'lab'          => $this->processLabAnalysis($service),
                'radiology'    => $this->processRadiologyAnalysis($service),
                default        => throw new \InvalidArgumentException("Unknown context type: {$this->contextType}"),
            };

            // Notify the requester that analysis is ready
            $this->analysis->refresh();
            $requester = $this->analysis->requester;
            if ($requester) {
                $requester->notify(new AiAnalysisCompleted($this->analysis));
            }

        } catch (\Exception $e) {
            Log::error('AI Analysis job failed', [
                'analysis_id'  => $this->analysis->id,
                'context_type' => $this->contextType,
                'attempt'      => $this->attempts(),
                'error'        => $e->getMessage(),
            ]);

            // Do NOT set status=failed here — the job will be retried.
            // Keeping status as 'pending' lets the frontend keep polling.
            // The failed() method sets status=failed only after all retries are exhausted.
            throw $e;
        }
    }

    /**
     * Route any analysis context through the sidecar's active model provider.
     * Consultation → /v1/consult (ETCSLV harness).
     * Lab / Radiology → /v1/analyse (generic single-turn, same provider).
     */
    private function handleViaSidecar(AiSidecarClient $sidecar, MedGemmaService $service): void
    {
        match ($this->contextType) {
            'consultation' => $this->handleConsultationViaSidecar($sidecar),
            'lab'          => $this->handleLabViaSidecar($sidecar, $service),
            'radiology'    => $this->handleRadiologyViaSidecar($sidecar, $service),
            default        => throw new \InvalidArgumentException("Unknown context type: {$this->contextType}"),
        };
    }

    private function handleConsultationViaSidecar(AiSidecarClient $sidecar): void
    {
        $patient = Patient::with(['triageVitals', 'prescriptions.items', 'invoices.items.serviceCatalog'])
            ->find($this->analysis->patient_id);

        if (!$patient) {
            $this->fail(new \Exception('Patient not found'));
            return;
        }

        $latestVitals = $patient->triageVitals()->latest()->first();

        $vitals = [];
        if ($latestVitals) {
            if ($latestVitals->blood_pressure && str_contains($latestVitals->blood_pressure, '/')) {
                [$sys, $dia] = explode('/', $latestVitals->blood_pressure);
                $vitals['bp_systolic']  = (int) trim($sys);
                $vitals['bp_diastolic'] = (int) trim($dia);
            }
            if ($latestVitals->pulse_rate)         $vitals['heart_rate']      = (int) $latestVitals->pulse_rate;
            if ($latestVitals->temperature)         $vitals['temperature_c']   = (float) $latestVitals->temperature;
            if ($latestVitals->oxygen_saturation)   $vitals['spo2']            = (int) $latestVitals->oxygen_saturation;
            if ($latestVitals->chief_complaint)     $vitals['chief_complaint'] = $latestVitals->chief_complaint;
        }

        try {
            $result = $sidecar->consult($patient, $vitals, $this->customQuestion);

            $this->analysis->update([
                'ai_response' => $result['rationale'] ?? json_encode($result),
                'status'      => 'completed',
            ]);

            $this->notifyRequester();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function handleLabViaSidecar(AiSidecarClient $sidecar, MedGemmaService $service): void
    {
        if (!$this->invoiceId) {
            throw new \InvalidArgumentException('Invoice ID required for lab analysis');
        }

        $invoice = Invoice::with(['patient', 'items.serviceCatalog'])->find($this->invoiceId);
        if (!$invoice) {
            throw new \Exception('Invoice not found');
        }

        $prompt = $service->buildLabPromptPublic($invoice);
        $text   = $sidecar->analyseText($service->getSystemPrompt(), $prompt);

        $this->analysis->update(['ai_response' => $text, 'status' => 'completed']);
        $this->notifyRequester();
    }

    private function handleRadiologyViaSidecar(AiSidecarClient $sidecar, MedGemmaService $service): void
    {
        if (!$this->invoiceId) {
            throw new \InvalidArgumentException('Invoice ID required for radiology analysis');
        }

        $invoice = Invoice::with(['patient', 'items.serviceCatalog'])->find($this->invoiceId);
        if (!$invoice) {
            throw new \Exception('Invoice not found');
        }

        $prompt = $service->buildRadiologyPromptPublic($invoice);
        $text   = $sidecar->analyseText($service->getSystemPrompt(), $prompt);

        $this->analysis->update(['ai_response' => $text, 'status' => 'completed']);
        $this->notifyRequester();
    }

    private function notifyRequester(): void
    {
        $this->analysis->refresh();
        $requester = $this->analysis->requester;
        if ($requester) {
            $requester->notify(new AiAnalysisCompleted($this->analysis));
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

        // Notify requester of permanent failure
        $requester = $this->analysis->requester;
        if ($requester) {
            $requester->notify(new AiAnalysisCompleted($this->analysis, failed: true));
        }
    }
}
