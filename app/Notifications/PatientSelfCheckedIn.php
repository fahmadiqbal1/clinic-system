<?php

namespace App\Notifications;

use App\Models\Patient;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PatientSelfCheckedIn extends Notification
{
    use Queueable;

    public function __construct(public readonly Patient $patient) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Patient Arrived',
            'message' => $this->patient->full_name . ' has self-checked in and is waiting.',
            'icon' => 'bi-person-check',
            'url' => route('triage.dashboard'),
            'color' => 'warning',
            'assigned_at' => now()->toIso8601String(),
        ];
    }
}
