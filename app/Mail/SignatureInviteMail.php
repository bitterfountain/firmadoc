<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SignatureInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $signerName,
        public string $documentName,
        public string $signUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Te han solicitado firmar: {$this->documentName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.signature-invite',
        );
    }
}
