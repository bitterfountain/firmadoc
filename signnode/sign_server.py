#!/usr/bin/env python3
"""
Nodo de firma casero para FirmaDoc.

Corre en un equipo de casa (mini-PC / portátil) que tiene el token QES
enchufado. Recibe un PDF por HTTPS (cuerpo crudo + Bearer token), lo sella con
pyHanko usando el token (PKCS#11) reutilizando scripts/pades_sign.py, y devuelve
el PDF sellado. FirmaDoc lo llama vía PADES_REMOTE_URL.

Exponlo SOLO por un tunel seguro (Tailscale / Cloudflare Tunnel), no abras puerto.

Configuracion por variables de entorno (ver signnode/.env.example):
  SIGN_AUTH_TOKEN   token compartido con FirmaDoc (obligatorio)
  SIGN_PORT         puerto (def. 8731)
  SIGN_BIND         interfaz (def. 127.0.0.1)
  PADES_SCRIPT      ruta a pades_sign.py
  PYTHON            interprete python (def. el actual)
  PKCS11_LIB        ruta al modulo .so/.dll del token (obligatorio)
  PKCS11_SLOT, PKCS11_TOKEN, PKCS11_CERT_LABEL, PKCS11_KEY_LABEL, PKCS11_PIN
  TSA_URL           sello de tiempo (opcional)
  LTV               "1" para PAdES-LTA (opcional)
"""

import hmac
import os
import subprocess
import sys
import tempfile
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

CFG = {
    "auth": os.environ.get("SIGN_AUTH_TOKEN", ""),
    "port": int(os.environ.get("SIGN_PORT", "8731")),
    "bind": os.environ.get("SIGN_BIND", "127.0.0.1"),
    "script": os.environ.get("PADES_SCRIPT", os.path.join(os.path.dirname(__file__), "pades_sign.py")),
    "python": os.environ.get("PYTHON", sys.executable),
    "lib": os.environ.get("PKCS11_LIB", ""),
    "slot": os.environ.get("PKCS11_SLOT", ""),
    "token": os.environ.get("PKCS11_TOKEN", ""),
    "cert_label": os.environ.get("PKCS11_CERT_LABEL", ""),
    "key_label": os.environ.get("PKCS11_KEY_LABEL", ""),
    "pin": os.environ.get("PKCS11_PIN", ""),
    "tsa": os.environ.get("TSA_URL", ""),
    "ltv": os.environ.get("LTV", ""),
}

MAX_BYTES = 30 * 1024 * 1024  # 30 MB


class Handler(BaseHTTPRequestHandler):
    def _send(self, code, body=b"", ctype="text/plain; charset=utf-8"):
        self.send_response(code)
        self.send_header("Content-Type", ctype)
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        # Healthcheck
        if self.path == "/health":
            self._send(200, b"ok")
        else:
            self._send(404, b"not found")

    def do_POST(self):
        if self.path != "/sign":
            return self._send(404, b"not found")

        auth = self.headers.get("Authorization", "")
        token = auth[7:].strip() if auth.lower().startswith("bearer ") else ""
        if not CFG["auth"] or not hmac.compare_digest(token, CFG["auth"]):
            return self._send(401, b"unauthorized")

        length = int(self.headers.get("Content-Length", "0"))
        if length <= 0 or length > MAX_BYTES:
            return self._send(400, b"bad length")
        pdf = self.rfile.read(length)

        reason = self.headers.get("X-Sign-Reason", "")
        name = self.headers.get("X-Sign-Name", "")
        location = self.headers.get("X-Sign-Location", "")

        with tempfile.TemporaryDirectory() as d:
            inp, outp = os.path.join(d, "in.pdf"), os.path.join(d, "out.pdf")
            with open(inp, "wb") as f:
                f.write(pdf)

            cmd = [CFG["python"], CFG["script"], "--in", inp, "--out", outp,
                   "--backend", "pkcs11", "--pkcs11-lib", CFG["lib"]]
            if CFG["slot"]:
                cmd += ["--pkcs11-slot", CFG["slot"]]
            if CFG["token"]:
                cmd += ["--pkcs11-token", CFG["token"]]
            if CFG["cert_label"]:
                cmd += ["--pkcs11-cert-label", CFG["cert_label"]]
            if CFG["key_label"]:
                cmd += ["--pkcs11-key-label", CFG["key_label"]]
            if CFG["pin"]:
                cmd += ["--pkcs11-pin", CFG["pin"]]
            if reason:
                cmd += ["--reason", reason]
            if name:
                cmd += ["--name", name]
            if location:
                cmd += ["--location", location]
            if CFG["tsa"]:
                cmd += ["--tsa", CFG["tsa"]]
                if CFG["ltv"] in ("1", "true", "yes"):
                    cmd += ["--ltv"]

            proc = subprocess.run(cmd, capture_output=True, timeout=150)
            if proc.returncode != 0 or not os.path.exists(outp):
                err = (proc.stderr or proc.stdout or b"")[:500]
                return self._send(502, b"sign failed: " + err)

            with open(outp, "rb") as f:
                sealed = f.read()

        self._send(200, sealed, "application/pdf")

    def log_message(self, *args):
        pass  # silencioso


if __name__ == "__main__":
    if not CFG["auth"] or not CFG["lib"]:
        sys.exit("Faltan SIGN_AUTH_TOKEN o PKCS11_LIB")
    srv = ThreadingHTTPServer((CFG["bind"], CFG["port"]), Handler)
    print(f"Nodo de firma escuchando en {CFG['bind']}:{CFG['port']}")
    srv.serve_forever()
