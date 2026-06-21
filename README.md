# FirmaDoc

Plataforma de firma electrónica de documentos. Acepta **PDF, DOCX/DOC/ODT e imágenes (JPG/PNG)**, normaliza todo a PDF, permite previsualizar, marcar zonas de firma, dibujar la firma e incrustarla en el documento.

---

## Arquitectura

```
Subida (PDF / DOCX / JPG / PNG)
        │  Laravel
   ¿Ya es PDF? ──no──► soffice --convert-to pdf   (LibreOffice headless)
        │
        ▼
   PDF normalizado  ──► storage privado + fila en MySQL
        │  Navegador (Blade + Vite)
   PDF.js previsualiza → se marca la zona → signature_pad captura la firma
        │
        ▼
   pdf-lib incrusta la firma en el PDF → se sube a Laravel → MySQL
        │  (opcional, solo cuentas Pro)
   pyHanko sella el PDF con PAdES (firma criptográfica X.509)
```

- **Backend:** Laravel 12 + MySQL. Conversión a PDF vía `Symfony\Process` con LibreOffice.
- **Frontend:** Blade + Vite. `pdfjs-dist` (preview), `signature_pad` (firma), `pdf-lib` (incrustado en navegador).
- **Almacenamiento:** Disco local o S3/DigitalOcean Spaces (configurable en `DOCSIGNER_DISK`).

---

## Niveles de firma electrónica

### Nivel 0 — Firma rápida (simple, anónima)

Firma visual sin registro ni verificación de identidad. Flujo efímero (120 min), sin persistencia en BD. Ideal para consentimientos, recibís, autorizaciones y uso cotidiano.

- Subida de documento → firma en navegador → descarga directa o envío por email opcional.
- Sin OTP, sin PAdES, sin almacenamiento permanente.
- Acceso: público, desde `/firmar`.

### Nivel 1 — Firma avanzada con verificación de identidad

Requiere cuenta Pro. Antes de firmar, el firmante verifica su identidad con un código OTP de 6 dígitos enviado por email. Se registra en base de datos: firmante, email, IP, navegador, timestamp y hashes SHA-256 del documento. Se anexa una página-certificado al PDF con los datos de auditoría.

- OTP con 5 intentos máximos y caducidad de 10 minutos.
- Verificación reforzada opcional: **SMS** como segundo factor, **subida de documento de identidad** (DNI/pasaporte) adjunto al evento de firma, y **certificado propio** del firmante (.p12/.pfx) para PAdES con su clave.
- Panel de auditoría por documento con historial completo de eventos.

### Nivel 2 — PAdES criptográfica

Sellado final en servidor con **pyHanko** (`scripts/pades_sign.py`). Firma X.509 que detecta cualquier manipulación posterior (tamper-evident). Degrada con elegancia a Nivel 1 si pyHanko no está disponible.

- **PAdES-T**: sello de tiempo RFC 3161 si se configura `PADES_TSA_URL`.
- **PAdES-LTA**: validación a largo plazo (DSS + document timestamp) si `PADES_LTV=true`.

### Nivel 3 — Cualificada (QES)

Backend de firma **enchufable** (`PADES_BACKEND`):

| Backend | Descripción |
|---------|-------------|
| `pemder` | Clave + certificado PEM (autofirmado o de CA) |
| `pkcs12` | Certificado .p12/.pfx de proveedor cualificado |
| `pkcs11` | Token físico / HSM / DNIe (requiere librería del módulo) |

Los usuarios Pro pueden cargar su propio certificado `.p12` desde el panel de certificados, usado para firmar sus documentos con su identidad criptográfica.

---

## Flujos de firma

### Firma única (propietario)

El dueño del documento (cuenta Pro) sube, verifica su identidad con OTP y firma. Es el flujo por defecto al crear un documento desde el dashboard.

### Firma rápida (anónima)

Acceso sin registro desde `/firmar`. Dos modalidades:

1. **Firma única:** sube, firma, descarga. Sin BD, efímero.
2. **Multi-firmante:** sube un documento, añade varios firmantes (nombre + email), cada uno recibe un enlace privado. Firman sin cuenta y sin OTP (Nivel 0). El documento se almacena temporalmente (7 días). Gestión desde panel público con enlaces de firma y estado.

### Multi-firmante con cuenta Pro

El propietario añade firmantes desde `/documents/{id}/signers`. A cada uno se le envía un enlace privado (`/sign/{token}`) por email con verificación OTP.

**Modos de firma:**
- **Secuencial** (por defecto): los firmantes firman en orden. Cada uno espera a que el anterior complete. El último dispara el sellado PAdES.
- **Paralelo:** todos los firmantes pueden firmar en cualquier orden. Ideal para contratos sin dependencia entre firmantes.

