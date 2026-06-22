<?php

namespace App\Http\Controllers;

use App\Concerns\HandlesDocumentFiles;
use App\Mail\SignatureCompletedMail;
use App\Mail\SignatureInviteMail;
use App\Mail\SignatureOtpMail;
use App\Mail\WitnessNotificationMail;
use App\Models\Document;
use App\Models\SignatureEvent;
use App\Models\SignatureInvitation;
use App\Services\HttpSmsService;
use App\Services\PadesSigningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class InvitationController extends Controller
{
    use HandlesDocumentFiles;

    private const OTP_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    private const DEFAULT_EXPIRY_DAYS = 30;

    // ---------------------------------------------------------------------
    // Lado propietario: gestionar firmantes
    // ---------------------------------------------------------------------

    /** Pagina de gestion de firmantes de un documento. */
    public function index(Document $document): View|RedirectResponse
    {
        abort_unless($document->user_id === auth()->id(), 403);

        if (! $document->pdf_path) {
            return redirect()->route('documents.index')->with('error', __('El documento aún no está listo.'));
        }

        $invitations = $document->invitations()->get();

        return view('documents.signers', compact('document', 'invitations'));
    }

    /** Anade un firmante y le envia la invitacion por email. */
    public function store(Request $request, Document $document): RedirectResponse
    {
        if ($document->user_id) {
            abort_unless($document->user_id === auth()->id(), 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|string|max:30',
        ]);

        $position = (int) $document->invitations()->max('position') + 1;
        $expiry = $request->filled('expires_at')
            ? now()->addDays((int) $request->input('expires_at'))
            : now()->addDays(self::DEFAULT_EXPIRY_DAYS);

        $invitation = $document->invitations()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'token' => Str::random(64),
            'position' => $position,
            'status' => 'pending',
            'expires_at' => $expiry,
        ]);

        Mail::to($invitation->email)->send(new SignatureInviteMail(
            $invitation->name,
            $document->original_name,
            route('sign.show', $invitation->token),
            $expiry,
            $document->isSequential() ? $position : null,
        ));

        return redirect()->route('documents.signers', $document)
            ->with('status', __('Invitación enviada a :email.', ['email' => $invitation->email]));
    }

    /** Elimina una invitacion pendiente o declinada. */
    public function destroy(Document $document, SignatureInvitation $invitation): RedirectResponse
    {
        if ($document->user_id) {
            abort_unless($document->user_id === auth()->id(), 403);
        }
        abort_unless($invitation->document_id === $document->id, 404);

        if ($invitation->status === 'signed') {
            return back()->with('error', __('No se puede quitar a un firmante que ya firmó.'));
        }

        $invitation->delete();

        return redirect()->route('documents.signers', $document)->with('status', __('Firmante eliminado.'));
    }

    /** Actualiza el modo de firma del documento (sequential/parallel). */
    public function updateMode(Request $request, Document $document): RedirectResponse
    {
        if ($document->user_id) {
            abort_unless($document->user_id === auth()->id(), 403);
        }

        $data = $request->validate([
            'signing_mode' => 'required|in:sequential,parallel',
        ]);

        $document->update(['signing_mode' => $data['signing_mode']]);

        return back()->with('status', __('Modo de firma actualizado.'));
    }

    /** Define testigo (witness) para el documento. */
    public function setWitness(Request $request, Document $document): RedirectResponse
    {
        if ($document->user_id) {
            abort_unless($document->user_id === auth()->id(), 403);
        }

        $data = $request->validate([
            'witness_name' => 'required|string|max:120',
            'witness_email' => 'required|email|max:190',
        ]);

        $token = Str::random(64);

        $document->update([
            'witness_name' => $data['witness_name'],
            'witness_email' => $data['witness_email'],
            'witness_token' => $token,
        ]);

        Mail::to($data['witness_email'])->send(new WitnessNotificationMail(
            $data['witness_name'],
            $document->original_name,
            route('sign.witness', $token),
        ));

        return back()->with('status', __('Testigo añadido y notificado.'));
    }

    /** Confirma el testigo que presencio las firmas. */
    public function confirmWitness(string $token): View
    {
        $document = Document::where('witness_token', $token)->firstOrFail();

        if ($document->witness_confirmed_at) {
            return view('invitations.message', [
                'title' => __('Testigo ya confirmado'),
                'message' => __('Ya dejaste constancia como testigo de este documento.'),
            ]);
        }

        $document->update(['witness_confirmed_at' => now()]);

        return view('invitations.message', [
            'title' => __('Testigo confirmado'),
            'message' => __('Has quedado registrado como testigo del documento ":doc".', ['doc' => $document->original_name]),
        ]);
    }

    /** Actualiza la URL de webhook del documento. */
    public function updateWebhook(Request $request, Document $document): RedirectResponse
    {
        if ($document->user_id) {
            abort_unless($document->user_id === auth()->id(), 403);
        }

        $data = $request->validate([
            'webhook_url' => 'nullable|url|max:500',
        ]);

        $document->update(['webhook_url' => $data['webhook_url']]);

        return back()->with('status', __('Webhook actualizado.'));
    }

    // ---------------------------------------------------------------------
    // Lado firmante: flujo publico por token
    // ---------------------------------------------------------------------

    /** Pagina publica de firma para un invitado (portal del firmante). */
    public function show(string $token): View
    {
        $invitation = $this->resolve($token);
        $document = $invitation->document;

        if ($invitation->status === 'signed') {
            return view('invitations.portal', [
                'invitation' => $invitation,
                'document' => $document,
                'allInvitations' => $document->invitations()->get(),
                'alreadySigned' => true,
            ]);
        }

        if ($invitation->status === 'declined') {
            return view('invitations.message', [
                'title' => __('Has declinado firmar'),
                'message' => __('Registramos que no firmarás este documento.'),
            ]);
        }

        if ($invitation->isExpired()) {
            return view('invitations.message', [
                'title' => __('Invitación caducada'),
                'message' => __('El plazo para firmar este documento ha vencido. Contacta con quien te invitó.'),
            ]);
        }

        if (! $invitation->isMyTurn()) {
            return view('invitations.portal', [
                'invitation' => $invitation,
                'document' => $document,
                'allInvitations' => $document->invitations()->get(),
                'notYourTurn' => true,
            ]);
        }

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

    /** Declina firmar. */
    public function decline(string $token): RedirectResponse
    {
        $invitation = $this->resolve($token);

        if ($invitation->status !== 'pending') {
            return redirect()->route('sign.show', $token);
        }

        $invitation->update(['status' => 'declined', 'declined_at' => now()]);

        $this->notifyWebhook($invitation->document, 'signer_declined', [
            'name' => $invitation->name,
            'email' => $invitation->email,
            'position' => $invitation->position,
        ]);

        return redirect()->route('sign.show', $token);
    }

    /** Sirve la version actual del PDF (con las firmas previas). */
    public function pdf(string $token)
    {
        $invitation = $this->resolve($token);
        $path = $this->currentPdfPath($invitation->document);
        abort_unless($path && Storage::disk($this->docDisk())->exists($path), 404);

        return Storage::disk($this->docDisk())->response($path, 'documento.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /** Paso 1: enviar OTP (al email fijado en la invitacion). */
    public function requestOtp(Request $request, string $token): JsonResponse
    {
        $invitation = $this->resolve($token);
        abort_unless($invitation->isMyTurn(), 403);

        $code = (string) random_int(100000, 999999);
        $verificationMethod = $request->input('method', 'email');

        if ($verificationMethod === 'sms' && ! $request->filled('phone')) {
            return response()->json(['message' => __('Se requiere un teléfono para verificación SMS.')], 422);
        }

        $event = SignatureEvent::create([
            'document_id' => $invitation->document_id,
            'invitation_id' => $invitation->id,
            'signer_name' => $invitation->name,
            'signer_email' => $invitation->email,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 512),
            'otp_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(self::OTP_MINUTES),
            'phone' => $request->input('phone'),
            'verification_method' => $verificationMethod,
            'status' => 'pending',
        ]);

        if ($verificationMethod !== 'sms') {
            Mail::to($invitation->email)->send(
                new SignatureOtpMail($code, $invitation->document->original_name, self::OTP_MINUTES)
            );
        }

        $smsSent = false;
        if ($verificationMethod === 'sms' || $request->boolean('sms_also')) {
            $sms = app(HttpSmsService::class);
            $result = $sms->send(
                to: $request->input('phone', ''),
                content: "Tu codigo de verificacion Docsigner: {$code}",
                from: 'Docsigner',
            );
            $smsSent = $result['success'] ?? false;
        }

        return response()->json([
            'event_id' => $event->id,
            'sms_sent' => $smsSent,
        ]);
    }

    /** Paso 2: verificar OTP + opcionalmente subir DNI o certificado propio. */
    public function verifyOtp(Request $request, string $token): JsonResponse
    {
        $invitation = $this->resolve($token);
        $data = $request->validate([
            'event_id' => 'required|integer',
            'otp' => 'required|string',
            'id_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $event = SignatureEvent::where('id', $data['event_id'])
            ->where('invitation_id', $invitation->id)
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

            return response()->json(['message' => __('Código incorrecto.'), 'remaining' => self::MAX_ATTEMPTS - $event->attempts], 422);
        }

        // Guardar documento de identidad si se subio
        $idDocPath = null;
        if ($request->hasFile('id_document')) {
            $idDir = "documents/{$invitation->document_id}/id_docs";
            $idDocPath = $request->file('id_document')->store("{$idDir}/{$event->id}", $this->docDisk());
        }

        $work = $this->tempWorkDir();
        try {
            $localPdf = $work.DIRECTORY_SEPARATOR.'original.pdf';
            $this->pullToLocal($this->currentPdfPath($invitation->document), $localPdf);
            $originalHash = hash_file('sha256', $localPdf);
        } finally {
            $this->cleanupTemp($work);
        }

        $event->update([
            'verified_at' => now(),
            'status' => 'verified',
            'original_sha256' => $originalHash,
            'id_document_path' => $idDocPath,
            'phone_verified_at' => $event->verification_method === 'sms' ? now() : null,
        ]);

        return response()->json([
            'audit' => [
                'reference' => $event->reference,
                'signer_name' => $event->signer_name,
                'signer_email' => $event->signer_email,
                'verified_at' => $event->verified_at->toIso8601String(),
                'verified_at_human' => $event->verified_at->format('d/m/Y H:i:s').' UTC',
                'ip_address' => $event->ip_address,
                'document_hash' => $originalHash,
                'verification_method' => $event->verification_method,
                'phone' => $event->phone,
                'id_document_attached' => $idDocPath !== null,
            ],
        ]);
    }

    /** Paso 3: recibir el PDF firmado; sellar PAdES. */
    public function finalize(Request $request, string $token, PadesSigningService $pades): JsonResponse
    {
        $invitation = $this->resolve($token);
        $document = $invitation->document;

        $request->validate([
            'event_id' => 'required|integer',
            'signed' => 'required|file|mimes:pdf|max:'.(int) config('docsigner.max_upload_kb'),
            'signing_cert' => 'nullable|file|mimes:p12,pfx|max:10240',
            'cert_password' => 'nullable|string|max:255',
        ]);

        $event = SignatureEvent::where('id', $request->integer('event_id'))
            ->where('invitation_id', $invitation->id)
            ->where('status', 'verified')
            ->firstOrFail();

        $dir = "documents/{$document->id}";

        // Guardar certificado propio del firmante si subio uno
        if ($request->hasFile('signing_cert')) {
            $certContent = base64_encode(file_get_contents($request->file('signing_cert')->getRealPath()));
            $event->update([
                'signing_cert' => $certContent,
                'signing_cert_password' => $request->input('cert_password'),
                'signing_cert_subject' => $request->input('cert_subject'),
            ]);
        }

        $invitation->update(['status' => 'signed', 'signed_at' => now()]);

        $allSigned = $document->allSigned();

        $work = $this->tempWorkDir();
        $padesApplied = false;

        try {
            $signedAbs = $work.DIRECTORY_SEPARATOR.'signed.pdf';
            copy($request->file('signed')->getRealPath(), $signedAbs);

            $finalAbs = $signedAbs;
            if ($allSigned && $pades->isEnabled()) {
                try {
                    $sealedAbs = $work.DIRECTORY_SEPARATOR.'sealed.pdf';
                    $override = $this->certOverrideForEvent($document, $event, $work);
                    $pades->sign($signedAbs, $sealedAbs, [
                        'reason' => 'Firma electronica multiparte con verificacion de identidad',
                        'name' => $event->signer_name,
                    ], $override);
                    $finalAbs = $sealedAbs;
                    $padesApplied = true;
                } catch (Throwable $e) {
                    report($e);
                }
            }

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

        $newStatus = $allSigned ? 'completed' : 'in_progress';
        $document->update([
            'signed_path' => "{$dir}/signed.pdf",
            'status' => $newStatus,
        ]);

        // Notificar webhook si hay
        $this->notifyWebhook($document, 'signer_completed', [
            'name' => $invitation->name,
            'email' => $invitation->email,
            'all_signed' => $allSigned,
            'remaining' => $document->pendingInvitations()->count(),
        ]);

        // Si todos firmaron, enviar comprobantes
        if ($allSigned) {
            $this->sendCompletionEmails($document, $padesApplied);
            $this->notifyWitnessIfSet($document);
            $this->notifyWebhook($document, 'document_completed', [
                'document_name' => $document->original_name,
                'signers' => $document->invitations()->count(),
                'pades_applied' => $padesApplied,
            ]);
        }

        return response()->json([
            'ok' => true,
            'all_signed' => $allSigned,
            'download_url' => $this->downloadUrl($document),
        ]);
    }

    /** Reenviar OTP (desde el portal del firmante, sin pasar por el dueno). */
    public function resendOtp(string $token): RedirectResponse
    {
        $invitation = $this->resolve($token);
        abort_unless($invitation->isPending() && $invitation->isMyTurn(), 403);

        return redirect()->route('sign.show', $token);
    }

    // ---------------------------------------------------------------------
    // Helpers privados
    // ---------------------------------------------------------------------

    private function resolve(string $token): SignatureInvitation
    {
        return SignatureInvitation::with('document')->where('token', $token)->firstOrFail();
    }

    /** Ruta del PDF "actual" a firmar. */
    private function currentPdfPath(Document $document): ?string
    {
        return $document->signed_path ?: $document->pdf_path;
    }

    private function downloadUrl(Document $document): string
    {
        if ($document->user_id) {
            return route('documents.download', $document);
        }

        return route('quick.multi.download', $document->id);
    }

    /** Override de certificado para PAdES: el del firmante si subio, o el del dueno. */
    private function certOverrideForEvent(Document $document, SignatureEvent $event, string $work): array
    {
        if (! empty($event->signing_cert)) {
            $p12 = $work.DIRECTORY_SEPARATOR.'signer.p12';
            file_put_contents($p12, base64_decode($event->signing_cert));

            return [
                'backend' => 'pkcs12',
                'p12' => $p12,
                'p12_pass' => (string) $event->signing_cert_password,
            ];
        }

        return $this->ownerCertOverride($document, $work);
    }

    /** Envia el PDF firmado + comprobante de auditoria a cada firmante. */
    private function sendCompletionEmails(Document $document, bool $padesApplied): void
    {
        $disk = Storage::disk($this->docDisk());
        $path = $document->signed_path;
        if (! $path || ! $disk->exists($path)) {
            return;
        }

        $pdfBytes = $disk->get($path);
        $name = pathinfo($document->original_name, PATHINFO_FILENAME).'-firmado.pdf';

        foreach ($document->invitations()->where('status', 'signed')->get() as $inv) {
            try {
                Mail::to($inv->email)->send(new SignatureCompletedMail(
                    $inv->name,
                    $document->original_name,
                    $pdfBytes,
                    $name,
                    $padesApplied,
                ));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /** Notifica al testigo cuando todas las firmas se completan. */
    private function notifyWitnessIfSet(Document $document): void
    {
        if (! $document->witness_email || ! $document->witness_name) {
            return;
        }

        try {
            Mail::to($document->witness_email)->send(new WitnessNotificationMail(
                $document->witness_name,
                $document->original_name,
                route('sign.witness', $document->witness_token),
                true,
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Envia notificacion webhook si el documento tiene URL configurada. */
    private function notifyWebhook(Document $document, string $event, array $payload): void
    {
        if (! $document->webhook_url) {
            return;
        }

        try {
            Http::timeout(10)->post($document->webhook_url, array_merge($payload, [
                'event' => $event,
                'document_id' => $document->id,
                'document_name' => $document->original_name,
            ]));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
