<?php

namespace App\Mail;

use App\Models\ExternalLab;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExternalLabCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ExternalLab $lab,
        public readonly string $plainPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Portal Login — ' . config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.external-lab-credentials');
    }
}