Ambos modos son intercambiables en caliente desde la página de gestión de firmantes.

### Portal del firmante

Cada firmante invitado tiene un portal personal accesible desde su enlace (`/sign/{token}`):
- Ve el estado de todos los firmantes (quién ha firmado, quién falta).
- Puede **declinar** la firma si no desea participar.
- Al completarse todas las firmas, recibe el PDF firmado por email.

---

## Verificación de identidad

| Método | Descripción | Configuración |
|--------|-------------|---------------|
| Email OTP | Código de 6 dígitos al email. Siempre activo en Nivel 1. | Por defecto |
| SMS | Código por SMS como segundo factor. Requiere integración con Twilio/Vonage. | Placeholder listo |
| Documento identidad | El firmante sube foto/PDF de su DNI o pasaporte como evidencia adjunta al evento. | Sin configuración |
| Certificado propio | El firmante invitado puede cargar su .p12/.pfx para firmar con su identidad criptográfica. | Sin configuración |

---

## Ciclo de vida de invitaciones

- **Caducidad configurable:** cada invitación tiene `expires_at` (por defecto 30 días).
- **Recordatorios automáticos:** comando `php artisan firmadoc:send-reminders --days=3` envía emails a firmantes pendientes que no han sido recordados recientemente.
- **Declinar:** el firmante puede rechazar la invitación, quedando registrado en el documento.
- **Eliminación:** el propietario puede quitar firmantes pendientes o declinados en cualquier momento.

---

## Testigo (witness)

El propietario puede designar un testigo desde la página de firmantes. El testigo:
1. Recibe un email con un enlace de confirmación.
2. Confirma con un click, quedando registrado como testigo del documento.
3. Al completarse todas las firmas, recibe notificación de que el proceso ha finalizado.

---

## Webhooks

Cada documento puede tener una URL de webhook. Se envían notificaciones POST JSON en estos eventos:

| Evento | Payload |
|--------|---------|
| `signer_declined` | `name`, `email`, `position` |
| `signer_completed` | `name`, `email`, `all_signed`, `remaining` |
| `document_completed` | `document_name`, `signers`, `pades_applied` |

---

## Panel de auditoría

Cada documento tiene un panel de auditoría (`/documents/{id}/audit`) accesible solo por el propietario, con el historial completo de eventos de firma:

- Referencia única del evento (`DS-00007-A1B2C3`)
- Firmante, email, IP, navegador
- Fecha y hora de verificación
- Método de verificación empleado
- Hash SHA-256 del documento (original y firmado)
- Indicador de sellado PAdES
- Documento de identidad adjunto (si se subió)

---

## Sistema de cuentas

- **Registro cerrado:** las cuentas Pro se crean mediante enlaces de invitación de un solo uso generados por el administrador, o respondiendo a solicitudes de acceso desde `/solicitar-pro`.
- **Caducidad de cuenta:** cada cuenta tiene `pro_until`. Las cuentas gratuitas de lanzamiento no caducan.
- **Middleware `EnsureProActive`:** bloquea el acceso a funcionalidades Pro si la cuenta ha expirado.

---

## Requisitos

- PHP 8.2+, Composer, Node 18+, MySQL 8+/9.
- **LibreOffice** (solo para convertir DOCX e imágenes; el flujo PDF no lo necesita).
- **Python 3 + pyHanko** (solo para PAdES/Nivel 2; opcional).

---

## Puesta en marcha (desarrollo)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build          # o `npm run dev` para hot reload
php artisan serve
```

---

## Despliegue en Ubuntu

```bash
sudo apt update
sudo apt install -y libreoffice --no-install-recommends
```

En `.env`:

```ini
SOFFICE_PATH=/usr/bin/soffice
```

---

## Setup del Nivel 2/3 (PAdES)

```bash
pip install -r requirements.txt      # pyHanko
```

```ini
# .env
PADES_ENABLED=true
PYTHON_PATH=python3
PADES_BACKEND=pemder                  # pemder | pkcs12 | pkcs11

# PAdES-T con sello de tiempo (opcional)
PADES_TSA_URL=http://timestamp.digicert.com

# PAdES-LTA (opcional, requiere TSA)
PADES_LTV=true

# pkcs12 (certificado cualificado en .p12/.pfx)
# PADES_BACKEND=pkcs12
# PADES_P12=storage/app/certs/cert.p12
# PADES_P12_PASS=...

