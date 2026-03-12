<?php

namespace App\Notifications;

use App\Models\AiAnalysis;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AiAnalysisCompleted extends Notification
{
    use Queueable;

    public function __construct(
        public AiAnalysis $analysis,
        public bool $failed = false,
        public bool $offline = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $contextLabel = ucfirst($this->analysis->context_type);
        $patientName = $this->analysis->patient?->full_name ?? 'Patient';

        if ($this->offline) {
            return [
                'title'       => 'AI Model Offline — Analysis Queued',
                'message'     => "AI analysis for {$patientName} ({$contextLabel}) is queued. It will run automatically when your computer and tunnel are connected.",
                'icon'        => 'bi-wifi-off',
                'url'         => $this->resolveUrl(),
                'color'       => 'warning',
                'assigned_at' => now()->toIso8601String(),
            ];
        }

        if ($this->failed) {
            return [
                'title' => 'MedGemma Analysis Failed',
                'message' => "AI analysis for {$patientName} ({$contextLabel}) could not complete. Please try again.",
                'icon' => 'bi-robot',
                'url' => $this->resolveUrl(),
                'color' => 'danger',
                'assigned_at' => now()->toIso8601String(),
            ];
        }

        return [
            'title' => 'MedGemma Analysis Ready',
            'message' => "Your AI second opinion for {$patientName} ({$contextLabel}) is ready to review.",
            'icon' => 'bi-robot',
            'url' => $this->resolveUrl(),
            'color' => 'success',
            'assigned_at' => now()->toIso8601String(),
        ];
    }

    private function resolveUrl(): string
    {
        return match ($this->analysis->context_type) {
            'consultation' => "/doctor/consultations/{$this->analysis->patient_id}",
            'lab'          => "/laboratory/invoices/{$this->analysis->invoice_id}",
            'radiology'    => "/radiology/invoices/{$this->analysis->invoice_id}",
            default        => "/ai-analysis/patient/{$this->analysis->patient_id}",
        };
    }
}
