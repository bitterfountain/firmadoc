<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CertInfo extends Command
{
    protected $signature = 'docsigner:cert-info
                            {--p12= : Inspeccionar un .p12/.pfx en vez del cert PEM configurado}
                            {--pass= : Contrasena del .p12}
                            {--cert= : Ruta a un cert PEM concreto}';

    protected $description = 'Inspecciona el certificado de firma: emisor, validez, keyUsage y si es de confianza';

    public function handle(): int
    {
        $certPem = $this->loadCert();
        if ($certPem === null) {
            return self::FAILURE;
        }

        $info = openssl_x509_parse($certPem);
        if ($info === false) {
            $this->error('No se pudo parsear el certificado.');
            return self::FAILURE;
        }

        $subjectCn = $info['subject']['CN'] ?? '(sin CN)';
        $issuerCn = $info['issuer']['CN'] ?? '(sin CN)';
        $issuerOrg = $info['issuer']['O'] ?? '';
        $selfSigned = $info['subject'] === $info['issuer'];
        $from = date('Y-m-d', $info['validFrom_time_t']);
        $to = date('Y-m-d', $info['validTo_time_t']);
        $daysLeft = (int) floor(($info['validTo_time_t'] - time()) / 86400);
        $keyUsage = $info['extensions']['keyUsage'] ?? '(no definido)';
        $extKeyUsage = $info['extensions']['extendedKeyUsage'] ?? '(no definido)';

        $this->newLine();
        $this->line("  <fg=cyan>Sujeto (firmante):</>  {$subjectCn}");
        $this->line("  <fg=cyan>Emisor (CA):</>        {$issuerCn}" . ($issuerOrg ? " — {$issuerOrg}" : ''));
        $this->line("  <fg=cyan>Validez:</>            {$from}  →  {$to}  ({$daysLeft} dias restantes)");
        $this->line("  <fg=cyan>Key Usage:</>          {$keyUsage}");
        $this->line("  <fg=cyan>Ext Key Usage:</>      {$extKeyUsage}");
        $this->line("  <fg=cyan>Huella SHA-256:</>     " . openssl_x509_fingerprint($certPem, 'sha256'));
        $this->newLine();

        // Veredictos.
        if ($daysLeft < 0) {
            $this->error('  ⚠ El certificado esta CADUCADO.');
        } elseif ($daysLeft < 30) {
            $this->warn("  ⚠ Caduca pronto ({$daysLeft} dias).");
        }

        $signOk = str_contains(strtolower($keyUsage), 'digital signature')
            || str_contains(strtolower($keyUsage), 'non repudiation');
        if (! $signOk) {
            $this->warn('  ⚠ keyUsage no incluye Digital Signature / Non Repudiation: puede no servir para firmar.');
        }

        if ($selfSigned) {
            $this->warn('  ⚠ Certificado AUTOFIRMADO → tamper-evidence si, pero NO de confianza.');
            $this->line('     Adobe/visores lo mostraran como "emisor no verificado".');
            $this->line('     Para AdES de confianza (verde), usa un cert de una CA reconocida (AATL/eIDAS).');
        } else {
            $this->info("  ✓ Emitido por una CA externa ({$issuerCn}).");
            $this->line('     Sera de confianza si esa CA esta en la lista de Adobe (AATL) o la EU Trusted List.');
        }
        $this->newLine();

        return self::SUCCESS;
    }

    /** Carga el PEM del cert desde --p12, --cert, o el cert configurado. */
    private function loadCert(): ?string
    {
        if ($p12Path = $this->option('p12')) {
            if (! is_file($p12Path)) {
                $this->error("No existe el .p12: {$p12Path}");
                return null;
            }
            if (! openssl_pkcs12_read(file_get_contents($p12Path), $certs, (string) $this->option('pass'))) {
                $this->error('No se pudo abrir el .p12 (¿contrasena incorrecta?): ' . openssl_error_string());
                return null;
            }
            $extra = count($certs['extracerts'] ?? []);
            $this->line("  <fg=gray>.p12 leido. Certificados en la cadena: " . (1 + $extra) . "</>");
            return $certs['cert'];
        }

        $certPath = $this->option('cert') ?: config('docsigner.pades.cert');
        if (! is_file($certPath)) {
            $this->error("No existe el certificado: {$certPath}");
            $this->line('Genera uno con: php artisan docsigner:make-cert');
            return null;
        }

        return file_get_contents($certPath);
    }
}
