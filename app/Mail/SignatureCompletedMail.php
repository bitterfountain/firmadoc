<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SignatureCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $signerName,
        public string $documentName,
        public string $pdfBytes,
        public string $pdfFileName,
        public bool $padesApplied,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Documento completado: {$this->documentName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.signature-completed',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfBytes, $this->pdfFileName)
                ->withMime('application/pdf'),
        ];
    }
}
