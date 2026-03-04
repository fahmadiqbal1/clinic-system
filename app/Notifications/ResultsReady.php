<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ResultsReady extends Notification
{
    use Queueable;

    public function __construct(
        public int $invoiceId,
        public string $department,
        public string $patientName,
        public string $serviceDescription,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $deptLabel = $this->department === 'lab' ? 'Laboratory' : 'Radiology';

        return [
            'title' => "{$deptLabel} Results Ready",
            'message' => "{$deptLabel} results for {$this->patientName} ({$this->serviceDescription}) are ready for review.",
            'icon' => $this->department === 'lab' ? 'bi-droplet-fill' : 'bi-camera-fill',
            'url' => "/doctor/patients",
            'color' => 'success',
            'assigned_at' => now()->toIso8601String(),
        ];
    }
}
