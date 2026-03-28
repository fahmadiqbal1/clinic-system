<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AppointmentCancelled extends Notification
{
    use Queueable;

    public function __construct(public readonly Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Appointment Cancelled',
            'message' => "Appointment for {$this->appointment->patient->full_name} with Dr. {$this->appointment->doctor->name} was cancelled. Reason: {$this->appointment->cancellation_reason}",
            'icon' => 'bi-calendar-x',
            'url' => '/receptionist/appointments/' . $this->appointment->id,
            'color' => 'danger',
            'assigned_at' => now()->toIso8601String(),
        ];
    }
}
