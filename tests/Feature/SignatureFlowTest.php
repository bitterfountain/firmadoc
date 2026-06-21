<?php

namespace Tests\Feature;

use App\Mail\SignatureOtpMail;
use App\Models\Document;
use App\Models\SignatureEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignatureFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function pdfBytes(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    }

    /** Sube un PDF y devuelve el documento ya normalizado (status ready). */
    private function uploadReadyDocument(): Document
    {
        $file = UploadedFile::fake()->createWithContent('contrato.pdf', $this->pdfBytes());
        $this->post(route('documents.store'), ['file' => $file]);

        return Document::firstOrFail();
    }

    /** Pide un OTP y devuelve [evento, codigo en claro] capturado del mail. */
    private function requestOtp(Document $doc): array
    {
        $this->postJson(route('documents.otp', $doc), [
            'signer_name' => 'Juan Perez',
            'signer_email' => 'juan@example.com',
        ])->assertOk()->assertJsonStructure(['event_id']);

        $code = null;
        Mail::assertSent(SignatureOtpMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return [SignatureEvent::firstOrFail(), $code];
    }

    public function test_full_otp_signing_flow(): void
    {
        Storage::fake('local');
        Mail::fake();

        $doc = $this->uploadReadyDocument();
        [$event, $code] = $this->requestOtp($doc);

        $this->assertSame('pending', $event->status);
        $this->assertNotNull($code);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        // Verificar OTP.
        $this->postJson(route('documents.otpVerify', $doc), [
            'event_id' => $event->id,
            'otp' => $code,
        ])->assertOk()
            ->assertJsonPath('audit.signer_name', 'Juan Perez')
            ->assertJsonStructure(['audit' => ['reference', 'document_hash', 'verified_at']]);

        $event->refresh();
        $this->assertSame('verified', $event->status);
        $this->assertNotNull($event->verified_at);
        $this->assertSame(64, strlen($event->original_sha256));

        // Finalizar (subir el PDF firmado). PAdES desactivado en testing.
        $signed = UploadedFile::fake()->createWithContent('signed.pdf', $this->pdfBytes());
        $this->post(route('documents.storeSigned', $doc), [
            'event_id' => $event->id,
            'signed' => $signed,
        ])->assertOk()->assertJsonPath('ok', true);

        $event->refresh();
        $doc->refresh();
        $this->assertSame('completed', $event->status);
        $this->assertSame(64, strlen($event->signed_sha256));
        $this->assertSame('signed', $doc->status);
        $this->assertNotNull($doc->signed_path);
    }

    public function test_wrong_otp_is_rejected_and_counts_attempts(): void
    {
        Storage::fake('local');
        Mail::fake();

        $doc = $this->uploadReadyDocument();
        [$event] = $this->requestOtp($doc);

        $this->postJson(route('documents.otpVerify', $doc), [
            'event_id' => $event->id,
            'otp' => '000000',
        ])->assertStatus(422)->assertJsonStructure(['message', 'remaining']);

        $event->refresh();
        $this->assertSame(1, $event->attempts);
        $this->assertSame('pending', $event->status);
    }

    public function test_expired_otp_is_rejected(): void
    {
        Storage::fake('local');
        Mail::fake();

        $doc = $this->uploadReadyDocument();
        [$event, $code] = $this->requestOtp($doc);

        // Forzamos la caducidad.
        $event->update(['otp_expires_at' => now()->subMinute()]);

        $this->postJson(route('documents.otpVerify', $doc), [
            'event_id' => $event->id,
            'otp' => $code,
        ])->assertStatus(422);

        $event->refresh();
        $this->assertNull($event->verified_at);
    }

    public function test_finalize_requires_verified_event(): void
    {
        Storage::fake('local');
        Mail::fake();

        $doc = $this->uploadReadyDocument();
        [$event] = $this->requestOtp($doc); // queda 'pending', no verificado

        $signed = UploadedFile::fake()->createWithContent('signed.pdf', $this->pdfBytes());
        $this->post(route('documents.storeSigned', $doc), [
            'event_id' => $event->id,
            'signed' => $signed,
        ])->assertNotFound(); // firstOrFail sobre status 'verified'

        $doc->refresh();
        $this->assertNotSame('signed', $doc->status);
    }

    public function test_audit_page_lists_events(): void
    {
        Storage::fake('local');
        Mail::fake();

        $doc = $this->uploadReadyDocument();
        [$event, $code] = $this->requestOtp($doc);
        $this->postJson(route('documents.otpVerify', $doc), ['event_id' => $event->id, 'otp' => $code]);

        $this->get(route('documents.audit', $doc))
            ->assertOk()
            ->assertSee('juan@example.com')
            ->assertSee($event->fresh()->reference);
    }
}
