# FirmaDoc

Sistema para firmar documentos. Acepta **PDF, DOCX/DOC/ODT e imágenes (JPG/PNG)**,
lo **normaliza todo a PDF**, permite **previsualizar**, **marcar la zona de firma**
con ratón o dedo, **dibujar la firma** e **incrustarla** en el documento.

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
```

- **Backend:** Laravel 12 + MySQL. La conversión a PDF la hace LibreOffice vía
  `Symfony\Process` (sin paquetes Composer extra).
- **Frontend:** Blade + Vite. `pdfjs-dist` (preview), `signature_pad` (firma),
  `pdf-lib` (incrustado). La firma se incrusta **en el navegador**; Laravel guarda
  el resultado.

### Piezas clave

| Archivo | Rol |
|---|---|
| `app/Services/PdfConversionService.php` | Normaliza cualquier entrada a PDF |
| `app/Http/Controllers/DocumentController.php` | Subida, conversión, firma, descarga |
| `resources/js/sign.js` | Preview + marcado de zona + incrustado de firma |
| `config/docsigner.php` | Ruta de LibreOffice, formatos y límites |

## Requisitos

- PHP 8.2+, Composer, Node 18+, MySQL 8+/9.
- **LibreOffice** (solo para convertir DOCX e imágenes; el flujo PDF no lo necesita).

## Puesta en marcha (desarrollo)

```bash
composer install
npm install
# .env ya configurado para MySQL en 127.0.0.1:3306 / docsigner / root sin password
php artisan key:generate
php artisan migrate
npm run build          # o `npm run dev` para hot reload
php artisan serve
```

> En Windows sin LibreOffice solo se puede probar el flujo de **PDF**. DOCX e
> imágenes requieren LibreOffice (ver despliegue).

## Despliegue en Ubuntu

Instalar LibreOffice headless:

```bash
sudo apt update
sudo apt install -y libreoffice --no-install-recommends
# mínimo: sudo apt install -y libreoffice-writer libreoffice-draw --no-install-recommends
```

En `.env`:

```ini
SOFFICE_PATH=/usr/bin/soffice     # o simplemente "soffice" si está en el PATH
```

## Niveles de firma electrónica

- **Nivel 0 — Visual:** una o varias firmas dibujadas (móvil/ratón) incrustadas en el
  PDF con pdf-lib. Movibles, redimensionables, borrables; admite varios firmantes.
- **Nivel 1 — Auditoría + OTP:** antes de firmar, el firmante verifica su identidad con
  un **código por email** (OTP). Se registran firmante, email, IP, navegador, timestamp y
  **hashes SHA-256** del documento en `signature_events` (MySQL), y se anexa una
  **página-certificado** al PDF. El OTP se guarda hasheado.
- **Nivel 2 — PAdES criptográfica:** sellado final en el servidor con **pyHanko**
  (`scripts/pades_sign.py`), firma X.509 que detecta cualquier manipulación posterior
  (tamper-evident). Degrada con elegancia a Nivel 1 si pyHanko no está disponible.
  - **PAdES-T** (sello de tiempo RFC 3161) si se configura `PADES_TSA_URL`.
  - **PAdES-LTA** (validación a largo plazo: DSS + document timestamp) si `PADES_LTV=true`.
- **Nivel 3 — Cualificada (QES):** el backend de firma es **enchufable**
  (`PADES_BACKEND`): `pemder` (PEM), `pkcs12` (.p12 de un proveedor) o `pkcs11`
  (token/HSM/**DNIe**). Firmar con un **certificado cualificado** por cualquiera de estas
  vías produce una QES con la misma tubería. (Para un flujo gestionado tipo Signaturit/
  Viafirma se integraría su API como alternativa.)

Cada documento tiene además un **panel de auditoría** (`/documents/{id}/audit`) con el
historial completo de eventos de firma (firmante, email, IP, navegador, hashes, sellado).

### Multi-firmante (invitaciones por email)

El propietario añade firmantes en `/documents/{id}/signers`; a cada uno se le envía un
**enlace privado** (`/sign/{token}`) por email. Se firma **por orden** (secuencial): cada
firmante construye sobre las firmas anteriores. El **último** firmante dispara el sellado
PAdES. La página de gestión muestra el progreso (`N/M firmado`) y el turno de cada uno.

## Tests

```bash
php artisan test          # SQLite en memoria, sin tocar MySQL
```

Cubren: subida/conversión, validación de formato, flujo OTP (correcto, incorrecto, caducado),
finalize, panel de auditoría, y el flujo multi-firmante completo (turnos secuenciales +
finalización). PAdES queda desactivado en `testing` para no depender de pyHanko.

### Setup del Nivel 2/3

```bash
pip install -r requirements.txt      # pyHanko
php artisan docsigner:make-cert      # genera storage/app/certs/{key,cert}.pem (autofirmado)
```

```ini
# .env
PADES_ENABLED=true
PYTHON_PATH=python3                   # Windows: ruta completa a python.exe
PADES_BACKEND=pemder                  # pemder | pkcs12 | pkcs11
PADES_TSA_URL=http://timestamp.digicert.com   # opcional -> PAdES-T
PADES_LTV=true                        # opcional (requiere TSA) -> PAdES-LTA

