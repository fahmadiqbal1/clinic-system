<?php

namespace App\Notifications;

use App\Models\InventoryItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LowStockAlert extends Notification
{
    use Queueable;

    public function __construct(
        public InventoryItem $item,
        public int $currentStock,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $deptLabel = ucfirst($this->item->department);

        return [
            'title' => "Low Stock — {$this->item->name}",
            'message' => "{$this->item->name} ({$deptLabel}) is at {$this->currentStock} {$this->item->unit}. Minimum level is {$this->item->minimum_stock_level}.",
            'icon' => 'bi-exclamation-triangle',
            'url' => '/inventory?department=' . $this->item->department,
            'color' => 'warning',
            'assigned_at' => now()->toIso8601String(),
        ];
    }
}
