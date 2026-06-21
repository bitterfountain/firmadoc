# Estado de desarrollo — FirmaDoc

> Última actualización: 2026-06-21
> Commit de referencia: `8c8e00d`
> Rama: `main`

---

## Resumen del sprint 2026-06-21

Implementación de 7 mejoras (A–G) sobre el sistema de firma multi-firmante.

### A. Modo de firma paralela

- **Columna:** `documents.signing_mode` (`sequential` | `parallel`).
- **Selector** en la página de firmantes (`/documents/{id}/signers`) — cambiable en caliente.
- **Lógica:** `SignatureInvitation::isMyTurn()` devuelve `true` para todos los pendientes si el documento está en modo `parallel`.
- **Vista:** `resources/views/documents/signers.blade.php` incluye `<select>` con auto-submit.
- **Ruta:** `POST /documents/{document}/signing-mode` → `InvitationController@updateMode`.

### B. Verificación de identidad reforzada

- **Columnas nuevas en `signature_events`:**
  - `verification_method` — `email`, `sms`, `email_sms`.
  - `phone`, `phone_verified_at` — para SMS (placeholder, requiere integración Twilio/Vonage).
  - `id_document_path` — ruta en storage del DNI/pasaporte subido por el firmante.
  - `signing_cert`, `signing_cert_password`, `signing_cert_subject` — certificado .p12/.pfx propio del firmante invitado.
- **Endpoint OTP:** acepta `method=sms` y `phone`. El método `sendSmsOtp()` es un placeholder.
- **Endpoint verify OTP:** acepta `id_document` (archivo). Lo guarda en `documents/{id}/id_docs/{event_id}/`.
- **Endpoint finalize:** acepta `signing_cert` (.p12/.pfx) y `cert_password`. Se guarda en el evento y se usa en PAdES con prioridad sobre el certificado del dueño.

### C. Ciclo de vida de invitaciones

- **Columnas nuevas en `signature_invitations`:**
  - `expires_at` — fecha de caducidad (por defecto 30 días, configurable al añadir firmante).
  - `declined_at` — timestamp cuando el firmante declina.
  - `last_reminded_at` — última vez que se envió recordatorio.
- **Declinar:** `POST /sign/{token}/decline` → `InvitationController@decline`. Dispara webhook `signer_declined`.
- **Caducidad:** `SignatureInvitation::isExpired()` y chequeo en `isMyTurn()`.
- **Recordatorios:** comando `firmadoc:send-reminders --days=N`. Envía `SignatureReminderMail` a pendientes no recordados en N días.
- **Vista de gestión:** campo `expires_at` (días) y campo `phone` en el formulario de añadir firmante.

### D. Portal del firmante + comprobante de auditoría

- **Vista nueva:** `resources/views/invitations/portal.blade.php`.
  - Muestra estado de TODOS los firmantes (quién firmó, quién está pendiente, quién declinó).
  - Si el firmante ya firmó: mensaje de confirmación + fecha + botón de descarga si el documento está completado.
  - Si no es su turno: mensaje + botón de declinar.
- **Email de completado:** `SignatureCompletedMail` — se envía a CADA firmante cuando TODOS han firmado. Adjunta el PDF firmado.
- **Notificación al testigo:** si hay testigo designado, recibe email cuando se completan todas las firmas.

### E. Documentos multi-firma sin cuenta Pro (quick multi-sign)

- **Nueva pestaña** en `quick/start.blade.php`: "Firma única" | "Multi-firmante".
- **Flujo multi:**
  1. Usuario sube documento + lista de firmantes (nombre + email dinámicos, botón "Añadir").
  2. Elige modo `parallel` o `sequential`.
  3. Se crea `Document` con `user_id = null`, `signing_mode` elegido.
  4. Se crean `SignatureInvitation` con tokens, `expires_at = +7 días`.
  5. Se envían emails de invitación (`SignatureInviteMail`).
  6. Redirige a `quick/manage.blade.php` — panel público con enlaces y estado.
- **Vista:** `resources/views/quick/manage.blade.php` — lista de firmantes con estado, enlaces de firma, botón de descarga cuando completado.
- **Descarga:** `GET /firmar/multi/{id}/descargar` → `QuickSignController@multiDownload`.
- **Purga:** prevista a 7 días (no implementada como comando automático aún; los documentos quedan).

