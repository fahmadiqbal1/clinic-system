<?php

namespace App\Notifications;

use App\Models\DoctorPayout;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutDecision extends Notification
{
    use Queueable;

    const DECISION_APPROVED = 'approved';
    const DECISION_REJECTED = 'rejected';

    public function __construct(
        public DoctorPayout $payout,
        public string $decision,
        public ?string $notes = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = 'Rs. ' . number_format($this->payout->paid_amount, 2);
        $period = $this->payout->period_start->format('M d') . ' — ' . $this->payout->period_end->format('M d, Y');

        if ($this->decision === self::DECISION_APPROVED) {
            return (new MailMessage)
                ->subject('Payout Approved — ' . config('app.name'))
                ->greeting("Hello {$notifiable->name},")
                ->line("Your payout of **{$amount}** for {$period} has been **approved** and will be processed.")
                ->action('View Payout', url("/payouts/{$this->payout->id}"))
                ->line('Thank you for your service.');
        }

        return (new MailMessage)
            ->subject('Payout Rejected — ' . config('app.name'))
            ->greeting("Hello {$notifiable->name},")
            ->line("Your payout of **{$amount}** for {$period} has been **rejected**.")
            ->when($this->notes, fn ($msg) => $msg->line("Reason: {$this->notes}"))
            ->action('View Payout', url("/payouts/{$this->payout->id}"))
            ->line('Please contact your administrator for further details.');
    }

    public function toArray(object $notifiable): array
    {
        $amount = 'Rs. ' . number_format($this->payout->paid_amount, 2);
        $period = $this->payout->period_start->format('M d') . ' — ' . $this->payout->period_end->format('M d, Y');

        if ($this->decision === self::DECISION_APPROVED) {
            return [
                'title' => 'Payout Approved',
                'message' => "Your payout of {$amount} for {$period} has been approved and will be processed.",
                'icon' => 'bi-wallet2',
                'url' => "/payouts/{$this->payout->id}",
                'color' => 'success',
                'assigned_at' => now()->toIso8601String(),
            ];
        }

        return [
            'title' => 'Payout Rejected',
            'message' => "Your payout of {$amount} for {$period} was rejected." . ($this->notes ? " Reason: {$this->notes}" : ''),
            'icon' => 'bi-wallet2',
            'url' => "/payouts/{$this->payout->id}",
            'color' => 'danger',
            'assigned_at' => now()->toIso8601String(),
        ];
    }
}
