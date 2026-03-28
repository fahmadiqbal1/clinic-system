<?php

namespace App\Channels;

use App\Jobs\SendSmsJob;
use Illuminate\Notifications\Notification;

class TwilioSmsChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        $sid   = (string) config('twilio.sid', '');
        $token = (string) config('twilio.token', '');
        $from  = (string) config('twilio.from', '');

        if (empty($sid) || empty($token) || empty($from)) {
            return;
        }

        // Resolve patient phone (encrypted cast auto-decrypts)
        $phone = null;
        if (method_exists($notifiable, 'patient') && $notifiable->patient !== null) {
            $phone = $notifiable->patient->phone ?? null;
        }
        if (empty($phone)) {
            return;
        }

        if (!method_exists($notification, 'toTwilio')) {
            return;
        }

        /** @var string $message */
        $message = $notification->toTwilio($notifiable);
        if (empty($message)) {
            return;
        }

        SendSmsJob::dispatch($phone, $message);
    }
}
