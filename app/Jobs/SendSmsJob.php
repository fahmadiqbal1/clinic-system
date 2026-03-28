<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

/**
 * Queued job to send an SMS via Twilio with automatic retries.
 */
class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30; // seconds between retries

    public function __construct(
        private readonly string $to,
        private readonly string $body,
    ) {}

    public function handle(): void
    {
        $sid   = (string) config('twilio.sid', '');
        $token = (string) config('twilio.token', '');
        $from  = (string) config('twilio.from', '');

        if (empty($sid) || empty($token) || empty($from)) {
            // Not configured — discard silently, no retry
            $this->fail(new \RuntimeException('Twilio credentials not configured.'));
            return;
        }

        (new Client($sid, $token))->messages->create($this->to, [
            'from' => $from,
            'body' => $this->body,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning("SendSmsJob permanently failed to {$this->to}: {$e->getMessage()}");
    }
}
