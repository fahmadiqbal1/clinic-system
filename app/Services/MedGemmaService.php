<?php

namespace App\Services;

use App\Jobs\AnalyseConsultationJob;
use App\Models\AiAnalysis;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Notifications\AiAnalysisCompleted;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MedGemmaService
{
    protected string $apiKey;
    protected string $model;
    protected string $apiUrl;
    protected string $provider;
    protected ?PlatformSetting $dbSetting;
    protected CaseTokenService $caseTokenService;

    public function __construct(CaseTokenService $caseTokenService)
    {
        $this->caseTokenService = $caseTokenService;
        // Prefer database-stored settings over .env so the Owner can manage
        // credentials through the Platform Settings UI without touching the server.
        try {
            $this->dbSetting = PlatformSetting::where('platform_name', 'medgemma')->first();
        } catch (\Exception) {
            $this->dbSetting = null;
        }

        $this->provider = ($this->dbSetting && $this->dbSetting->provider)
            ? $this->dbSetting->provider
            : config('medgemma.provider', 'ollama');

        $this->apiKey = ($this->dbSetting && $this->dbSetting->hasApiKey())
            ? $this->dbSetting->api_key
            : config('medgemma.api_key', '');

        $this->model = ($this->dbSetting && $this->dbSetting->model)
            ? $this->dbSetting->model
            : config('medgemma.model', 'medgemma');

        $this->apiUrl = ($this->dbSetting && $this->dbSetting->api_url)
            ? $this->dbSetting->api_url
            : config('medgemma.api_url', 'http://localhost:11434');
    }

    /**
     * Check whether the configured AI endpoint is currently reachable.
     *
     * Uses a 3-second timeout against the Ollama /api/version health endpoint
     * (or a generic GET of the configured base URL for Hugging Face).
     * Returns false on any connection error — never throws.
     */
    public function isReachable(): bool
    {
        try {
            if ($this->provider === 'local') {
                $pingUrl = rtrim($this->apiUrl, '/') . '/docs';
                $response = Http::timeout(3)->get($pingUrl);
                return $response->successful();
            }

            $pingUrl = $this->provider === 'ollama'
                ? rtrim($this->apiUrl, '/') . '/api/version'
                : rtrim($this->apiUrl, '/');

            $headers = [];
            if ($this->provider === 'ollama') {
                $headers['bypass-tunnel-reminder'] = 'true';
            }

            $response = Http::withHeaders($headers)->timeout(3)->get($pingUrl);
            return $response->successful() || $response->status() === 404;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Queue a consultation analysis (async).
     *
     * If the AI endpoint is unreachable, marks the analysis as offline_pending
     * and notifies the requester — the scheduler retries every 5 minutes.
     */
    public function analyseConsultation(Patient $patient, int $requestedBy, ?string $customQuestion = null): AiAnalysis
    {
        $caseToken = $this->caseTokenService->tokenize($patient);
        $analysis  = AiAnalysis::create([
            'patient_id'     => $patient->id,
            'invoice_id'     => null,
            'requested_by'   => $requestedBy,
            'context_type'   => 'consultation',
            'prompt_summary' => $customQuestion
                ? "Quick question: {$customQuestion}"
                : "Consultation analysis [case:{$caseToken}]",
            'status'         => 'pending',
        ]);

        if (!$this->isReachable()) {
            return $this->markOfflinePending($analysis, $requestedBy);
        }

        AnalyseConsultationJob::dispatch($analysis, 'consultation', null, $customQuestion);

        return $analysis;
    }

    /**
     * Queue a lab analysis (async).
     */
    public function analyseLab(Invoice $invoice, int $requestedBy): AiAnalysis
    {
        $analysis = AiAnalysis::create([
            'patient_id'     => $invoice->patient_id,
            'invoice_id'     => $invoice->id,
            'requested_by'   => $requestedBy,
            'context_type'   => 'lab',
            'prompt_summary' => "Lab analysis for invoice #{$invoice->id}",
            'status'         => 'pending',
        ]);

        if (!$this->isReachable()) {
            return $this->markOfflinePending($analysis, $requestedBy);
        }

        AnalyseConsultationJob::dispatch($analysis, 'lab', $invoice->id);

        return $analysis;
    }

    /**
     * Queue a radiology analysis (async).
     */
    public function analyseRadiology(Invoice $invoice, int $requestedBy): AiAnalysis
    {
        $analysis = AiAnalysis::create([
            'patient_id'     => $invoice->patient_id,
            'invoice_id'     => $invoice->id,
            'requested_by'   => $requestedBy,
            'context_type'   => 'radiology',
            'prompt_summary' => "Radiology analysis for invoice #{$invoice->id}",
            'status'         => 'pending',
        ]);

        if (!$this->isReachable()) {
            return $this->markOfflinePending($analysis, $requestedBy);
        }

        AnalyseConsultationJob::dispatch($analysis, 'radiology', $invoice->id);

        return $analysis;
    }

    /**
     * Mark an analysis as offline_pending and immediately notify the requester.
     */
    private function markOfflinePending(AiAnalysis $analysis, int $requestedBy): AiAnalysis
    {
        $analysis->update([
            'status'      => 'offline_pending',
            'ai_response' => 'AI model is currently offline. Analysis will run automatically when your computer and tunnel are connected.',
        ]);

        /** @var User|null $requester */
        $requester = User::find($requestedBy);
        $requester?->notify(new AiAnalysisCompleted($analysis, offline: true));

        return $analysis;
    }

    /**
     * Process consultation analysis (called by job).
     */
    public function processConsultationAnalysis(AiAnalysis $analysis, Patient $patient, ?string $customQuestion = null): void
    {
        $patient->load(['triageVitals', 'prescriptions.items', 'invoices.items.serviceCatalog']);

        $latestVitals = $patient->triageVitals()->latest()->first();
        $prompt = $this->buildConsultationPrompt($patient, $latestVitals);

        // Append custom question if provided (Quick Chat feature)
        if ($customQuestion) {
            $prompt .= "\n\n---\nDOCTOR'S SPECIFIC QUESTION:\n{$customQuestion}\n\nPlease answer this question directly, then provide any additional relevant observations.";
        }

        $imageContents = $this->getPatientRadiologyImages($patient);

        $this->executeAnalysis($analysis, $prompt, $imageContents);
    }

    /**
     * Process lab analysis (called by job).
     */
    public function processLabAnalysis(AiAnalysis $analysis, Invoice $invoice): void
    {
        $invoice->load(['patient', 'items.serviceCatalog']);
        $prompt = $this->buildLabPrompt($invoice);

        $this->executeAnalysis($analysis, $prompt);
    }

    /**
     * Process radiology analysis (called by job).
     */
    public function processRadiologyAnalysis(AiAnalysis $analysis, Invoice $invoice): void
    {
        $invoice->load(['patient', 'items.serviceCatalog']);
        $prompt = $this->buildRadiologyPrompt($invoice);
        $imageContents = $this->getRadiologyImages($invoice);

        $this->executeAnalysis($analysis, $prompt, $imageContents);
    }

    /**
     * Execute the actual API call and update the analysis record.
     */
    private function executeAnalysis(AiAnalysis $analysis, string $prompt, array $imageContents = []): void
    {
        try {
            $analysis->update([
                'prompt_summary' => mb_substr($prompt, 0, 2000),
            ]);

            $response = $this->callApi($prompt, $imageContents);

            $analysis->update([
                'ai_response' => $response,
                'status' => 'completed',
            ]);
        } catch (\Exception $e) {
            Log::error('MedGemma API error', [
                'analysis_id' => $analysis->id,
                'error' => $e->getMessage(),
            ]);

            $analysis->update([
                'ai_response' => 'Analysis could not be completed: ' . $e->getMessage(),
                'status' => 'failed',
            ]);

            throw $e;
        }
    }

    /**
     * Build prompt for consultation context.
     *
     * PHI-free: uses case token + age band + gender. No name, DOB, CNIC, or contact details.
     */
    private function buildConsultationPrompt(Patient $patient, $vitals): string
    {
        $parts = ["You are MedGemma, an AI medical assistant providing a second opinion. Analyse ALL the following patient data comprehensively and provide your clinical assessment, possible differential diagnoses, and recommendations.\n"];

        $caseToken = $this->caseTokenService->tokenize($patient);
        $ageBand   = $this->caseTokenService->ageBand($patient->date_of_birth);

        $parts[] = "Case Token: {$caseToken}, Gender: {$patient->gender}, Age Band: {$ageBand}";

        if ($vitals) {
            $parts[] = "\n--- Vital Signs (Triage) ---";
            if ($vitals->blood_pressure) $parts[] = "Blood Pressure: {$vitals->blood_pressure} mmHg";
            if ($vitals->temperature) $parts[] = "Temperature: {$vitals->temperature}°C";
            if ($vitals->pulse_rate) $parts[] = "Heart Rate: {$vitals->pulse_rate} bpm";
            if ($vitals->respiratory_rate) $parts[] = "Respiratory Rate: {$vitals->respiratory_rate} br/min";
            if ($vitals->oxygen_saturation) $parts[] = "SpO2: {$vitals->oxygen_saturation}%";
            if ($vitals->weight) $parts[] = "Weight: {$vitals->weight} kg";
            if ($vitals->height) $parts[] = "Height: {$vitals->height} cm";
            if ($vitals->chief_complaint) $parts[] = "Chief Complaint: {$vitals->chief_complaint}";
            if ($vitals->priority) $parts[] = "Priority: {$vitals->priority}";
            if ($vitals->notes) $parts[] = "Triage Notes: {$vitals->notes}";
        }

        if ($patient->consultation_notes) {
            $parts[] = "\n--- Doctor's Consultation Notes ---";
            $parts[] = $patient->consultation_notes;
        }

        // Include prescriptions
        $prescriptions = $patient->prescriptions()->with('items')->latest()->get();
        if ($prescriptions->count() > 0) {
            $parts[] = "\n--- Prescribed Medications ---";
            foreach ($prescriptions as $rx) {
                foreach ($rx->items as $item) {
                    $line = $item->medication_name;
                    if ($item->dosage) $line .= " — Dosage: {$item->dosage}";
                    if ($item->frequency) $line .= ", Frequency: {$item->frequency}";
                    if ($item->duration) $line .= ", Duration: {$item->duration}";
                    $parts[] = $line;
                }
            }
        }

        // Include completed lab/radiology results
        $completedInvoices = $patient->invoices()
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('department', 'lab')->whereNotNull('lab_results');
                })->orWhere(function ($q2) {
                    $q2->where('department', 'radiology')->whereNotNull('report_text');
                });
            })->with('items.serviceCatalog')->get();

        foreach ($completedInvoices as $inv) {
            if ($inv->department === 'lab' && $inv->lab_results) {
                $parts[] = "\n--- Lab Results ({$inv->service_name}) ---";
                foreach ((array) $inv->lab_results as $key => $results) {
                    $itemName = $inv->items->firstWhere('id', $key)?->description ?? $key;
                    if ($itemName !== $key) {
                        $parts[] = "[{$itemName}]";
                    }
                    foreach ((array) $results as $r) {
                        $parts[] = ($r['test_name'] ?? '') . ": " . ($r['result'] ?? '') . " " . ($r['unit'] ?? '') . " (Ref: " . ($r['reference_range'] ?? 'N/A') . ")";
                    }
                }
                if ($inv->report_text) {
                    $parts[] = "Lab Technician Report: {$inv->report_text}";
                }
            }
            if ($inv->department === 'radiology' && $inv->report_text) {
                $parts[] = "\n--- Radiology Report ({$inv->service_name}) ---";
                $parts[] = "Radiologist Findings: {$inv->report_text}";
                if (!empty($inv->radiology_images)) {
                    $parts[] = count($inv->radiology_images) . " radiology image(s) are attached for your visual analysis.";
                }
            }
        }

        $parts[] = "\nProvide your comprehensive analysis as a clinical second opinion based on ALL available data above. Include: overall assessment, differential diagnoses, correlation between findings from different departments, and recommendations.";

        return implode("\n", $parts);
    }

    /**
     * Build prompt for lab context.
     *
     * PHI-free: uses case token + age band + gender.
     */
    private function buildLabPrompt(Invoice $invoice): string
    {
        $parts = ["You are MedGemma, an AI medical assistant. Analyse the following laboratory test results and provide your clinical interpretation.\n"];

        $patient = $invoice->patient;
        if ($patient) {
            $caseToken = $this->caseTokenService->tokenize($patient);
            $ageBand   = $this->caseTokenService->ageBand($patient->date_of_birth);
            $parts[]   = "Case Token: {$caseToken}, Gender: {$patient->gender}, Age Band: {$ageBand}";
        }

        $parts[] = "\nTest: {$invoice->service_name}";

        if ($invoice->lab_results) {
            $parts[] = "\n--- Structured Results ---";
            foreach ((array) $invoice->lab_results as $key => $results) {
                $itemName = $invoice->items->firstWhere('id', $key)?->description ?? $key;
                $parts[] = "\n[{$itemName}]";
                foreach ((array) $results as $r) {
                    $parts[] = ($r['test_name'] ?? '') . ": " . ($r['result'] ?? '') . " " . ($r['unit'] ?? '') . " (Ref: " . ($r['reference_range'] ?? 'N/A') . ")";
                }
            }
        }

        if ($invoice->report_text) {
            $parts[] = "\n--- Technician Report ---";
            $parts[] = $invoice->report_text;
        }

        $parts[] = "\nProvide your analysis: interpretation of results, any abnormalities detected, clinical significance, and recommendations.";

        return implode("\n", $parts);
    }

    /**
     * Build prompt for radiology context.
     *
     * PHI-free: uses case token + age band + gender.
     */
    private function buildRadiologyPrompt(Invoice $invoice): string
    {
        $parts = ["You are MedGemma, an AI medical assistant. Analyse the following radiology imaging data and provide your clinical interpretation.\n"];

        $patient = $invoice->patient;
        if ($patient) {
            $caseToken = $this->caseTokenService->tokenize($patient);
            $ageBand   = $this->caseTokenService->ageBand($patient->date_of_birth);
            $parts[]   = "Case Token: {$caseToken}, Gender: {$patient->gender}, Age Band: {$ageBand}";
        }

        $parts[] = "\nImaging Type: {$invoice->service_name}";

        if ($invoice->report_text) {
            $parts[] = "\n--- Technician Report ---";
            $parts[] = $invoice->report_text;
        }

        $hasImages = !empty($invoice->radiology_images);
        if ($hasImages) {
            $parts[] = "\n" . count($invoice->radiology_images) . " image(s) attached for analysis.";
        }

        $parts[] = "\nProvide your analysis: findings from the imaging data, any abnormalities detected, clinical significance, and recommendations.";

        return implode("\n", $parts);
    }

    /**
     * Get radiology images as base64 content for multimodal analysis.
     */
    private function getRadiologyImages(Invoice $invoice): array
    {
        return $this->collectImages($invoice->radiology_images ?? []);
    }

    /**
     * Collect all radiology images across a patient's completed invoices.
     */
    private function getPatientRadiologyImages(Patient $patient): array
    {
        $allPaths = [];
        $radiologyInvoices = $patient->invoices()
            ->where('department', 'radiology')
            ->whereNotNull('radiology_images')
            ->get();

        foreach ($radiologyInvoices as $inv) {
            foreach (($inv->radiology_images ?? []) as $path) {
                $allPaths[] = $path;
            }
        }

        return $this->collectImages($allPaths);
    }

    /**
     * Convert image paths to base64-encoded content blocks for the vision API.
     */
    private function collectImages(array $paths): array
    {
        $images = [];

        foreach ($paths as $path) {
            if (Storage::disk('public')->exists($path)) {
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                // Only include actual images, skip PDFs for vision API
                if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                    $content = Storage::disk('public')->get($path);
                    $mimeType = match ($extension) {
                        'jpg', 'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        default => 'image/jpeg',
                    };
                    $images[] = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => 'data:' . $mimeType . ';base64,' . base64_encode($content),
                        ],
                    ];
                }
            }
        }

        return $images;
    }

    /**
     * Call the MedGemma API (Hugging Face Inference API or local Ollama).
     */
    private function callApi(string $prompt, array $imageContents = []): string
    {
        // Local FastAPI bridge — MedGemma inference can take several minutes on CPU
        if ($this->provider === 'local') {
            $url = rtrim($this->apiUrl, '/') . '/second-opinion';
            $response = Http::timeout(180)->post($url, [
                'summary'   => mb_substr($prompt, 0, 2000),
                'diagnosis' => 'See summary',
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException('Local AI service returned status ' . $response->status() . ': ' . $response->body());
            }

            return $response->json('opinion', 'No response generated.');
        }

        $isOllama = $this->provider === 'ollama';

        if (!$isOllama && empty($this->apiKey)) {
            throw new \RuntimeException('Hugging Face API key is not configured. Set it via Owner Profile or HUGGINGFACE_API_KEY in .env');
        }

        // Use the PlatformSetting model helper when available; otherwise build
        // the endpoint URL from the primitive fields.
        if ($this->dbSetting) {
            $url = $this->dbSetting->chatCompletionsUrl();
        } elseif ($isOllama) {
            $url = rtrim($this->apiUrl, '/') . '/v1/chat/completions';
        } else {
            $url = rtrim($this->apiUrl, '/') . '/' . $this->model . '/v1/chat/completions';
        }

        // Build messages array
        $content = [];
        if (!empty($imageContents)) {
            // Multimodal: images + text
            foreach ($imageContents as $img) {
                $content[] = $img;
            }
            $content[] = ['type' => 'text', 'text' => $prompt];
        }

        $messages = [
            [
                'role' => 'user',
                'content' => !empty($imageContents) ? $content : $prompt,
            ],
        ];

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => 2048,
            'temperature' => 0.3,
        ];

        $headers = [];
        if (!$isOllama && !empty($this->apiKey)) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }
        // Bypass the Localtunnel browser-reminder page (safe to send for all Ollama URLs).
        if ($isOllama) {
            $headers['bypass-tunnel-reminder'] = 'true';
        }

        $response = Http::withHeaders($headers)->timeout(120)->post($url, $payload);

        if (!$response->successful()) {
            throw new \RuntimeException('MedGemma API returned status ' . $response->status() . ': ' . $response->body());
        }

        $data = $response->json();

        // Handle both OpenAI-compatible format (choices[0].message.content)
        // and Hugging Face legacy format ([0].generated_text)
        return $data['choices'][0]['message']['content']
            ?? $data[0]['generated_text']
            ?? 'No response generated.';
    }
}
