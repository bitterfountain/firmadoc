<?php

namespace App\Http\Controllers;

use App\Concerns\HandlesDocumentFiles;
use App\Mail\SignatureOtpMail;
use App\Models\Document;
use App\Models\SignatureEvent;
use App\Services\PadesSigningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SignatureController extends Controller
{
    use HandlesDocumentFiles;

    private const OTP_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    /** Paso 1: el firmante introduce nombre + email; enviamos un OTP. */
    public function requestOtp(Request $request, Document $document): JsonResponse
    {
        abort_unless($document->user_id === auth()->id(), 403);
        abort_unless($document->isReadyToSign() || $document->status === 'signed', 404);

        $data = $request->validate([
            'signer_name' => 'required|string|max:120',
            'signer_email' => 'required|email|max:190',
        ]);

        $code = (string) random_int(100000, 999999);

        $event = SignatureEvent::create([
            'document_id' => $document->id,
            'signer_name' => $data['signer_name'],
            'signer_email' => $data['signer_email'],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'otp_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(self::OTP_MINUTES),
            'status' => 'pending',
        ]);

        Mail::to($data['signer_email'])->send(
            new SignatureOtpMail($code, $document->original_name, self::OTP_MINUTES)
        );

        return response()->json(['event_id' => $event->id]);
    }

    /** Paso 2: verificamos el OTP y devolvemos los datos de auditoria. */
    public function verifyOtp(Request $request, Document $document): JsonResponse
    {
        abort_unless($document->user_id === auth()->id(), 403);

        $data = $request->validate([
            'event_id' => 'required|integer',
            'otp' => 'required|string',
        ]);

        $event = SignatureEvent::where('id', $data['event_id'])
            ->where('document_id', $document->id)
            ->firstOrFail();

        if (! $event->isOtpValid()) {
            return response()->json(['message' => __('El código ha caducado. Solicita uno nuevo.')], 422);
        }

        if ($event->attempts >= self::MAX_ATTEMPTS) {
            $event->update(['status' => 'expired']);
            return response()->json(['message' => __('Demasiados intentos. Solicita un código nuevo.')], 422);
        }

        if (! Hash::check($data['otp'], $event->otp_hash)) {
            $event->increment('attempts');
            return response()->json([
                'message' => __('Código incorrecto.'),
                'remaining' => self::MAX_ATTEMPTS - $event->attempts,
            ], 422);
        }

        // OTP correcto: marcamos verificado y calculamos el hash del documento original.
        $work = $this->tempWorkDir();
        try {
            $localPdf = $work.DIRECTORY_SEPARATOR.'original.pdf';
            $this->pullToLocal($document->pdf_path, $localPdf);
            $originalHash = hash_file('sha256', $localPdf);
        } finally {
            $this->cleanupTemp($work);
        }

        $event->update([
            'verified_at' => now(),
            'status' => 'verified',
            'original_sha256' => $originalHash,
        ]);

        return response()->json([
            'audit' => [
                'reference' => $event->reference,
                'signer_name' => $event->signer_name,
                'signer_email' => $event->signer_email,
                'verified_at' => $event->verified_at->toIso8601String(),
                'verified_at_human' => $event->verified_at->format('d/m/Y H:i:s') . ' UTC',
                'ip_address' => $event->ip_address,
                'document_hash' => $originalHash,
            ],
        ]);
    }

    /** Paso 3: recibimos el PDF firmado (ya con la pagina-certificado) y cerramos. */
    public function finalize(Request $request, Document $document, PadesSigningService $pades): JsonResponse
    {
        abort_unless($document->user_id === auth()->id(), 403);

        $data = $request->validate([
            'event_id' => 'required|integer',
            'signed' => 'required|file|mimes:pdf|max:' . (int) config('docsigner.max_upload_kb'),
        ]);

        $event = SignatureEvent::where('id', $data['event_id'])
            ->where('document_id', $document->id)
            ->where('status', 'verified')
            ->firstOrFail();

        $dir = "documents/{$document->id}";
        $work = $this->tempWorkDir();
        $padesApplied = false;

        try {
            // Trabajamos en local; el resultado definitivo se sube al disco.
            $signedAbs = $work.DIRECTORY_SEPARATOR.'signed.pdf';
            copy($request->file('signed')->getRealPath(), $signedAbs);

            // Nivel 2: sellado criptografico PAdES como ultimo paso. Si falla o esta
            // deshabilitado, el documento sigue valido en Nivel 1 (no rompemos el flujo).
            $finalAbs = $signedAbs;
            if ($pades->isEnabled()) {
                try {
                    $sealedAbs = $work.DIRECTORY_SEPARATOR.'sealed.pdf';
                    $override = $this->ownerCertOverride($document, $work);
                    $pades->sign($signedAbs, $sealedAbs, [
                        'reason' => 'Firma electronica con verificacion de identidad por email',
                        'name' => $event->signer_name,
                    ], $override);
                    $finalAbs = $sealedAbs;
                    $padesApplied = true;
                } catch (Throwable $e) {
                    report($e); // se registra, pero no aborta la firma
                }
            }

            // Subimos la version definitiva (sellada o no) y calculamos su hash.
            $this->pushFromLocal($finalAbs, "{$dir}/signed.pdf");
            $signedHash = hash_file('sha256', $finalAbs);
        } finally {
            $this->cleanupTemp($work);
        }

        $event->update([
            'signed_sha256' => $signedHash,
            'pades_applied' => $padesApplied,
            'status' => 'completed',
        ]);
        $document->update(['signed_path' => "{$dir}/signed.pdf", 'status' => 'signed']);

        return response()->json([
            'ok' => true,
            'pades_applied' => $padesApplied,
            'download_url' => route('documents.download', $document),
        ]);
    }
}
