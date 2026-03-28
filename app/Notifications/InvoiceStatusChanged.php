<?php

namespace App\Notifications;

use App\Channels\TextBeeSmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InvoiceStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public int $invoiceId,
        public string $oldStatus,
        public string $newStatus,
        public string $department,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (method_exists($notifiable, 'patient') && $notifiable->patient !== null) {
            $channels[] = TextBeeSmsChannel::class;
        }
        return $channels;
    }

    public function toTextBee(object $notifiable): string
    {
        $statusLabel = ucfirst(str_replace('_', ' ', $this->newStatus));
        $deptLabel = ucfirst($this->department);
        return "Aviva HealthCare: Your {$deptLabel} Invoice #{$this->invoiceId} has been updated to {$statusLabel}.";
    }

    public function toArray(object $notifiable): array
    {
        $statusLabel = ucfirst(str_replace('_', ' ', $this->newStatus));
        $deptLabel = ucfirst($this->department);

        return [
            'title' => "{$deptLabel} Invoice #{$this->invoiceId} — {$statusLabel}",
            'message' => "Invoice #{$this->invoiceId} ({$deptLabel}) has been updated to {$statusLabel}.",
            'icon' => 'bi-receipt',
            'url' => null,
            'color' => match ($this->newStatus) {
                'paid' => 'success',
                'completed' => 'info',
                'cancelled' => 'danger',
                default => 'warning',
            },
            'assigned_at' => now()->toIso8601String(),
        ];
    }
}
