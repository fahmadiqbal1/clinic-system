<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DiscountApprovalRequested extends Notification
{
    use Queueable;

    public function __construct(
        public int $invoiceId,
        public string $patientName,
        public float $discountAmount,
        public string $requesterName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Discount Approval Needed',
            'message' => "{$this->requesterName} requested a discount of " . number_format($this->discountAmount, 2) . " for {$this->patientName}.",
            'icon' => 'bi-percent',
            'url' => '/owner/discount-approvals',
            'color' => 'warning',
            'assigned_at' => now()->toIso8601String(),
        ];
    }
}
