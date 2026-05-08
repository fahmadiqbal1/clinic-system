<?php

namespace App\Mail;

use App\Models\ExternalLab;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExternalLabRegisteredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ExternalLab $lab) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Partnership Registered — ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.external-lab-registered',
        );
    }
}
