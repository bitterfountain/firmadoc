<?php

namespace App\Http\Controllers;

use App\Mail\SignatureOtpMail;
use App\Services\PdfConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Firma rapida ANONIMA (sin registro). Flujo efimero: el documento se convierte
 * y se firma en una carpeta temporal local, se entrega (descarga + email) y se
 * PURGA. No usa Spaces ni guarda filas en BD. Nivel 1 (OTP por email); el OTP
 * vive en cache con TTL. Sin PAdES (reservado a usuarios registrados).
 */
class QuickSignController extends Controller
{
    private const TTL_MINUTES = 120;   // vida maxima de una sesion efimera
    private const OTP_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    private function baseDir(): string
    {
        return storage_path('app/ephemeral');
    }

    private function dir(string $eid): string
    {
        return $this->baseDir() . DIRECTORY_SEPARATOR . $eid;
    }

    private function meta(string $eid): ?array
    {
        return Cache::get("quick:{$eid}");
    }

    /** Borra carpetas efimeras mas viejas que el TTL (limpieza oportunista). */
    private function purgeStale(): void
    {
        if (! is_dir($this->baseDir())) {
            return;
        }
        $cutoff = now()->subMinutes(self::TTL_MINUTES)->getTimestamp();
        foreach (File::directories($this->baseDir()) as $d) {
            if (filemtime($d) < $cutoff) {
                File::deleteDirectory($d);
            }
        }
    }

    private function purge(string $eid): void
    {
        File::deleteDirectory($this->dir($eid));
        Cache::forget("quick:{$eid}");
        Cache::forget("quick_otp:{$eid}");
    }

    /** Formulario de subida. */
    public function start(): View
    {
        return view('quick.start');
    }

    /** Sube un archivo, lo normaliza a PDF en temporal y abre la pantalla de firma. */
    public function upload(Request $request, PdfConversionService $converter): RedirectResponse
    {
        $request->validate([
            'file' => [
                'required', 'file',
                'max:' . (int) config('docsigner.max_upload_kb'),
                'mimes:' . implode(',', config('docsigner.allowed_extensions')),
            ],
        ], [
            'file.mimes' => 'Formato no soportado. Acepta: ' . implode(', ', config('docsigner.allowed_extensions')) . '.',
        ]);

        $this->purgeStale();

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $eid = bin2hex(random_bytes(16));
        $dir = $this->dir($eid);
        File::ensureDirectoryExists($dir, 0775);

        try {
            $source = $dir . DIRECTORY_SEPARATOR . "original.{$ext}";
            $file->move($dir, "original.{$ext}");
            $converter->normalizeToPdf($source, $ext, $dir);
            @unlink($source);
        } catch (Throwable $e) {
            File::deleteDirectory($dir);

            return redirect()->route('quick.start')
                ->with('error', "No se pudo procesar el documento: {$e->getMessage()}");
        }

        Cache::put("quick:{$eid}", [
            'original_name' => $file->getClientOriginalName(),
        ], now()->addMinutes(self::TTL_MINUTES));

        return redirect()->route('quick.sign', $eid);
    }

    /** Pantalla de firma (reutiliza la vista con URLs efimeras). */
    public function sign(string $eid): View|RedirectResponse
    {
        $meta = $this->meta($eid);
        if (! $meta || ! is_file($this->dir($eid) . '/normalized.pdf')) {
            return redirect()->route('quick.start')
                ->with('error', 'La sesion de firma ha caducado. Sube el documento de nuevo.');
        }

        return view('documents.sign', [
            'headerTitle' => $meta['original_name'],
            'backUrl' => route('quick.start'),
            'pdfUrl' => route('quick.pdf', $eid),
            'saveUrl' => route('quick.finalize', $eid),
            'otpUrl' => route('quick.otp', $eid),
            'otpVerifyUrl' => route('quick.otpVerify', $eid),
            'signerName' => null,
            'signerEmail' => null,
        ]);
    }

    /** Sirve el PDF normalizado efimero. */
    public function pdf(string $eid)
    {
        abort_unless($this->meta($eid), 404);
        $path = $this->dir($eid) . '/normalized.pdf';
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Content-Type' => 'application/pdf']);
    }

