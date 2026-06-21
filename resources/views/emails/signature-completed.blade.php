Hola {{ $signerName }},

El documento "{{ $documentName }}" ha sido firmado por todas las partes.

Adjuntamos el documento firmado.
@if ($padesApplied)
El documento incluye sello criptografico PAdES.
@endif

-- FirmaDoc
