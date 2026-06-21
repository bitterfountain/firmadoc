Hola {{ $witnessName }},

@if ($allSigned)
El documento "{{ $documentName }}" ha sido firmado por todas las partes.

Quedas registrado como testigo de este documento.
@else
Te han designado como testigo del documento "{{ $documentName }}".

Para dejar constancia de que has presenciado las firmas, confirma en este enlace:

    {{ $confirmUrl }}
@endif

-- FirmaDoc