# pkcs11 (token / HSM / DNIe -> QES)
# PADES_BACKEND=pkcs11
# PADES_PKCS11_LIB=/usr/lib/opensc-pkcs11.so
# PADES_PKCS11_CERT_LABEL=...
# PADES_PKCS11_PIN=...
```

---

## Certificado de confianza para producción

El salto de mayor valor por menor coste: un **certificado de firma de organización** de una CA reconocida (~100-400 €/año). Hace que todas las firmas sean **AdES de confianza** (verde en Adobe).

1. Compra un *document-signing certificate* en una CA de la **Adobe AATL** o la **EU Trusted List** (DigiCert, GlobalSign, Sectigo, Uanataca…).
2. Coloca el `.p12` en `storage/app/certs/` y configura:
   ```ini
   PADES_BACKEND=pkcs12
   PADES_P12=storage/app/certs/empresa.p12
   PADES_P12_PASS=tu-clave
   ```
3. Verifica: `php artisan docsigner:cert-info --p12=storage/app/certs/empresa.p12 --pass=tu-clave`

---

## Email

En desarrollo `MAIL_MAILER=log` escribe el OTP en `storage/logs/laravel.log`.

En producción el sistema soporta:
- **SMTP directo** (`MAIL_MAILER=smtp`)
- **Relay HTTP** personalizado (`MAIL_MAILER=relay`) para entornos con SMTP bloqueado (p.ej. DigitalOcean). El transporte `App\Mail\Transport\RelayTransport` envía vía POST JSON a un endpoint externo. Configurar con `MAIL_RELAY_URL` y `MAIL_RELAY_TOKEN`.

---

## Comandos Artisan

| Comando | Descripción |
|---------|-------------|
| `firmadoc:send-reminders --days=3` | Envía recordatorios a firmantes pendientes que no han firmado en los últimos N días |
| `docsigner:make-cert` | Genera un par de claves autofirmado para PAdES (pemder) |
| `docsigner:cert-info --p12=... --pass=...` | Muestra información de un certificado .p12 |

---

## Tests

```bash
php artisan test          # SQLite en memoria, 40 tests, sin tocar MySQL
```

Cubren: subida/conversión, validación de formato, flujo OTP (correcto, incorrecto, caducado), finalize, panel de auditoría, flujo multi-firmante completo, firma rápida anónima, certificados de firma, invitaciones Pro, tracking de visitas y PAdES.

---

## Piezas clave

| Archivo | Rol |
|---------|-----|
| `app/Http/Controllers/QuickSignController.php` | Firma rápida anónima (Nivel 0) + multi-firmante sin cuenta |
| `app/Http/Controllers/SignatureController.php` | Firma propia con OTP del dueño del documento |
| `app/Http/Controllers/InvitationController.php` | Multi-firmante: gestión, flujo público por token, testigo, webhooks |
| `app/Http/Controllers/DocumentController.php` | CRUD de documentos y panel de auditoría |
| `app/Http/Controllers/SigningCertController.php` | Gestión de certificados .p12 del usuario |
| `app/Services/PdfConversionService.php` | Normaliza cualquier entrada a PDF (LibreOffice) |
| `app/Services/PadesSigningService.php` | Sellado criptográfico PAdES con pyHanko |
| `app/Models/Document.php` | Documento: estado, modo de firma, testigo, webhook |
| `app/Models/SignatureInvitation.php` | Invitación a firmante: token, posición, ciclo de vida |
| `app/Models/SignatureEvent.php` | Evento de firma: OTP, verificación, hashes, certificados |
| `app/Mail/SignatureInviteMail.php` | Email de invitación a firmar |
| `app/Mail/SignatureOtpMail.php` | Email con código OTP |
| `app/Mail/SignatureCompletedMail.php` | Email con PDF firmado al completar todas las firmas |
| `app/Mail/SignatureReminderMail.php` | Email de recordatorio para firmantes pendientes |
| `app/Mail/WitnessNotificationMail.php` | Email de notificación al testigo |
| `app/Console/Commands/SendSignatureReminders.php` | Comando de recordatorios programados |
| `resources/js/sign.js` | Preview PDF + marcado de zona + incrustado de firma en navegador |
| `config/docsigner.php` | Configuración: rutas, formatos, PAdES, almacenamiento |

---

## Notas técnicas

- **InnoDB forzado** en `config/database.php`: el servidor MySQL tiene MyISAM por defecto y con `utf8mb4` un `varchar(255)` no cabe en el límite de clave de 1000 bytes.
- Los archivos se guardan en disco privado y se sirven vía controlador, nunca públicamente.
- **PAdES en Windows:** `PadesSigningService` reinyecta `SystemRoot` al subproceso para evitar `WinError 10106` al iniciar Python.
- Las migraciones se ejecutan con `--force` en producción.
- El código sigue el estándar de estilo Laravel (Pint).
