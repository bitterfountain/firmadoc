Hola {{ $signerName }},

Te recordamos que tienes pendiente firmar el documento "{{ $documentName }}".

Abre este enlace para firmarlo:

    {{ $signUrl }}

@if ($expiresIn)
La invitacion caduca en {{ $expiresIn }}.
@endif

-- FirmaDoc
