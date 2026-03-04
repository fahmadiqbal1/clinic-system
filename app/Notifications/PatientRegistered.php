<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PatientRegistered extends Notification
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
            'title' => 'New Patient Registered',
            'message' => "Patient {$this->patientName} has been registered and assigned to you.",
            'icon' => 'bi-person-plus',
            'url' => "/doctor/patients/{$this->patientId}",
            'color' => 'primary',
            'assigned_at' => now()->toIso8601String(),
        ];
    }
}
