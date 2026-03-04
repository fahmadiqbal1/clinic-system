<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProcurementAwaitingApproval extends Notification
{
    use Queueable;

    public function __construct(
        public int $procurementId,
        public string $department,
        public string $type,
        public string $requesterName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $deptLabel = ucfirst($this->department);
        $typeLabel = ucfirst($this->type);

        return [
            'title' => "Procurement Request — {$deptLabel}",
            'message' => "{$this->requesterName} submitted a {$typeLabel} procurement request for {$deptLabel}.",
            'icon' => 'bi-cart3',
            'url' => "/procurement/{$this->procurementId}",
            'color' => 'info',
            'assigned_at' => now()->toIso8601String(),
        ];
    }
}
