<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MorningBriefMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User  $owner,
        public readonly array $brief,
    ) {}

    public function envelope(): Envelope
    {
        $date = now()->format('d M');
        return new Envelope(subject: "Aviva Morning Brief — {$date}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.morning-brief');
    }
}
