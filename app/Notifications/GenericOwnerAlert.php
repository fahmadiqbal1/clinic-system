<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GenericOwnerAlert extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $message,
        public readonly string $icon  = 'bi-info-circle',
        public readonly string $color = 'info',
        public readonly string $url   = '/owner/dashboard',
        public readonly string $title = 'System Notice',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'       => $this->title,
            'message'     => $this->message,
            'icon'        => $this->icon,
            'url'         => $this->url,
            'color'       => $this->color,
            'assigned_at' => now()->toIso8601String(),
        ];
    }
}
