<?php

return [
    /*
    | Ruta al binario de LibreOffice (soffice) usado para convertir
    | DOCX e imagenes a PDF. Si se deja vacio, se intenta "soffice" del PATH.
    | Windows: "C:\\Program Files\\LibreOffice\\program\\soffice.exe"
    | Ubuntu:  "soffice" o "/usr/bin/soffice"
    */
    'soffice_path' => env('SOFFICE_PATH', ''),

    /*
    | Disco de almacenamiento de los documentos (config/filesystems.php).
    | "local" usa el disco del servidor; "s3" usa un bucket S3/DO Spaces.
    | El pipeline de conversion/firma necesita rutas locales, asi que cuando
    | el disco es remoto los ficheros se materializan en temporales y el
    | resultado se vuelve a subir (ver App\Concerns\HandlesDocumentFiles).
    */
    'disk' => env('DOCSIGNER_DISK', 'local'),

    // Tamano maximo de subida en kilobytes (20 MB por defecto).
    'max_upload_kb' => env('DOCSIGNER_MAX_UPLOAD_KB', 20480),

    // Extensiones aceptadas en la subida.
    'allowed_extensions' => ['pdf', 'docx', 'doc', 'odt', 'jpg', 'jpeg', 'png'],

    // Segundos maximos para la conversion con LibreOffice.
    'convert_timeout' => 120,

    /*
    | Firma criptografica PAdES (Nivel 2) con pyHanko.
    | Se aplica como paso final en el servidor, sellando el PDF completo.
    | Si esta deshabilitado o falta el certificado, el flujo degrada a Nivel 1
    | (firma visual + auditoria) sin romperse.
    */
    'pades' => [
        'enabled' => env('PADES_ENABLED', false),
        // Interprete de Python. Windows: ruta completa; Ubuntu: "python3".
        'python' => env('PYTHON_PATH', 'python3'),
        'script' => base_path('scripts/pades_sign.py'),
        'field' => 'Sig1',
        'timeout' => 120,

        // URL de TSA (RFC 3161) opcional -> PAdES-T con sello de tiempo.
        'tsa_url' => env('PADES_TSA_URL', ''),
        // Validacion a largo plazo (PAdES-LTA): DSS + document timestamp. Requiere TSA.
        'ltv' => env('PADES_LTV', false),

        // Backend de la clave: pemder | pkcs12 | pkcs11.
        'backend' => env('PADES_BACKEND', 'pemder'),

        // pemder (clave + cert PEM autofirmado o de CA).
        'key' => storage_path('app/certs/key.pem'),
        'cert' => storage_path('app/certs/cert.pem'),

        // pkcs12 (.p12/.pfx, p.ej. exportado de un certificado cualificado).
        'p12' => env('PADES_P12', storage_path('app/certs/cert.p12')),
        'p12_pass' => env('PADES_P12_PASS', ''),

        // pkcs11 (token/HSM/DNIe -> QES). Requiere la libreria del modulo.
        'pkcs11' => [
            'lib' => env('PADES_PKCS11_LIB', ''),
            'slot' => env('PADES_PKCS11_SLOT'),
            'token' => env('PADES_PKCS11_TOKEN', ''),
            'cert_label' => env('PADES_PKCS11_CERT_LABEL', ''),
            'key_label' => env('PADES_PKCS11_KEY_LABEL', ''),
            'pin' => env('PADES_PKCS11_PIN', ''),
        ],
    ],
];
