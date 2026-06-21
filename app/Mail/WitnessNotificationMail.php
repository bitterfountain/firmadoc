<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WitnessNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $witnessName,
        public string $documentName,
        public string $confirmUrl,
        public bool $allSigned = false,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->allSigned
            ? "Todas las firmas completadas: {$this->documentName}"
            : "Te han designado testigo: {$this->documentName}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.witness-notification',
        );
    }
}
