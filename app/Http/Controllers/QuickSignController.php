<?php

namespace App\Http\Controllers;

use App\Services\PdfConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/**
 * Firma rapida ANONIMA (sin registro). Flujo efimero y SIN verificacion de
 * identidad (Nivel 0): el documento se convierte y se firma en una carpeta
 * temporal local, se entrega (descarga + email OPCIONAL) y se PURGA. No usa
 * Spaces ni guarda filas en BD. Sin OTP y sin PAdES (reservado a registrados).
 */
class QuickSignController extends Controller
{
    private const TTL_MINUTES = 120;   // vida maxima de una sesion efimera

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

    /** Pantalla de firma (reutiliza la vista; sin otpUrl => Nivel 0, sin verificacion). */
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
            'otpUrl' => '',          // vacio => Nivel 0 (el JS salta la verificacion OTP)
            'otpVerifyUrl' => '',
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

    /** Recibe el PDF firmado, lo entrega (descarga + email opcional) y NO sella. */
    public function finalize(Request $request, string $eid): JsonResponse
    {
        $meta = $this->meta($eid);
        abort_unless($meta, 404);

        $data = $request->validate([
            'signed' => 'required|file|mimes:pdf|max:' . (int) config('docsigner.max_upload_kb'),
            'email' => 'nullable|email|max:190',
            'signer_name' => 'nullable|string|max:120',
            'reference' => 'nullable|string|max:40',
        ]);

        $dir = $this->dir($eid);
        $request->file('signed')->move($dir, 'signed.pdf');
        $signedPath = $dir . '/signed.pdf';

        // Entrega por email solo si el firmante dejo uno (opcional).
        if (! empty($data['email'])) {
            try {
                $bytes = file_get_contents($signedPath);
                $name = pathinfo($meta['original_name'], PATHINFO_FILENAME) . '-firmado.pdf';
                $ref = $data['reference'] ?? '';
                Mail::raw(
                    'Adjuntamos tu documento firmado con FirmaDoc.' . ($ref ? "\n\nReferencia: {$ref}" : ''),
                    function ($m) use ($data, $bytes, $name) {
                        $m->to($data['email'], $data['signer_name'] ?? null)
                            ->subject('Tu documento firmado · FirmaDoc')
                            ->attachData($bytes, $name, ['mime' => 'application/pdf']);
                    }
                );
            } catch (Throwable $e) {
                report($e); // no abortamos la firma si el email falla; queda la descarga
            }
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
