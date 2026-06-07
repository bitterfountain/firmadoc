<?php

namespace App\Http\Controllers;

use App\Mail\SignatureInviteMail;
use App\Mail\SignatureOtpMail;
use App\Models\Document;
use App\Models\SignatureEvent;
use App\Models\SignatureInvitation;
use App\Services\PadesSigningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class InvitationController extends Controller
{
    private const DISK = 'local';
    private const OTP_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    // ---------------------------------------------------------------------
    // Lado propietario: gestionar firmantes
    // ---------------------------------------------------------------------

    /** Pagina de gestion de firmantes de un documento. */
    public function index(Document $document): View|RedirectResponse
    {
        if (! $document->pdf_path) {
            return redirect()->route('documents.index')->with('error', 'El documento aun no esta listo.');
        }

        $invitations = $document->invitations()->get();

        return view('documents.signers', compact('document', 'invitations'));
    }

    /** Anade un firmante y le envia la invitacion por email. */
    public function store(Request $request, Document $document): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
        ]);

        $position = (int) $document->invitations()->max('position') + 1;

        $invitation = $document->invitations()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'token' => Str::random(64),
            'position' => $position,
            'status' => 'pending',
        ]);

        Mail::to($invitation->email)->send(new SignatureInviteMail(
            $invitation->name,
            $document->original_name,
            route('sign.show', $invitation->token),
        ));

        return redirect()->route('documents.signers', $document)
            ->with('status', "Invitacion enviada a {$invitation->email}.");
    }

    /** Elimina una invitacion pendiente. */
    public function destroy(Document $document, SignatureInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->document_id === $document->id, 404);

        if ($invitation->status === 'signed') {
            return back()->with('error', 'No se puede quitar a un firmante que ya firmo.');
        }

        $invitation->delete();

        return redirect()->route('documents.signers', $document)->with('status', 'Firmante eliminado.');
    }

    // ---------------------------------------------------------------------
    // Lado firmante: flujo publico por token
    // ---------------------------------------------------------------------

    /** Pagina publica de firma para un invitado. */
    public function show(string $token): View
    {
        $invitation = $this->resolve($token);
        $document = $invitation->document;

        if ($invitation->status === 'signed') {
            return view('invitations.message', [
                'title' => 'Ya has firmado',
                'message' => 'Gracias, tu firma ya consta en este documento.',
            ]);
        }

        if (! $invitation->isMyTurn()) {
            return view('invitations.message', [
                'title' => 'Aun no es tu turno',
                'message' => 'Este documento se firma por orden. Te avisaremos cuando te toque.',
            ]);
        }

        // Reutiliza la vista de firma con URLs basadas en el token.
        return view('documents.sign', [
            'document' => $document,
            'headerTitle' => $document->original_name,
            'backUrl' => route('sign.show', $token),
            'pdfUrl' => route('sign.pdf', $token),
            'saveUrl' => route('sign.finalize', $token),
            'otpUrl' => route('sign.otp', $token),
            'otpVerifyUrl' => route('sign.otpVerify', $token),
            'signerName' => $invitation->name,
            'signerEmail' => $invitation->email,
        ]);
    }

    /** Sirve la version actual del PDF (con las firmas previas). */
    public function pdf(string $token)
    {
        $invitation = $this->resolve($token);
        $path = $this->currentPdfPath($invitation->document);
        abort_unless($path && Storage::disk(self::DISK)->exists($path), 404);

        return Storage::disk(self::DISK)->response($path, 'documento.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /** Paso 1: enviar OTP (al email fijado en la invitacion). */
    public function requestOtp(string $token): JsonResponse
    {
        $invitation = $this->resolve($token);
        abort_unless($invitation->isMyTurn(), 403);

        $code = (string) random_int(100000, 999999);

        $event = SignatureEvent::create([
            'document_id' => $invitation->document_id,
            'invitation_id' => $invitation->id,
            'signer_name' => $invitation->name,
            'signer_email' => $invitation->email,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 512),
            'otp_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(self::OTP_MINUTES),
            'status' => 'pending',
        ]);

        Mail::to($invitation->email)->send(
            new SignatureOtpMail($code, $invitation->document->original_name, self::OTP_MINUTES)
        );

        return response()->json(['event_id' => $event->id]);
    }

    /** Paso 2: verificar OTP. */
    public function verifyOtp(Request $request, string $token): JsonResponse
    {
        $invitation = $this->resolve($token);
        $data = $request->validate(['event_id' => 'required|integer', 'otp' => 'required|string']);

        $event = SignatureEvent::where('id', $data['event_id'])
            ->where('invitation_id', $invitation->id)
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
            return response()->json(['message' => 'Codigo incorrecto.', 'remaining' => self::MAX_ATTEMPTS - $event->attempts], 422);
        }

        $originalHash = hash_file('sha256', Storage::disk(self::DISK)->path($this->currentPdfPath($invitation->document)));
        $event->update(['verified_at' => now(), 'status' => 'verified', 'original_sha256' => $originalHash]);

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

    /** Paso 3: recibir el PDF firmado; sellar PAdES solo cuando firma el ultimo. */
    public function finalize(Request $request, string $token, PadesSigningService $pades): JsonResponse
    {
        $invitation = $this->resolve($token);
        $document = $invitation->document;

        $request->validate([
            'event_id' => 'required|integer',
            'signed' => 'required|file|mimes:pdf|max:' . (int) config('docsigner.max_upload_kb'),
        ]);

        $event = SignatureEvent::where('id', $request->integer('event_id'))
            ->where('invitation_id', $invitation->id)
            ->where('status', 'verified')
            ->firstOrFail();

        $dir = "documents/{$document->id}";
        $request->file('signed')->storeAs($dir, 'signed.pdf', self::DISK);
        $signedAbs = Storage::disk(self::DISK)->path("{$dir}/signed.pdf");

        $invitation->update(['status' => 'signed', 'signed_at' => now()]);

        // ¿Quedan firmantes pendientes?
        $allSigned = ! $document->invitations()->where('status', '!=', 'signed')->exists();

        $padesApplied = false;
        if ($allSigned && $pades->isEnabled()) {
            try {
                $sealedAbs = Storage::disk(self::DISK)->path("{$dir}/sealed.pdf");
                $pades->sign($signedAbs, $sealedAbs, [
                    'reason' => 'Firma electronica multiparte con verificacion de identidad',
                    'name' => $event->signer_name,
                ]);
                @unlink($signedAbs);
                rename($sealedAbs, $signedAbs);
                $padesApplied = true;
            } catch (Throwable $e) {
                report($e);
            }
        }

        $event->update([
            'signed_sha256' => hash_file('sha256', $signedAbs),
            'pades_applied' => $padesApplied,
            'status' => 'completed',
        ]);

        $document->update([
            'signed_path' => "{$dir}/signed.pdf",
            'status' => $allSigned ? 'completed' : 'in_progress',
        ]);

        return response()->json([
            'ok' => true,
            'all_signed' => $allSigned,
            'download_url' => route('documents.download', $document),
        ]);
    }

    private function resolve(string $token): SignatureInvitation
    {
        return SignatureInvitation::with('document')->where('token', $token)->firstOrFail();
    }

    /** Ruta del PDF "actual" a firmar: la version firmada en curso, o la normalizada. */
    private function currentPdfPath(Document $document): ?string
    {
        return $document->signed_path ?: $document->pdf_path;
    }
}