### F. Firma con testigo (witness)

- **Columnas nuevas en `documents`:**
  - `witness_name`, `witness_email`, `witness_token` (64 chars, unique).
  - `witness_confirmed_at` — timestamp de confirmación.
- **Flujo:**
  1. Dueño añade testigo desde página de firmantes (`POST /documents/{document}/witness`).
  2. Se envía `WitnessNotificationMail` con enlace `GET /witness/{token}`.
  3. Testigo hace click → `witness_confirmed_at = now()`.
  4. Al completar todas las firmas, testigo recibe segundo email notificando finalización.
- **Vista de gestión:** sección "Testigo" en `documents/signers.blade.php`.

### G. Webhooks

- **Columna:** `documents.webhook_url`.
- **Configuración:** desde página de firmantes (`POST /documents/{document}/webhook`).
- **Notificaciones POST JSON:** `signer_declined`, `signer_completed`, `document_completed`.
- **Método:** `InvitationController::notifyWebhook()` — `Http::timeout(10)->post(...)`. Fail-safe: captura excepciones, no rompe el flujo.
- **Payload incluye:** `event`, `document_id`, `document_name`, y datos específicos del evento.

---

## Estado actual del código

### Migraciones (todas ejecutadas en local y producción)

| Migración | Estado |
|-----------|--------|
| `0001_01_01_000000_create_users_table` | Ran |
| `0001_01_01_000001_create_cache_table` | Ran |
| `0001_01_01_000002_create_jobs_table` | Ran |
| `2026_06_06_085359_create_documents_table` | Ran |
| `2026_06_06_123105_create_signature_events_table` | Ran |
| `2026_06_06_124452_add_pades_applied_to_signature_events` | Ran |
| `2026_06_06_204604_create_signature_invitations_table` | Ran |
| `2026_06_06_204605_add_invitation_id_to_signature_events` | Ran |
| `2026_06_07_120000_add_user_id_to_documents_table` | Ran |
| `2026_06_08_100000_add_pro_fields_to_users_table` | Ran |
| `2026_06_08_100100_create_account_invites_table` | Ran |
| `2026_06_08_120000_create_pro_requests_table` | Ran |
| `2026_06_08_140000_create_page_visits_table` | Ran |
| `2026_06_08_160000_add_signing_cert_to_users_table` | Ran |
| `2026_06_21_000000_add_signing_features` | Ran |

### Tests: 40 pasando, 175 assertions

```
Tests\Unit\ExampleTest              ✓ that true is true
Tests\Unit\SignatureLogicTest       ✓ reference format, pades disabled, pemder requires key/cert
Tests\Feature\DocumentTest          ✓ index, home redirect, upload, reject, sign, pdf 404, delete, other user
Tests\Feature\InviteTest            ✓ pro account, single use, expired blocked, admin only
Tests\Feature\MultiSignerTest       ✓ invite signers, sequential turns, full flow, done message
Tests\Feature\PadesRemoteTest       ✓ enabled config, remote delegation
Tests\Feature\ProRequestTest        ✓ request, admin invite, non-admin blocked
Tests\Feature\QuickSignTest         ✓ ephemeral, email delivery, expired session
Tests\Feature\SignatureFlowTest     ✓ full OTP, wrong OTP, expired OTP, finalize requires verified, audit
Tests\Feature\SigningCertTest       ✓ login required, upload, wrong password, remove
Tests\Feature\VisitTrackingTest     ✓ tracked, bots ignored, admin only
```

### Assets compilados (Vite)

- `public/build/assets/app-DsbP9Jus.js`
- `public/build/assets/app-Bio_6821.css`
- `public/build/assets/sign-RW7n793-.js`
- `public/build/assets/pdf.worker.min-CrMmvqMo.mjs`

---

## Servidores

### Producción

