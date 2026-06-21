<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Sella un PDF con una firma PAdES (criptografica) ejecutando pyHanko via
 * el script scripts/pades_sign.py. Es el paso final del Nivel 2/3.
 *
 * Soporta backend pemder (PEM autofirmado/CA), pkcs12 (.p12) y pkcs11
 * (token/HSM/DNIe -> QES), ademas de sello de tiempo (PAdES-T) y LTV (PAdES-LTA).
 */
class PadesSigningService
{
    /** ¿Esta configurado y disponible el sellado PAdES segun el backend? */
    public function isEnabled(): bool
    {
        $cfg = config('docsigner.pades');

        if (! ($cfg['enabled'] ?? false)) {
            return false;
        }

        // Nodo de firma remoto: el sellado lo hace otro equipo (token QES).
        if (! empty($cfg['remote_url'])) {
            return true;
        }

        if (! is_file($cfg['script'])) {
            return false;
        }

        return match ($cfg['backend'] ?? 'pemder') {
            'pemder' => is_file($cfg['key']) && is_file($cfg['cert']),
            'pkcs12' => is_file($cfg['p12']),
            'pkcs11' => ! empty($cfg['pkcs11']['lib']),
            default => false,
        };
    }

    /**
     * Sella delegando en el nodo de firma remoto (equipo con el token QES).
     * Envia el PDF por HTTPS y guarda el PDF sellado que devuelve.
     */
    private function signRemote(string $inAbs, string $outAbs, array $meta, string $url, string $token): void
    {
        $response = Http::timeout(180)
            ->withToken($token)
            ->withHeaders([
                'X-Sign-Reason' => (string) ($meta['reason'] ?? ''),
                'X-Sign-Name' => (string) ($meta['name'] ?? ''),
                'X-Sign-Location' => (string) ($meta['location'] ?? ''),
            ])
            ->withBody(file_get_contents($inAbs), 'application/pdf')
            ->post($url);

        if (! $response->successful()) {
            throw new RuntimeException('El nodo de firma remoto rechazo el sellado: HTTP '
                .$response->status().' '.mb_substr($response->body(), 0, 300));
        }

        file_put_contents($outAbs, $response->body());
    }

    /**
     * Firma $inAbs con PAdES y escribe el resultado en $outAbs.
     *
     * @param  array{reason?:string,name?:string,location?:string}  $meta
     */
    public function sign(string $inAbs, string $outAbs, array $meta = [], array $override = []): void
    {
        // $override permite firmar con el certificado propio del usuario
        // (p. ej. ['backend' => 'pkcs12', 'p12' => ..., 'p12_pass' => ...]).
        $cfg = array_replace(config('docsigner.pades'), $override);

        // Si hay nodo remoto (token QES en casa) y no se fuerza un cert propio,
        // delegamos el sellado a ese nodo por HTTPS.
        if (empty($override) && ! empty($cfg['remote_url'])) {
            $this->signRemote($inAbs, $outAbs, $meta, $cfg['remote_url'], (string) ($cfg['remote_token'] ?? ''));

            return;
        }

        $args = [
            $cfg['python'],
            $cfg['script'],
            '--in', $inAbs,
            '--out', $outAbs,
            '--field', $cfg['field'] ?? 'Sig1',
            '--backend', $cfg['backend'] ?? 'pemder',
        ];

        // Argumentos del backend.
        switch ($cfg['backend'] ?? 'pemder') {
            case 'pemder':
                array_push($args, '--key', $cfg['key'], '--cert', $cfg['cert']);
                break;
            case 'pkcs12':
                array_push($args, '--p12', $cfg['p12']);
                if (! empty($cfg['p12_pass'])) {
                    array_push($args, '--p12-pass', $cfg['p12_pass']);
                }
                break;
            case 'pkcs11':
                $p = $cfg['pkcs11'];
                array_push($args, '--pkcs11-lib', $p['lib']);
                if ($p['slot'] !== null && $p['slot'] !== '') {
                    array_push($args, '--pkcs11-slot', (string) $p['slot']);
                }
                foreach (['token' => '--pkcs11-token', 'cert_label' => '--pkcs11-cert-label',
                    'key_label' => '--pkcs11-key-label', 'pin' => '--pkcs11-pin'] as $key => $flag) {
                    if (! empty($p[$key])) {
                        array_push($args, $flag, $p[$key]);
                    }
                }
                break;
        }

        // Metadatos de la firma.
        foreach (['reason', 'name', 'location'] as $opt) {
            if (! empty($meta[$opt])) {
                array_push($args, "--{$opt}", $meta[$opt]);
            }
        }

        // Sello de tiempo (PAdES-T) y LTV (PAdES-LTA).
        if (! empty($cfg['tsa_url'])) {
            array_push($args, '--tsa', $cfg['tsa_url']);
            if ($cfg['ltv'] ?? false) {
                $args[] = '--ltv'; // LTV requiere un sello de tiempo.
            }
        }

        $process = new Process($args, base_path(), $this->systemEnv());
        $process->setTimeout((float) ($cfg['timeout'] ?? 120));

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new RuntimeException(
                'pyHanko fallo al sellar: '.trim($process->getErrorOutput() ?: $process->getOutput()),
                previous: $e,
            );
        }
    }

    /**
     * Entorno explicito para el proceso hijo.
     *
     * Bajo `artisan serve`, Symfony Process intersecta el entorno con $_SERVER
     * (lleno de variables de la peticion HTTP), perdiendo SystemRoot y otras
     * variables que Python necesita para inicializar Winsock/crypto en Windows.
     * Las reinyectamos explicitamente. En Linux no hace falta (devolvemos null).
     */
    private function systemEnv(): ?array
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            return null; // Linux/Ubuntu: heredar el entorno por defecto basta.
        }

        $keys = [
            'SystemRoot', 'windir', 'SystemDrive', 'PATH', 'Path', 'PATHEXT',
            'TEMP', 'TMP', 'COMSPEC', 'APPDATA', 'LOCALAPPDATA', 'USERPROFILE',
            'NUMBER_OF_PROCESSORS', 'PROCESSOR_ARCHITECTURE',
        ];

        $env = [];
        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $env[$key] = $value;
            }
        }

        return $env;
    }
}
