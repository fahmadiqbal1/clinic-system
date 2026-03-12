<?php

namespace App\Notifications;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ExpiryAlert extends Notification
{
    use Queueable;

    public function __construct(
        public InventoryItem $item,
        public StockMovement $movement,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $daysLeft = (int) now()->diffInDays($this->movement->expiry_date, false);
        $isExpired = $daysLeft < 0;

        $label = $isExpired
            ? "Expired on {$this->movement->expiry_date->format('d M Y')}"
            : "Expires in {$daysLeft} day(s) on {$this->movement->expiry_date->format('d M Y')}";

        $batchSuffix = $this->movement->batch_number ? " (Batch: {$this->movement->batch_number})" : '';

        return [
            'title'       => ($isExpired ? 'Expired Stock' : 'Stock Expiry Warning') . ' — ' . $this->item->name,
            'message'     => "{$this->item->name}{$batchSuffix} — {$label}.",
            'icon'        => $isExpired ? 'bi-exclamation-octagon' : 'bi-hourglass-split',
            'url'         => '/inventory?department=' . $this->item->department,
            'color'       => $isExpired ? 'danger' : ($daysLeft <= 7 ? 'warning' : 'info'),
            'assigned_at' => now()->toIso8601String(),
        ];
    }
}
