#!/usr/bin/env python3
"""
Sella un PDF con una firma PAdES (criptografica X.509) usando pyHanko.

Lo invoca Laravel por shell-out, como paso final de la firma. La firma es
invisible (no dibuja widget): las firmas visuales ya estan incrustadas como
imagenes; esto anade la capa criptografica que detecta manipulaciones.

Soporta:
  - Backends de clave: pemder (clave+cert PEM), pkcs12 (.p12/.pfx), pkcs11 (token/HSM/DNIe).
  - Sello de tiempo (--tsa URL)        -> PAdES-T.
  - Validacion a largo plazo (--ltv)   -> PAdES-LTA (DSS + document timestamp).

Uso (pemder):
  python pades_sign.py --in IN.pdf --out OUT.pdf --backend pemder --key key.pem --cert cert.pem
                       [--field Sig1] [--reason ...] [--name ...] [--tsa URL] [--ltv]
"""
import argparse
import sys


def build_signer(args):
    """Crea el firmante segun el backend elegido."""
    from pyhanko.sign import signers

    if args.backend == "pemder":
        return signers.SimpleSigner.load(
            args.key, args.cert, key_passphrase=None
        )

    if args.backend == "pkcs12":
        passphrase = args.p12_pass.encode() if args.p12_pass else None
        return signers.SimpleSigner.load_pkcs12(args.p12, passphrase=passphrase)

    if args.backend == "pkcs11":
        # Firma con un certificado cualificado en token/HSM/DNIe (-> QES).
        from pyhanko.sign import pkcs11 as ph_pkcs11

        session = ph_pkcs11.open_pkcs11_session(
            args.pkcs11_lib,
            slot_no=args.pkcs11_slot,
            token_label=args.pkcs11_token,
            user_pin=args.pkcs11_pin,
        )
        return ph_pkcs11.PKCS11Signer(
            session,
            cert_label=args.pkcs11_cert_label,
            key_label=args.pkcs11_key_label or args.pkcs11_cert_label,
        )

    raise ValueError(f"Backend desconocido: {args.backend}")


def main() -> int:
    ap = argparse.ArgumentParser(description="Firma PAdES con pyHanko")
    ap.add_argument("--in", dest="inp", required=True)
    ap.add_argument("--out", dest="out", required=True)
    ap.add_argument("--field", default="Sig1")
    ap.add_argument("--reason", default=None)
    ap.add_argument("--name", default=None)
    ap.add_argument("--location", default=None)
    ap.add_argument("--tsa", default=None, help="URL de TSA (RFC 3161) -> PAdES-T")
    ap.add_argument("--ltv", action="store_true", help="Validacion a largo plazo (PAdES-LTA)")

    ap.add_argument("--backend", default="pemder", choices=["pemder", "pkcs12", "pkcs11"])
    # pemder
    ap.add_argument("--key")
    ap.add_argument("--cert")
    # pkcs12
    ap.add_argument("--p12")
    ap.add_argument("--p12-pass", dest="p12_pass")
    # pkcs11
    ap.add_argument("--pkcs11-lib", dest="pkcs11_lib")
    ap.add_argument("--pkcs11-slot", dest="pkcs11_slot", type=int)
    ap.add_argument("--pkcs11-token", dest="pkcs11_token")
    ap.add_argument("--pkcs11-cert-label", dest="pkcs11_cert_label")
    ap.add_argument("--pkcs11-key-label", dest="pkcs11_key_label")
    ap.add_argument("--pkcs11-pin", dest="pkcs11_pin")
    args = ap.parse_args()

    from pyhanko.sign import signers, fields
    from pyhanko.pdf_utils.incremental_writer import IncrementalPdfFileWriter

    signer = build_signer(args)
    if signer is None:
        print("ERROR: no se pudo cargar la clave o el certificado", file=sys.stderr)
        return 2

    timestamper = None
    if args.tsa:
        from pyhanko.sign.timestamps import HTTPTimeStamper
        timestamper = HTTPTimeStamper(args.tsa)

    meta_kwargs = dict(
        field_name=args.field,
        subfilter=fields.SigSeedSubFilter.PADES,
        reason=args.reason,
        name=args.name,
        location=args.location,
    )

    # PAdES-LTA: incrusta info de validacion (cadena de certs, OCSP/CRL si hay)
    # en el DSS y anade un document timestamp. Requiere un sello de tiempo.
    if args.ltv:
        from pyhanko_certvalidator import ValidationContext

        # Confiamos en nuestro cert firmante + los root CAs publicos (Mozilla via
        # certifi), necesarios para validar la cadena del sello de tiempo (TSA).
        trust_roots = [signer.signing_cert]
        try:
            import certifi
            from pyhanko.keys import load_certs_from_pemder
            trust_roots += list(load_certs_from_pemder([certifi.where()]))
        except Exception:
            pass

        vc = ValidationContext(
            trust_roots=trust_roots,
            allow_fetching=True,
            revocation_mode="soft-fail",  # cert autofirmado: sin OCSP/CRL, no fallar
        )
        meta_kwargs.update(
            embed_validation_info=True,
            use_pades_lta=True,
            validation_context=vc,
        )

    meta = signers.PdfSignatureMetadata(**meta_kwargs)

    with open(args.inp, "rb") as inf:
        writer = IncrementalPdfFileWriter(inf)
        result = signers.sign_pdf(writer, meta, signer=signer, timestamper=timestamper)
        with open(args.out, "wb") as outf:
            outf.write(result.getbuffer())

    print("OK")
    return 0


if __name__ == "__main__":
    sys.exit(main())
