<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * Transporte de correo "relay": envia el email por HTTPS a un script PHP
 * externo (nuestro "Resend casero") que vive en un servidor capaz de mandar
 * SMTP. Necesario porque DigitalOcean bloquea el SMTP saliente del droplet.
 *
 * Contrato (POST JSON, Authorization: Bearer <token>):
 *   {
 *     "from": {"address","name"},
 *     "to":   [{"address","name"}, ...],
 *     "cc": [...], "bcc": [...], "reply_to": [...],
 *     "subject": "...",
 *     "html": "...", "text": "...",
 *     "attachments": [{"filename","content_type","content_base64"}, ...]
 *   }
 * Respuesta OK: HTTP 2xx con {"ok": true, "id": "..."}.
 */
class RelayTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $url,
        private readonly string $token,
        private readonly int $timeout = 30,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if ($this->url === '' || $this->token === '') {
            throw new RuntimeException('Relay de correo sin configurar (MAIL_RELAY_URL / MAIL_RELAY_TOKEN).');
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $from = $this->addresses($email->getFrom());

        $payload = [
            'from' => $from[0] ?? null,
            'to' => $this->addresses($email->getTo()),
            'cc' => $this->addresses($email->getCc()),
            'bcc' => $this->addresses($email->getBcc()),
            'reply_to' => $this->addresses($email->getReplyTo()),
            'subject' => $email->getSubject(),
            'html' => $email->getHtmlBody(),
            'text' => $email->getTextBody(),
            'attachments' => [],
        ];

        foreach ($email->getAttachments() as $attachment) {
            $payload['attachments'][] = [
                'filename' => $attachment->getFilename() ?? 'attachment',
                'content_type' => $attachment->getMediaType().'/'.$attachment->getMediaSubtype(),
                'content_base64' => base64_encode($attachment->getBody()),
            ];
        }

        $response = Http::timeout($this->timeout)
            ->withToken($this->token)
            ->acceptJson()
            ->post($this->url, $payload);

        if (! $response->successful() || $response->json('ok') === false) {
            throw new RuntimeException(
                'El relay de correo rechazo el envio: HTTP '.$response->status().' '.$response->body()
            );
        }
    }

    /** @param  Address[]  $addresses */
    private function addresses(array $addresses): array
    {
        return array_map(
            fn (Address $a) => ['address' => $a->getAddress(), 'name' => $a->getName()],
            $addresses,
        );
    }

    public function __toString(): string
    {
        return 'relay';
    }
}
