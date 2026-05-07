<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PatientStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $patientId,
        public readonly string $patientName,
        public readonly string $status,
        public readonly string $location,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('triage')];
    }

    public function broadcastAs(): string
    {
        return 'PatientStatusChanged';
    }
}
