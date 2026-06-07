# FirmaDoc — Estado del proyecto

> Última actualización: 2026-06-07. Documento de continuidad: dónde está el proyecto,
> qué está hecho, qué falta y cómo retomarlo. (Ver también `README.md` para setup.)

## Resumen

Plataforma de firma de documentos en **Laravel 12 + MySQL**. Cualquier entrada
(PDF / DOCX / JPG / PNG) se normaliza a PDF y se firma en el navegador; el servidor
gestiona conversión, auditoría y sellado criptográfico. Firma electrónica en 3 niveles
eIDAS + multi-firmante.

## ✅ Hecho (y verificado)

| Área | Estado |
|---|---|
| Subida multiformato → PDF | ✅ LibreOffice (docx/imágenes) vía `PdfConversionService` |
| **Nivel 0** — firma visual multi-firma | ✅ pdf-lib; mover/redimensionar/borrar; previsualización |
| **Nivel 1** — OTP email + auditoría | ✅ `signature_events`, SHA-256, página-certificado, OTP hasheado |
| **Nivel 2** — PAdES criptográfica | ✅ pyHanko; PAdES-B / **-T** (timestamp) / **-LTA** (LTV+DSS) |
| **Nivel 3** — backends QES | ✅ enchufable: `pemder` / `pkcs12` / `pkcs11` (DNIe). pkcs12 probado |
| Panel de auditoría | ✅ `/documents/{id}/audit` |
| **Multi-firmante** (invitaciones email) | ✅ secuencial, enlaces `/sign/{token}`, sello PAdES en el último |
| Herramientas de cert | ✅ `docsigner:make-cert`, `docsigner:cert-info` |
| Tests automatizados | ✅ 19 tests / 108 aserciones (`php artisan test`) |

## ⏳ Pendiente / próximos pasos

1. **Comprar cert de organización** (CA AATL/eIDAS, ~100–400 €/año) → colocar en `pkcs12`
   → firmas **AdES de confianza** (verde en Adobe). *Único paso manual; la plataforma ya lo firma.*
   Verificar con `php artisan docsigner:cert-info --p12=... --pass=...`.
2. **QTSP por API** (Signaturit/Viafirma…) — **APARCADO**: requiere credenciales de proveedor.
   Sería un driver `QualifiedSignatureProvider` + webhooks. Solo si se necesita QES gestionada.
3. Ideas abiertas: modo de firma **paralelo** (no solo secuencial), aviso por email al
   siguiente firmante cuando le toca, **SMS OTP**, geolocalización en auditoría, tests
   multi-firmante con PAdES activado, LTV con cert de CA real (revocación OCSP/CRL real).

## Arranque rápido (dev)

```bash
composer install && npm install
php artisan migrate
npm run build
# Servidor: lanzar desde PowerShell (entorno Windows completo, necesario para PAdES)
php artisan serve --host=127.0.0.1 --port=8000
php artisan test          # suite completa
```

- **DB**: MySQL 9 en `127.0.0.1:3306`, `root` sin contraseña, base `docsigner`.
- **Email** en dev: `MAIL_MAILER=log` → el OTP/invitación aparece en `storage/logs/laravel.log`.
- **PAdES**: `PADES_ENABLED=true`, `PYTHON_PATH` a python.exe, `PADES_BACKEND`, `PADES_TSA_URL`,
  `PADES_LTV`. Python: `pip install -r requirements.txt`.

## Gotchas conocidos (ya resueltos)

- MySQL con **MyISAM por defecto** → `config/database.php` fuerza `engine InnoDB`.
- El cliente `mysql` del PATH es **MariaDB (WAMP)** y no autentica contra MySQL 9
  (`caching_sha2_password`) → usar PDO/artisan para tareas de BD.
- **Windows + Symfony Process**: bajo `artisan serve` se pierde `SystemRoot` → Python falla con
  `WinError 10106`. `PadesSigningService::systemEnv()` lo reinyecta (solo Windows).
- **`.p12` legacy RC2**: PHP 8.2 (OpenSSL 3) no lo lee. Exportar con AES
  (`-keypbe AES-256-CBC -certpbe AES-256-CBC -macalg sha256`).
- Cert para PAdES-LTA necesita `keyUsage = digitalSignature, nonRepudiation` (lo pone `make-cert`).
- pyHanko 0.35 **no instala CLI** → se usa como **librería** (`scripts/pades_sign.py`).

## Mapa de archivos clave

- `app/Services/PdfConversionService.php` — normaliza entradas a PDF (LibreOffice).
- `app/Services/PadesSigningService.php` — sellado PAdES (shell-out a pyHanko).
- `scripts/pades_sign.py` — firma PAdES (backends, TSA, LTV).
- `app/Http/Controllers/DocumentController.php` — subida, firma single, descarga, auditoría.
- `app/Http/Controllers/SignatureController.php` — OTP single-firmante + finalize.
- `app/Http/Controllers/InvitationController.php` — multi-firmante (gestión + público por token).
- `resources/js/sign.js` — firma en navegador (PDF.js + signature_pad + pdf-lib + flujo OTP).
- `resources/views/documents/sign.blade.php` — vista de firma (reutilizada por documento y token).
- `tests/Feature/*` — DocumentTest, SignatureFlowTest, MultiSignerTest.