- **URL:** https://firmadoc.leukasoft.com
- **Host:** DigitalOcean droplet `159.223.14.50`
- **Proyecto:** `/var/www/html/firmadoc.leukasoft.com`
- **Stack:** nginx + php8.3-fpm + Let's Encrypt
- **BD:** DigitalOcean Managed MySQL (`leukasoftamsterdam-do-user-16340060-0.c.db.ondigitalocean.com:25060`)
- **Storage:** DigitalOcean Spaces (`leukasoftstorage`, región `ams3`)
- **Deploy:** `git pull` desde `github.com/bitterfountain/firmadoc` (rama `main`)
- **Permisos:** código `root:root`; `storage/` y `bootstrap/cache/` → `www-data:www-data`

### Email en producción

- **Estado actual:** `MAIL_MAILER=log` (provisional).
- **Relay previsto:** `App\Mail\Transport\RelayTransport` → `https://resend.leukasoft.com/mailrelay.php`.
- **Bloqueante:** `resend.leukasoft.com` no tiene vhost en DirectAdmin → no responde. Pendiente crear el dominio en DirectAdmin.
- **Fallback operativo:** Resend API (`RESEND_API_KEY` en `micrcrm/.env`, remitente `sender@olepyme.com`).

---

## Tareas pendientes

### Alta prioridad

- [ ] **Resolver relay de email:** crear vhost `resend.leukasoft.com` en DirectAdmin para que `mailrelay.php` responda por HTTPS y FirmaDoc pueda enviar emails en producción.
- [ ] **Integrar SMS:** implementar `sendSmsOtp()` en `InvitationController` con Twilio o Vonage.
- [ ] **Comando de purga automática:** para documentos multi-firmante anónimos (quick multi), eliminar documentos con `user_id = null` tras 7 días de la última firma (o de la creación si no se ha firmado).
- [ ] **Botón declinar en página de firma:** actualmente solo visible en el portal cuando no es tu turno. Añadirlo también en la página de firma (`documents/sign.blade.php`) para que el firmante pueda declinar incluso cuando es su turno.

### Media prioridad

- [ ] **Historial de acciones del firmante:** que el portal muestre también los eventos de auditoría (no solo el estado de firmas).
- [ ] **Reenviar invitación:** botón en la gestión de firmantes para reenviar el email de invitación.
- [ ] **Tests para nuevas funcionalidades:** modo paralelo, declinar, testigo, webhooks, quick multi-firmante, verificación SMS/ID/cert.

### Baja prioridad

- [ ] **Recordatorios automáticos vía scheduler:** añadir `firmadoc:send-reminders` al Kernel de consola para ejecución diaria.
- [ ] **Internacionalización:** traducir nuevas vistas y emails a inglés.
- [ ] **Firma cualificada (QES) con proveedor externo:** integrar API de DocuSign/Signicat/Viafirma como alternativa al flujo propio.

---

## Decisiones de arquitectura

1. **Documentos sin dueño (`user_id = null`):** para el flujo quick multi-firmante. Los modelos y controladores ya lo soportan. `DocumentController::authorizeOwner()` solo se llama en rutas que requieren auth.
2. **Certificado del firmante invitado:** se guarda en `SignatureEvent`, no en `User` (el firmante no tiene cuenta). El override de PAdES (`certOverrideForEvent`) da prioridad al certificado del firmante sobre el del dueño.
3. **PAdES solo al último firmante:** para no romper firmas previas al re-sellar.
4. **Webhooks sin colas:** `Http::timeout(10)` síncrono. Si hay latencia, mover a Job encolado.
5. **Migraciones incrementales:** cada feature añade columnas con migraciones independientes en lugar de modificar migraciones existentes.

---

## Configuración relevante de `.env`

```ini
# Documentos
DOCSIGNER_DISK=local                # local | s3
DOCSIGNER_MAX_UPLOAD_KB=20480

# PAdES
PADES_ENABLED=false                 # true en producción con certificado
PADES_BACKEND=pemder
PADES_REMOTE_URL=                   # nodo remoto opcional
PYTHON_PATH=python3

# Email
MAIL_MAILER=log                     # log | smtp | relay
MAIL_RELAY_URL=                     # endpoint del relay HTTP
MAIL_RELAY_TOKEN=                   # token del relay

# LibreOffice
SOFFICE_PATH=                       # ruta a soffice (Windows: ruta completa)
```
