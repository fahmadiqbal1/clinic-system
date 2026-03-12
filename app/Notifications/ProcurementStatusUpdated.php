<?php

namespace App\Notifications;

use App\Models\ProcurementRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProcurementStatusUpdated extends Notification
{
    use Queueable;

    // Possible events
    const EVENT_APPROVED = 'approved';
    const EVENT_REJECTED = 'rejected';
    const EVENT_RECEIVED = 'received';

    public function __construct(
        public ProcurementRequest $procurement,
        public string $event,
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $deptLabel = ucfirst($this->procurement->department);
        $itemCount = $this->procurement->items()->count();

        return match ($this->event) {
            self::EVENT_APPROVED => [
                'title' => "Procurement Approved — {$deptLabel}",
                'message' => "Your procurement request for {$itemCount} item(s) has been approved. Goods can now be ordered.",
                'icon' => 'bi-cart-check',
                'url' => "/procurement/{$this->procurement->id}",
                'color' => 'success',
                'assigned_at' => now()->toIso8601String(),
            ],
            self::EVENT_REJECTED => [
                'title' => "Procurement Rejected — {$deptLabel}",
                'message' => "Your procurement request was rejected." . ($this->reason ? " Reason: {$this->reason}" : ''),
                'icon' => 'bi-cart-x',
                'url' => "/procurement/{$this->procurement->id}",
                'color' => 'danger',
                'assigned_at' => now()->toIso8601String(),
            ],
            self::EVENT_RECEIVED => [
                'title' => "Stock Received — {$deptLabel}",
                'message' => "Your procurement request items have been received and inventory has been updated.",
                'icon' => 'bi-box-seam',
                'url' => "/procurement/{$this->procurement->id}",
                'color' => 'info',
                'assigned_at' => now()->toIso8601String(),
            ],
            default => [
                'title' => "Procurement Updated — {$deptLabel}",
                'message' => "Your procurement request status has changed.",
                'icon' => 'bi-cart3',
                'url' => "/procurement/{$this->procurement->id}",
                'color' => 'secondary',
                'assigned_at' => now()->toIso8601String(),
            ],
        };
    }
}
