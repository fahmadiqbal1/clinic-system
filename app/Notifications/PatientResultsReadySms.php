<?php

namespace App\Notifications;

use App\Channels\TextBeeSmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PatientResultsReadySms extends Notification
{
    use Queueable;

    public function __construct(public readonly string $department) {}

    public function via(object $notifiable): array
    {
        return ['database', TextBeeSmsChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => ucfirst($this->department) . ' Results Ready',
            'message' => 'Your ' . ucfirst($this->department) . ' test results are ready. Log in to view them.',
            'icon' => 'bi-clipboard2-pulse',
            'url' => '/patient/dashboard',
            'color' => 'success',
            'assigned_at' => now()->toIso8601String(),
        ];
    }

    public function toTextBee(object $notifiable): string
    {
        return 'Aviva HealthCare: Your ' . ucfirst($this->department) . ' test results are ready. Please log in to your patient portal to view them.';
    }
}
