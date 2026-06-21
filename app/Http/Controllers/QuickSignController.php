<?php

namespace App\Http\Controllers;

use App\Mail\SignatureInviteMail;
use App\Models\Document;
use App\Services\PdfConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Firma rapida ANONIMA (sin registro). Flujo efimero y SIN verificacion de
 * identidad (Nivel 0): el documento se convierte y se firma en una carpeta
 * temporal local, se entrega (descarga + email OPCIONAL) y se PURGA. No usa
 * Spaces ni guarda filas en BD. Sin OTP y sin PAdES (reservado a registrados).
 *
 * MODO MULTI-FIRMANTE: permite invitar a varios firmantes sin cuenta Pro.
 * El documento se persiste en BD (user_id=null) y Spaces, con invitaciones.
 * Cada firmante recibe un enlace con token como en el flujo Pro, pero solo
 * Nivel 0 (sin OTP, sin PAdES). Tras 7 dias de la ultima firma, se purga.
 */
class QuickSignController extends Controller
{
    private const TTL_MINUTES = 120;

    private const MULTI_TTL_DAYS = 7;

    // =========================================================================
    // Flujo simple (Nivel 0, efimero) — existente
    // =========================================================================

    private function baseDir(): string
    {
        return storage_path('app/ephemeral');
    }

    private function dir(string $eid): string
    {
        return $this->baseDir().DIRECTORY_SEPARATOR.$eid;
    }

    private function meta(string $eid): ?array
    {
        return Cache::get("quick:{$eid}");
    }

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

