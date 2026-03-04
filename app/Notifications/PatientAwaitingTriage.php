<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PatientAwaitingTriage extends Notification
{
    use Queueable;

    public function __construct(
        public string $patientName,
        public int $patientId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Patient Awaiting Triage',
            'message' => "{$this->patientName} has been registered and needs vitals assessment.",
            'icon' => 'bi-clipboard2-pulse',
            'url' => "/triage/patients/{$this->patientId}/vitals",
            'color' => 'warning',
            'assigned_at' => now()->toIso8601String(),
        ];
    }
}
