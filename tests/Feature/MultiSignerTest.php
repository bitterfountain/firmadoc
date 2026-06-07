<?php

namespace Tests\Feature;

use App\Mail\SignatureInviteMail;
use App\Mail\SignatureOtpMail;
use App\Models\Document;
use App\Models\SignatureInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MultiSignerTest extends TestCase
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

    private function uploadReadyDocument(): Document
    {
        $this->post(route('documents.store'), [
            'file' => UploadedFile::fake()->createWithContent('contrato.pdf', $this->pdfBytes()),
        ]);

        return Document::firstOrFail();
    }

    private function addSigner(Document $doc, string $name, string $email): SignatureInvitation
    {
        $this->post(route('documents.invitations.store', $doc), compact('name', 'email'))
            ->assertRedirect(route('documents.signers', $doc));

        return SignatureInvitation::where('email', $email)->firstOrFail();
    }

    /** Firma completa de un invitado por su token; devuelve la respuesta de finalize. */
    private function signAs(SignatureInvitation $invitation)
    {
        $token = $invitation->token;

        $this->postJson(route('sign.otp', $token))->assertOk();
        $code = Mail::sent(SignatureOtpMail::class)->last()->code;
        $event = $invitation->document->signatureEvents()
            ->where('invitation_id', $invitation->id)->latest()->firstOrFail();

        $this->postJson(route('sign.otpVerify', $token), ['event_id' => $event->id, 'otp' => $code])
            ->assertOk();

        return $this->post(route('sign.finalize', $token), [
            'event_id' => $event->id,
            'signed' => UploadedFile::fake()->createWithContent('signed.pdf', $this->pdfBytes()),
        ]);
    }

    public function test_owner_can_invite_signers(): void
    {
        Storage::fake('local');
        Mail::fake();

        $doc = $this->uploadReadyDocument();
        $this->addSigner($doc, 'Ana', 'ana@example.com');
        $this->addSigner($doc, 'Bruno', 'bruno@example.com');

        $this->assertDatabaseCount('signature_invitations', 2);
        Mail::assertSent(SignatureInviteMail::class, 2);
        $this->assertSame([1, 2], $doc->invitations()->pluck('position')->all());
    }

    public function test_sequential_turns_are_enforced(): void
    {
        Storage::fake('local');
        Mail::fake();

        $doc = $this->uploadReadyDocument();
        $ana = $this->addSigner($doc, 'Ana', 'ana@example.com');
        $bruno = $this->addSigner($doc, 'Bruno', 'bruno@example.com');

        $this->assertTrue($ana->isMyTurn());
        $this->assertFalse($bruno->fresh()->isMyTurn());

        // Bruno (turno 2) no puede pedir OTP todavia.
        $this->postJson(route('sign.otp', $bruno->token))->assertForbidden();

        // Su pagina muestra el mensaje de espera.
        $this->get(route('sign.show', $bruno->token))->assertOk()->assertSee('Aun no es tu turno');
    }

    public function test_full_multi_signer_flow_completes_document(): void
    {
        Storage::fake('local');
        Mail::fake();

        $doc = $this->uploadReadyDocument();
        $ana = $this->addSigner($doc, 'Ana', 'ana@example.com');
        $bruno = $this->addSigner($doc, 'Bruno', 'bruno@example.com');

        // Firma Ana (1/2): documento queda en progreso.
        $this->signAs($ana)->assertOk()->assertJsonPath('all_signed', false);
        $doc->refresh();
        $this->assertSame('in_progress', $doc->status);
        $this->assertSame('signed', $ana->fresh()->status);

        // Ahora es el turno de Bruno.
        $this->assertTrue($bruno->fresh()->isMyTurn());

        // Firma Bruno (2/2): documento completado.
        $this->signAs($bruno)->assertOk()->assertJsonPath('all_signed', true);
        $doc->refresh();
        $this->assertSame('completed', $doc->status);
        $this->assertSame('signed', $bruno->fresh()->status);

        // Dos eventos de auditoria completados, uno por firmante.
        $this->assertSame(2, $doc->signatureEvents()->where('status', 'completed')->count());
    }

    public function test_signed_invitation_shows_done_message(): void
    {
        Storage::fake('local');
        Mail::fake();

        $doc = $this->uploadReadyDocument();
        $ana = $this->addSigner($doc, 'Ana', 'ana@example.com');
        $this->signAs($ana);

        $this->get(route('sign.show', $ana->token))->assertOk()->assertSee('Ya has firmado');
    }
}
