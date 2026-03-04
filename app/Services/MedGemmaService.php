<?php

namespace App\Services;

use App\Models\AiAnalysis;
use App\Models\Invoice;
use App\Models\Patient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MedGemmaService
{
    protected string $apiKey;
    protected string $model;
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('medgemma.api_key');
        $this->model = config('medgemma.model');
        $this->apiUrl = config('medgemma.api_url');
    }

    /**
     * Analyse a consultation (vitals + doctor notes).
     */
    public function analyseConsultation(Patient $patient, int $requestedBy): AiAnalysis
    {
        $patient->load(['triageVitals', 'prescriptions.items', 'invoices']);

        $latestVitals = $patient->triageVitals()->latest()->first();

        $prompt = $this->buildConsultationPrompt($patient, $latestVitals);

        return $this->runAnalysis($patient->id, null, $requestedBy, 'consultation', $prompt);
    }

    /**
     * Analyse a lab invoice (report + structured results).
     */
    public function analyseLab(Invoice $invoice, int $requestedBy): AiAnalysis
    {
        $invoice->load(['patient', 'items.serviceCatalog']);

        $prompt = $this->buildLabPrompt($invoice);

        return $this->runAnalysis($invoice->patient_id, $invoice->id, $requestedBy, 'lab', $prompt);
    }

    /**
     * Analyse a radiology invoice (report + images description).
     */
    public function analyseRadiology(Invoice $invoice, int $requestedBy): AiAnalysis
    {
        $invoice->load(['patient', 'items.serviceCatalog']);

        $prompt = $this->buildRadiologyPrompt($invoice);
        $imageContents = $this->getRadiologyImages($invoice);

        return $this->runAnalysis(
            $invoice->patient_id,
            $invoice->id,
            $requestedBy,
            'radiology',
            $prompt,
            $imageContents
        );
    }

    /**
     * Build prompt for consultation context.
     */
    private function buildConsultationPrompt(Patient $patient, $vitals): string
    {
        $parts = ["You are MedGemma, an AI medical assistant providing a second opinion. Analyse the following patient data and provide your clinical assessment, possible differential diagnoses, and recommendations.\n"];

        $parts[] = "Patient: {$patient->first_name} {$patient->last_name}, Gender: {$patient->gender}";
        if ($patient->date_of_birth) {
            $parts[] = "Age: " . $patient->date_of_birth->age . " years";
        }

        if ($vitals) {
            $parts[] = "\n--- Vital Signs ---";
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

        // Include completed lab/radiology results if available
        $completedInvoices = $patient->invoices()
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('department', 'lab')->whereNotNull('lab_results');
                })->orWhere(function ($q2) {
                    $q2->where('department', 'radiology')->whereNotNull('report_text');
                });
            })->get();

        foreach ($completedInvoices as $inv) {
            if ($inv->department === 'lab' && $inv->lab_results) {
                $parts[] = "\n--- Lab Results ({$inv->service_name}) ---";
                foreach ((array) $inv->lab_results as $key => $results) {
                    foreach ((array) $results as $r) {
                        $parts[] = ($r['test_name'] ?? '') . ": " . ($r['result'] ?? '') . " " . ($r['unit'] ?? '') . " (Ref: " . ($r['reference_range'] ?? 'N/A') . ")";
                    }
                }
                if ($inv->report_text) {
                    $parts[] = "Lab Report: {$inv->report_text}";
                }
            }
            if ($inv->department === 'radiology' && $inv->report_text) {
                $parts[] = "\n--- Radiology Report ({$inv->service_name}) ---";
                $parts[] = $inv->report_text;
            }
        }

        $parts[] = "\nProvide your analysis as a clinical second opinion. Include: assessment, differential diagnoses, and recommendations.";

        return implode("\n", $parts);
    }

    /**
     * Build prompt for lab context.
     */
    private function buildLabPrompt(Invoice $invoice): string
    {
        $parts = ["You are MedGemma, an AI medical assistant. Analyse the following laboratory test results and provide your clinical interpretation.\n"];

        $patient = $invoice->patient;
        if ($patient) {
            $parts[] = "Patient: {$patient->first_name} {$patient->last_name}, Gender: {$patient->gender}";
            if ($patient->date_of_birth) {
                $parts[] = "Age: " . $patient->date_of_birth->age . " years";
            }
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
     */
    private function buildRadiologyPrompt(Invoice $invoice): string
    {
        $parts = ["You are MedGemma, an AI medical assistant. Analyse the following radiology imaging data and provide your clinical interpretation.\n"];

        $patient = $invoice->patient;
        if ($patient) {
            $parts[] = "Patient: {$patient->first_name} {$patient->last_name}, Gender: {$patient->gender}";
            if ($patient->date_of_birth) {
                $parts[] = "Age: " . $patient->date_of_birth->age . " years";
            }
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
        $images = [];
        $radiologyImages = $invoice->radiology_images ?? [];

        foreach ($radiologyImages as $path) {
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
     * Execute analysis via Hugging Face Inference API.
     */
    private function runAnalysis(
        int $patientId,
        ?int $invoiceId,
        int $requestedBy,
        string $contextType,
        string $prompt,
        array $imageContents = []
    ): AiAnalysis {
        $analysis = AiAnalysis::create([
            'patient_id' => $patientId,
            'invoice_id' => $invoiceId,
            'requested_by' => $requestedBy,
            'context_type' => $contextType,
            'prompt_summary' => mb_substr($prompt, 0, 2000),
            'status' => 'pending',
        ]);

        try {
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
        }

        return $analysis;
    }

    /**
     * Call the Hugging Face Inference API.
     */
    private function callApi(string $prompt, array $imageContents = []): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Hugging Face API key is not configured. Set HUGGINGFACE_API_KEY in .env');
        }

        $url = rtrim($this->apiUrl, '/') . '/' . $this->model . '/v1/chat/completions';

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

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->timeout(120)->post($url, [
            'messages' => $messages,
            'max_tokens' => 2048,
            'temperature' => 0.3,
        ]);

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
