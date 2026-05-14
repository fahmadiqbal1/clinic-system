<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a critical AI finding creates an AiActionRequest that requires
 * the Owner's immediate attention (e.g. ComplianceAI NON_COMPLIANT, OpsAI
 * critical stock-out draft failed, etc.).
 *
 * Broadcasts on a private per-owner channel so each owner's browser
 * can receive a push notification without polling.
 */
class AiCriticalAlert implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $ownerId,
        public readonly string $title,
        public readonly string $message,
        public readonly string $icon  = 'bi-exclamation-triangle',
        public readonly string $color = 'danger',
        public readonly string $url   = '/owner/ai-oversight',
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("owner-alerts.{$this->ownerId}")];
    }

    public function broadcastAs(): string
    {
        return 'AiCriticalAlert';
    }

    public function broadcastWith(): array
    {
        return [
            'title'   => $this->title,
            'message' => $this->message,
            'icon'    => $this->icon,
            'color'   => $this->color,
            'url'     => $this->url,
        ];
    }
}