    /** Paso 1: enviar OTP al email indicado (sirve tambien para la entrega). */
    public function otp(Request $request, string $eid): JsonResponse
    {
        $meta = $this->meta($eid);
        abort_unless($meta, 404);

        $data = $request->validate([
            'signer_name' => 'required|string|max:120',
            'signer_email' => 'required|email|max:190',
        ]);

        $code = (string) random_int(100000, 999999);

        Cache::put("quick_otp:{$eid}", [
            'hash' => Hash::make($code),
            'name' => $data['signer_name'],
            'email' => $data['signer_email'],
            'expires_at' => now()->addMinutes(self::OTP_MINUTES)->getTimestamp(),
            'attempts' => 0,
            'verified' => false,
            'reference' => 'FD-' . strtoupper(Str::random(8)),
        ], now()->addMinutes(self::OTP_MINUTES + 5));

        Mail::to($data['signer_email'])->send(
            new SignatureOtpMail($code, $meta['original_name'], self::OTP_MINUTES)
        );

        return response()->json(['event_id' => $eid]);
    }

    /** Paso 2: verificar OTP y devolver los datos de auditoria. */
    public function otpVerify(Request $request, string $eid): JsonResponse
    {
        $request->validate(['otp' => 'required|string']);
        $otp = Cache::get("quick_otp:{$eid}");

        if (! $otp || $otp['expires_at'] < now()->getTimestamp()) {
            return response()->json(['message' => 'El codigo ha caducado. Solicita uno nuevo.'], 422);
        }
        if ($otp['attempts'] >= self::MAX_ATTEMPTS) {
            return response()->json(['message' => 'Demasiados intentos. Solicita un codigo nuevo.'], 422);
        }
        if (! Hash::check($request->input('otp'), $otp['hash'])) {
            $otp['attempts']++;
            Cache::put("quick_otp:{$eid}", $otp, now()->addMinutes(self::OTP_MINUTES + 5));
            return response()->json([
                'message' => 'Codigo incorrecto.',
                'remaining' => self::MAX_ATTEMPTS - $otp['attempts'],
            ], 422);
        }

        $documentHash = hash_file('sha256', $this->dir($eid) . '/normalized.pdf');
        $now = now();

        $otp['verified'] = true;
        Cache::put("quick_otp:{$eid}", $otp, now()->addMinutes(self::OTP_MINUTES + 5));

        return response()->json([
            'audit' => [
                'reference' => $otp['reference'],
                'signer_name' => $otp['name'],
                'signer_email' => $otp['email'],
                'verified_at' => $now->toIso8601String(),
                'verified_at_human' => $now->format('d/m/Y H:i:s') . ' UTC',
                'ip_address' => $request->ip(),
                'document_hash' => $documentHash,
            ],
        ]);
    }

    /** Paso 3: recibir el PDF firmado, entregarlo (email + descarga) y NO sellar. */
    public function finalize(Request $request, string $eid): JsonResponse
    {
        $meta = $this->meta($eid);
        abort_unless($meta, 404);

        $request->validate([
            'signed' => 'required|file|mimes:pdf|max:' . (int) config('docsigner.max_upload_kb'),
        ]);

        $otp = Cache::get("quick_otp:{$eid}");
        if (! $otp || empty($otp['verified'])) {
            return response()->json(['message' => 'Verifica tu identidad antes de firmar.'], 422);
        }

        $dir = $this->dir($eid);
        $request->file('signed')->move($dir, 'signed.pdf');
        $signedPath = $dir . '/signed.pdf';

        // Entrega por email (el email verificado = email de entrega).
        try {
            $bytes = file_get_contents($signedPath);
            $name = pathinfo($meta['original_name'], PATHINFO_FILENAME) . '-firmado.pdf';
            Mail::raw(
                "Adjuntamos tu documento firmado con FirmaDoc.\n\nReferencia: {$otp['reference']}",
                function ($m) use ($otp, $bytes, $name) {
                    $m->to($otp['email'], $otp['name'])
                        ->subject('Tu documento firmado · FirmaDoc')
                        ->attachData($bytes, $name, ['mime' => 'application/pdf']);
                }
            );
        } catch (Throwable $e) {
            report($e); // no abortamos la firma si el email falla; queda la descarga
        }

        return response()->json([
            'ok' => true,
            'download_url' => route('quick.download', $eid),
        ]);
    }

    /** Descarga del PDF firmado; borra el fichero tras enviarlo. */
    public function download(string $eid)
    {
        abort_unless($this->meta($eid), 404);
        $path = $this->dir($eid) . '/signed.pdf';
        abort_unless(is_file($path), 404);

        $meta = $this->meta($eid);
        $name = pathinfo($meta['original_name'], PATHINFO_FILENAME) . '-firmado.pdf';

        return response()->download($path, $name)->deleteFileAfterSend(true);
    }
}
