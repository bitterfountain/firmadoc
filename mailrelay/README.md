# mailrelay — "Resend casero"

Microservicio de envío de email por HTTP, para servidores cuyo SMTP saliente
está bloqueado (caso del droplet de DigitalOcean donde corre FirmaDoc).

```
FirmaDoc (SMTP bloqueado) ──HTTPS+token──▶ mailrelay.php (servidor con SMTP) ──▶ destinatario
```

## Instalación (en el servidor de correo, p.ej. detrás de mail.leukasoft.com)

1. Sube `mailrelay.php` a una carpeta servida por **HTTPS**, p.ej. `https://mail.leukasoft.com/relay/mailrelay.php`.
2. Instala PHPMailer junto al script:
   ```bash
   composer require phpmailer/phpmailer
   ```
   (o copia `src/Exception.php`, `src/PHPMailer.php`, `src/SMTP.php` en una subcarpeta `src/`).
3. Edita el bloque `$CONFIG` de `mailrelay.php`:
   - `token`: genera uno largo → `openssl rand -hex 32`. (Mejor aún: ponlo en la variable de entorno `RELAY_TOKEN`.)
   - `smtp_*`: datos del SMTP local (`no-reply@leukasoft.com` y su contraseña).
   - `allowed_from_domain`: `leukasoft.com` (evita que el relay sirva para enviar como cualquiera).

## Probar

```bash
curl -s -X POST https://mail.leukasoft.com/relay/mailrelay.php \
  -H "Authorization: Bearer EL_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"to":[{"address":"tucorreo@gmail.com"}],"subject":"test relay","text":"funciona"}'
# -> {"ok":true,"id":"..."}
```

## Conectar FirmaDoc

En el `.env` de FirmaDoc:

```
MAIL_MAILER=relay
MAIL_RELAY_URL=https://mail.leukasoft.com/relay/mailrelay.php
MAIL_RELAY_TOKEN=EL_MISMO_TOKEN
MAIL_FROM_ADDRESS=no-reply@leukasoft.com
MAIL_FROM_NAME=FirmaDoc
```

Después: `php artisan config:cache`. Todo el correo (OTP, invitaciones, PDF
firmado) saldrá por el relay. El transporte está en
`app/Mail/Transport/RelayTransport.php` y se registra en `AppServiceProvider`.

## Notas
- Sólo acepta POST autenticado por Bearer token sobre HTTPS.
- Soporta `html`, `text`, `cc`, `bcc`, `reply_to` y adjuntos (`attachments` en base64) — esto último permite enviar el PDF firmado a los firmantes anónimos.
- Es reutilizable por cualquier otro proyecto del droplet con el mismo problema.
