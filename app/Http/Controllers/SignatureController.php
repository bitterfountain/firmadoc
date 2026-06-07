<?php

namespace App\Http\Controllers;

use App\Mail\SignatureOtpMail;
use App\Models\Document;
use App\Models\SignatureEvent;
use App\Services\PadesSigningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SignatureController extends Controller
{
    private const DISK = 'local';
    private const OTP_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    /** Paso 1: el firmante introduce nombre + email; enviamos un OTP. */
    public function requestOtp(Request $request, Document $document): JsonResponse
    {
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
        $data = $request->validate([
            'event_id' => 'required|integer',
            'otp' => 'required|string',
        ]);

        $event = SignatureEvent::where('id', $data['event_id'])
            ->where('document_id', $document->id)
            ->firstOrFail();

        if (! $event->isOtpValid()) {
            return response()->json(['message' => 'El codigo ha caducado. Solicita uno nuevo.'], 422);
        }

        if ($event->attempts >= self::MAX_ATTEMPTS) {
            $event->update(['status' => 'expired']);
            return response()->json(['message' => 'Demasiados intentos. Solicita un codigo nuevo.'], 422);
        }

        if (! Hash::check($data['otp'], $event->otp_hash)) {
            $event->increment('attempts');
            return response()->json([
                'message' => 'Codigo incorrecto.',
                'remaining' => self::MAX_ATTEMPTS - $event->attempts,
            ], 422);
        }

        // OTP correcto: marcamos verificado y calculamos el hash del documento original.
        $originalHash = hash_file('sha256', Storage::disk(self::DISK)->path($document->pdf_path));

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
        $data = $request->validate([
            'event_id' => 'required|integer',
            'signed' => 'required|file|mimes:pdf|max:' . (int) config('docsigner.max_upload_kb'),
        ]);

        $event = SignatureEvent::where('id', $data['event_id'])
            ->where('document_id', $document->id)
            ->where('status', 'verified')
            ->firstOrFail();

        $dir = "documents/{$document->id}";
        $request->file('signed')->storeAs($dir, 'signed.pdf', self::DISK);
        $signedAbs = Storage::disk(self::DISK)->path("{$dir}/signed.pdf");

        // Nivel 2: sellado criptografico PAdES como ultimo paso. Si falla o esta
        // deshabilitado, el documento sigue valido en Nivel 1 (no rompemos el flujo).
        $padesApplied = false;
        if ($pades->isEnabled()) {
            try {
                $sealedAbs = Storage::disk(self::DISK)->path("{$dir}/sealed.pdf");
                $pades->sign($signedAbs, $sealedAbs, [
                    'reason' => 'Firma electronica con verificacion de identidad por email',
                    'name' => $event->signer_name,
                ]);
                @unlink($signedAbs);
                rename($sealedAbs, $signedAbs);
                $padesApplied = true;
            } catch (Throwable $e) {
                report($e); // se registra, pero no aborta la firma
            }
        }

        // Hash final sobre la version definitiva (sellada o no).
        $signedHash = hash_file('sha256', $signedAbs);

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