# pkcs12 (certificado cualificado en .p12):
# PADES_BACKEND=pkcs12
# PADES_P12=/ruta/cert.p12
# PADES_P12_PASS=...

# pkcs11 (token / HSM / DNIe -> QES):
# PADES_BACKEND=pkcs11
# PADES_PKCS11_LIB=/usr/lib/opensc-pkcs11.so   # p.ej. OpenSC para DNIe
# PADES_PKCS11_CERT_LABEL=...
# PADES_PKCS11_PIN=...
```

> El certificado autofirmado sirve para sellar (tamper-evidence); para confianza plena en
> Adobe usa un certificado de una CA reconocida. Con un **certificado cualificado**
> (pkcs12/pkcs11) la firma es **QES** (equivalente legal a la manuscrita bajo eIDAS).

### Pasar a AdES "de confianza" (recomendado para producción)

El salto de mayor valor por menor coste: un **certificado de firma de organización** de una
CA reconocida (~100–400 €/año, **coste plano, sin pago por firma**, y el documento nunca sale
del servidor). Hace que todas las firmas sean **AdES de confianza** (verde en Adobe).

1. Compra un **document-signing certificate** en una CA de la **Adobe AATL** o la **EU Trusted
   List** (DigiCert, GlobalSign, Sectigo, Uanataca…). Pide explícitamente que sea válido para
   *document signing* y, si quieres verde automático en Adobe, **AATL**.
   - Nota: los certs AATL de mayor garantía suelen entregarse en **token/HSM** (usa el backend
     `pkcs11`); los de menor garantía pueden venir como **`.p12` descargable** (backend `pkcs12`).
2. Coloca el `.p12` en `storage/app/certs/` y configura:
   ```ini
   PADES_BACKEND=pkcs12
   PADES_P12=storage/app/certs/empresa.p12
   PADES_P12_PASS=tu-clave
   ```
3. Verifica el certificado antes de usarlo:
   ```bash
   php artisan docsigner:cert-info --p12=storage/app/certs/empresa.p12 --pass=tu-clave
   ```
   Debe mostrar un **emisor de CA externa** (no autofirmado), `keyUsage` con *Digital Signature*,
   y vigencia válida.

> Si el `.p12` se generó con OpenSSL antiguo (cifrado RC2 legacy), PHP 8.2 (OpenSSL 3) no lo
> lee. Reexpórtalo con AES: `openssl pkcs12 -export -keypbe AES-256-CBC -certpbe AES-256-CBC
> -macalg sha256 ...`. Los `.p12` de CAs modernas ya vienen en AES.

### Email (Nivel 1)

En desarrollo `MAIL_MAILER=log` escribe el OTP en `storage/logs/laravel.log`. En producción
configura SMTP real (`MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_USERNAME`, …).

## Notas técnicas

- **InnoDB forzado** en `config/database.php`: el servidor MySQL tiene MyISAM por
  defecto (límite de clave 1000 bytes) y con `utf8mb4` un `varchar(255)` no cabe.
- Los archivos se guardan en el **disco privado** (`storage/app/private/documents/{id}/`)
  y se sirven vía controlador, nunca públicamente.
- **PAdES en Windows:** bajo `artisan serve`, `PadesSigningService` reinyecta `SystemRoot`
  y otras variables al subproceso (Symfony Process las pierde al intersectar con `$_SERVER`),
  evitando el `WinError 10106` al iniciar Python.
