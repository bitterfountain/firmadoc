<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MakeSigningCertificate extends Command
{
    protected $signature = 'docsigner:make-cert
                            {--cn=FirmaDoc Signing : Common Name del certificado}
                            {--org=FirmaDoc : Organizacion}
                            {--country=ES : Codigo de pais}
                            {--days=3650 : Validez en dias}
                            {--force : Sobrescribir si ya existe}';

    protected $description = 'Genera un certificado X.509 autofirmado (clave + cert PEM) para firmar PDFs con PAdES';

    public function handle(): int
    {
        $dir = storage_path('app/certs');
        $keyPath = $dir.DIRECTORY_SEPARATOR.'key.pem';
        $certPath = $dir.DIRECTORY_SEPARATOR.'cert.pem';

        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            $this->error("No se pudo crear el directorio {$dir}");

            return self::FAILURE;
        }

        if ((is_file($keyPath) || is_file($certPath)) && ! $this->option('force')) {
            $this->warn('Ya existe un certificado. Usa --force para sobrescribirlo.');

            return self::FAILURE;
        }

        // Config OpenSSL con keyUsage para FIRMA (digitalSignature + nonRepudiation):
        // imprescindible para que la firma sea valida para PAdES-LTA / non-repudiation.
        $cnf = <<<'CNF'
            [ req ]
            distinguished_name = dn
            [ dn ]
            [ v3_sign ]
            basicConstraints = critical, CA:FALSE
            keyUsage = critical, digitalSignature, nonRepudiation
            extendedKeyUsage = clientAuth, emailProtection
            CNF;
        $cnfPath = tempnam(sys_get_temp_dir(), 'dsx').'.cnf';
        file_put_contents($cnfPath, $cnf);

        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
            'config' => $cnfPath,
            'x509_extensions' => 'v3_sign',
        ];

        $pkey = openssl_pkey_new($config);
        if ($pkey === false) {
            @unlink($cnfPath);
            $this->error('openssl_pkey_new fallo: '.openssl_error_string());

            return self::FAILURE;
        }

        $dn = [
            'countryName' => $this->option('country'),
            'organizationName' => $this->option('org'),
            'commonName' => $this->option('cn'),
        ];

        $csr = openssl_csr_new($dn, $pkey, $config);
        // Serial positivo (RFC 5280): evita avisos con serial cero/negativo.
        $serial = random_int(1, PHP_INT_MAX);
        $cert = openssl_csr_sign($csr, null, $pkey, (int) $this->option('days'), $config, $serial);
        @unlink($cnfPath);

        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($pkey, $keyPem); // sin passphrase (clave en claro)

        file_put_contents($keyPath, $keyPem);
        @chmod($keyPath, 0600);
        file_put_contents($certPath, $certPem);

        $fingerprint = openssl_x509_fingerprint($certPem, 'sha256');

        $this->info('Certificado generado:');
        $this->line("  Clave:         {$keyPath}");
        $this->line("  Certificado:   {$certPath}");
        $this->line('  CN:            '.$this->option('cn'));
        $this->line("  Huella SHA256: {$fingerprint}");
        $this->newLine();
        $this->warn('Es un certificado AUTOFIRMADO: sirve para sellar (tamper-evidence), pero');
        $this->warn('Adobe lo marcara como "emisor no confiable". Para confianza plena usa un');
        $this->warn('certificado de una CA reconocida (o cualificado para QES).');

        return self::SUCCESS;
    }
}
