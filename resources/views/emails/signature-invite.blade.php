Hola {{ $signerName }},

Te han solicitado firmar el documento "{{ $documentName }}".

Abre este enlace para revisarlo y firmarlo:

    {{ $signUrl }}

@if ($position)
Eres el firmante numero {{ $position }}.
@endif

@if ($expiresAt)
Esta invitacion caduca el {{ $expiresAt->format('d/m/Y H:i') }} (UTC).
@endif

El enlace es personal: no lo compartas.

-- FirmaDoc