    /** Formulario de subida (simple + multi). */
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
                'max:'.(int) config('docsigner.max_upload_kb'),
                'mimes:'.implode(',', config('docsigner.allowed_extensions')),
            ],
            'mode' => 'nullable|in:simple,multi',
        ], [
            'file.mimes' => 'Formato no soportado. Acepta: '.implode(', ', config('docsigner.allowed_extensions')).'.',
        ]);

        $this->purgeStale();

        if ($request->input('mode') === 'multi') {
            return $this->uploadForMulti($request, $converter);
        }

        return $this->uploadSimple($request, $converter);
    }

    private function uploadSimple(Request $request, PdfConversionService $converter): RedirectResponse
    {
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $eid = bin2hex(random_bytes(16));
        $dir = $this->dir($eid);
        File::ensureDirectoryExists($dir, 0775);

        try {
            $source = $dir.DIRECTORY_SEPARATOR."original.{$ext}";
            $file->move($dir, "original.{$ext}");
            $converter->normalizeToPdf($source, $ext, $dir);
            @unlink($source);
        } catch (Throwable $e) {
            File::deleteDirectory($dir);

            return redirect()->route('quick.start')
                ->with('error', __('No se pudo procesar el documento: :error', ['error' => $e->getMessage()]));
        }

        Cache::put("quick:{$eid}", [
            'original_name' => $file->getClientOriginalName(),
        ], now()->addMinutes(self::TTL_MINUTES));

        return redirect()->route('quick.sign', $eid);
    }

    /** Pantalla de firma (reutiliza la vista; sin otpUrl => Nivel 0). */
    public function sign(string $eid): View|RedirectResponse
    {
        $meta = $this->meta($eid);
        if (! $meta || ! is_file($this->dir($eid).'/normalized.pdf')) {
            return redirect()->route('quick.start')
                ->with('error', __('La sesión de firma ha caducado. Sube el documento de nuevo.'));
        }

        return view('documents.sign', [
            'headerTitle' => $meta['original_name'],
            'backUrl' => route('quick.start'),
            'pdfUrl' => route('quick.pdf', $eid),
            'saveUrl' => route('quick.finalize', $eid),
            'otpUrl' => '',
            'otpVerifyUrl' => '',
            'signerName' => null,
            'signerEmail' => null,
        ]);
    }

    public function pdf(string $eid)
    {
        abort_unless($this->meta($eid), 404);
        $path = $this->dir($eid).'/normalized.pdf';
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Content-Type' => 'application/pdf']);
    }

    public function finalize(Request $request, string $eid): JsonResponse
    {
        $meta = $this->meta($eid);
        abort_unless($meta, 404);

        $data = $request->validate([
            'signed' => 'required|file|mimes:pdf|max:'.(int) config('docsigner.max_upload_kb'),
            'email' => 'nullable|email|max:190',
            'signer_name' => 'nullable|string|max:120',
            'reference' => 'nullable|string|max:40',
        ]);

        $dir = $this->dir($eid);
        $request->file('signed')->move($dir, 'signed.pdf');
        $signedPath = $dir.'/signed.pdf';

        if (! empty($data['email'])) {
            try {
                $bytes = file_get_contents($signedPath);
                $name = pathinfo($meta['original_name'], PATHINFO_FILENAME).'-firmado.pdf';
                $ref = $data['reference'] ?? '';
                Mail::raw(
                    'Adjuntamos tu documento firmado con FirmaDoc.'.($ref ? "\n\nReferencia: {$ref}" : ''),
                    function ($m) use ($data, $bytes, $name) {
                        $m->to($data['email'], $data['signer_name'] ?? null)
                            ->subject('Tu documento firmado · FirmaDoc')
                            ->attachData($bytes, $name, ['mime' => 'application/pdf']);
                    }
                );
            } catch (Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'ok' => true,
            'download_url' => route('quick.download', $eid),
        ]);
    }

    public function download(string $eid)
    {
        abort_unless($this->meta($eid), 404);
        $path = $this->dir($eid).'/signed.pdf';
        abort_unless(is_file($path), 404);

        $meta = $this->meta($eid);
        $name = pathinfo($meta['original_name'], PATHINFO_FILENAME).'-firmado.pdf';

        return response()->download($path, $name)->deleteFileAfterSend(true);
    }

    // =========================================================================
    // Flujo multi-firmante anonimo (sin cuenta, Nivel 0)
    // =========================================================================

    private function uploadForMulti(Request $request, PdfConversionService $converter): RedirectResponse
    {
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());

        $signers = $request->input('signers', []);
        if (empty($signers) || ! is_array($signers)) {
            return redirect()->route('quick.start')
                ->with('error', __('Añade al menos un firmante para el modo multi-firmante.'));
        }

        $document = Document::create([
            'user_id' => null,
            'original_name' => $file->getClientOriginalName(),
            'source_format' => $ext,
            'status' => 'uploaded',
            'signing_mode' => $request->input('signing_mode', 'parallel'),
        ]);

        $dir = "documents/{$document->id}";
        $work = null;
        $disk = Storage::disk(config('docsigner.disk', 'local'));

        try {
            $work = sys_get_temp_dir().DIRECTORY_SEPARATOR.'firmadoc_multi_'.Str::random(16);
            File::ensureDirectoryExists($work, 0775);

            $sourceAbs = $work.DIRECTORY_SEPARATOR."original.{$ext}";
            copy($file->getRealPath(), $sourceAbs);

            $pdfAbs = $converter->normalizeToPdf($sourceAbs, $ext, $work);

            $disk->put("{$dir}/normalized.pdf", fopen($pdfAbs, 'r'));

            $document->update([
                'pdf_path' => "{$dir}/normalized.pdf",
                'status' => 'ready',
            ]);
        } catch (Throwable $e) {
            $document->update(['status' => 'failed', 'error' => $e->getMessage()]);

            return redirect()->route('quick.start')
                ->with('error', __('No se pudo procesar el documento: :error', ['error' => $e->getMessage()]));
        } finally {
            if ($work && is_dir($work)) {
                File::deleteDirectory($work);
            }
        }

        // Crear invitaciones
        foreach ($signers as $i => $signer) {
            if (empty($signer['email'])) {
                continue;
            }
            $invitation = $document->invitations()->create([
                'name' => $signer['name'] ?? '',
                'email' => $signer['email'],
                'token' => Str::random(64),
                'position' => $i + 1,
                'status' => 'pending',
                'expires_at' => now()->addDays(self::MULTI_TTL_DAYS),
            ]);

            try {
                Mail::to($invitation->email)->send(new SignatureInviteMail(
                    $invitation->name,
                    $document->original_name,
                    route('sign.show', $invitation->token),
                    $invitation->expires_at,
                    $document->isSequential() ? $invitation->position : null,
                ));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('quick.multi.manage', $document->id)
            ->with('status', __('Documento listo. Las invitaciones se han enviado.'));
    }

    /** Panel de gestion del documento multi-firmante anonimo (muestra token maestro). */
    public function manage(int $id): View|RedirectResponse
    {
        $document = Document::whereNull('user_id')->findOrFail($id);
        $invitations = $document->invitations()->get();

        return view('quick.manage', compact('document', 'invitations'));
    }

    /** Descarga del PDF firmado (anonimo multi). */
    public function multiDownload(int $id)
    {
        $document = Document::whereNull('user_id')->findOrFail($id);
        $path = $document->signed_path ?? $document->pdf_path;
        abort_unless($path && Storage::disk(config('docsigner.disk', 'local'))->exists($path), 404);

        $name = pathinfo($document->original_name, PATHINFO_FILENAME).'-firmado.pdf';

        return Storage::disk(config('docsigner.disk', 'local'))->download($path, $name);
    }
}
