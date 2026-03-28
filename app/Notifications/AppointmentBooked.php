<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AppointmentBooked extends Notification
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
            'title' => 'Appointment Scheduled',
            'message' => "Appointment for {$this->appointment->patient->full_name} with Dr. {$this->appointment->doctor->name} on " . $this->appointment->scheduled_at->format('d M Y, H:i'),
            'icon' => 'bi-calendar-check',
            'url' => '/receptionist/appointments/' . $this->appointment->id,
            'color' => 'primary',
            'assigned_at' => now()->toIso8601String(),
        ];
    }
}
