# Nodo de firma casero (token QES) — el "ahorro"

Sella las firmas PAdES de FirmaDoc con un **token cualificado** (p. ej. Disig, ~30 €/año)
sin pagar un HSM en la nube. El token va enchufado a un equipo de casa siempre encendido;
ese equipo firma y FirmaDoc lo llama por un túnel seguro.

```
FirmaDoc (droplet)  ──HTTPS + token──▶  túnel (Tailscale/Cloudflare)  ──▶  sign_server.py (casa)
                                                                              │ PKCS#11
                                                                              ▼
                                                                        token QES (USB)
```

## Qué necesitas
- Un **equipo siempre encendido** con el token: mejor un **mini-PC x86 / portátil viejo** que una Raspberry (los drivers PKCS#11 del token suelen ser x86; en ARM puede que no existan).
- El **token QES** (Disig u otro QTSP) + su **PIN** y su **módulo PKCS#11** (`.so`).
- **Python 3** y conexión a internet.

## Instalación (en el equipo de casa, Ubuntu/Debian)
```bash
mkdir -p ~/signnode && cd ~/signnode
# 1) copia estos ficheros del repo: sign_server.py  + scripts/pades_sign.py de FirmaDoc
#    (pades_sign.py debe quedar junto al server, o ajusta PADES_SCRIPT)
python3 -m venv .venv && . .venv/bin/activate
pip install pyhanko pyhanko-certvalidator pkcs11 certifi
# 2) middleware del token (uno de estos, segun el fabricante):
sudo apt install opensc            # generico (muchos tokens)
# 3) localiza el modulo y verifica que ve el token:
pkcs11-tool --module /usr/lib/x86_64-linux-gnu/opensc-pkcs11.so -L -O
```

## Configura y arranca
```bash
cp .env.example .env
# edita .env: SIGN_AUTH_TOKEN (openssl rand -hex 32), PKCS11_LIB, PKCS11_PIN, labels...
set -a; . ./.env; set +a
python sign_server.py
# prueba local: curl -s localhost:8731/health  -> ok
```
Para dejarlo permanente, crea un servicio systemd (`/etc/systemd/system/signnode.service`)
que ejecute `sign_server.py` con esas variables y `Restart=always`.

## Exponerlo de forma segura (sin abrir puertos en casa)
Usa **Tailscale** (más fácil) o **Cloudflare Tunnel**:
- **Tailscale:** instala en el equipo de casa y en el droplet; FirmaDoc usará la IP Tailscale del nodo:
  `PADES_REMOTE_URL=http://100.x.y.z:8731/sign` (tráfico cifrado por la VPN).
- **Cloudflare Tunnel:** publica `https://firma.tudominio.com` → `localhost:8731`, y
  `PADES_REMOTE_URL=https://firma.tudominio.com/sign`.

## Conecta FirmaDoc (en el droplet)
En el `.env` de FirmaDoc:
```
PADES_ENABLED=true
PADES_REMOTE_URL=http://100.x.y.z:8731/sign     # o la URL del tunnel
PADES_REMOTE_TOKEN=el-mismo-SIGN_AUTH_TOKEN
```
`php artisan config:cache`. A partir de ahí, todas las firmas PAdES se sellan con tu token QES.

## Notas
- El token QES es un **sello de organización** automatizado: el PIN se cachea en `.env` para
  firmar desatendido. Revisa que los términos de tu QTSP lo permitan para sellado automatizado.
- Si el equipo de casa se apaga o pierde internet, el sellado PAdES falla y FirmaDoc degrada a
  Nivel 1 (firma visual + auditoría) sin romperse — el documento se firma igual, sin el sello QES.
- Rendimiento: los tokens USB firman pocas operaciones/seg; suficiente para volumen bajo.
