<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PrescriptionCreated extends Notification
{
    use Queueable;

    public function __construct(
        public int $prescriptionId,
        public string $patientName,
        public string $doctorName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Prescription',
            'message' => "Dr. {$this->doctorName} prescribed medications for {$this->patientName}.",
            'icon' => 'bi-prescription2',
            'url' => "/pharmacy/prescriptions/{$this->prescriptionId}",
            'color' => 'warning',
            'assigned_at' => now()->toIso8601String(),
        ];
    }
}
