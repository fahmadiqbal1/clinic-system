<?php

namespace App\Notifications;

use App\Models\DoctorPayout;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutGenerated extends Notification
{
    use Queueable;

    public function __construct(
        public DoctorPayout $payout,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payout Generated — ' . config('app.name'))
            ->greeting("Hello {$notifiable->name},")
            ->line("A payout of **" . number_format($this->payout->paid_amount, 2) . "** has been generated for you.")
            ->line("Period: {$this->payout->period_start->format('M d, Y')} — {$this->payout->period_end->format('M d, Y')}")
            ->line("Total eligible: " . number_format($this->payout->total_amount, 2))
            ->action('View & Confirm Payout', url("/payouts/{$this->payout->id}"))
            ->line('Please review and confirm this payout at your earliest convenience.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Payout Generated',
            'message' => "A payout of " . number_format($this->payout->paid_amount, 2) . " has been generated for you. Please review and confirm.",
            'icon' => 'bi-wallet2',
            'url' => "/payouts/{$this->payout->id}",
            'color' => 'success',
            'payout_id' => $this->payout->id,
            'amount' => $this->payout->paid_amount,
        ];
    }
}
