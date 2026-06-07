<?php

namespace Tests\Feature;

use App\Mail\SignatureOtpMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QuickSignTest extends TestCase
{
    use RefreshDatabase;

    private function pdfBytes(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    }

    public function test_anonymous_quick_sign_is_ephemeral(): void
    {
        Mail::fake();

        // Subida sin login.
        $file = UploadedFile::fake()->createWithContent('rapido.pdf', $this->pdfBytes());
        $res = $this->post(route('quick.upload'), ['file' => $file])->assertRedirect();
        $eid = basename((string) $res->headers->get('Location'));

        try {
            $this->get(route('quick.sign', $eid))->assertOk();

            // OTP por email.
            $this->postJson(route('quick.otp', $eid), [
                'signer_name' => 'Ana',
                'signer_email' => 'ana@example.com',
            ])->assertOk()->assertJsonStructure(['event_id']);

            $code = Mail::sent(SignatureOtpMail::class)->last()->code;

            $this->postJson(route('quick.otpVerify', $eid), ['otp' => $code])
                ->assertOk()
                ->assertJsonPath('audit.signer_email', 'ana@example.com')
                ->assertJsonStructure(['audit' => ['reference', 'document_hash']]);

            // Finalizar: entrega + descarga, sin persistir nada.
            $signed = UploadedFile::fake()->createWithContent('signed.pdf', $this->pdfBytes());
            $this->post(route('quick.finalize', $eid), ['signed' => $signed])
                ->assertOk()
                ->assertJsonPath('ok', true)
                ->assertJsonStructure(['download_url']);

            // No se ha creado ninguna fila de documento.
            $this->assertDatabaseCount('documents', 0);
        } finally {
            File::deleteDirectory(storage_path('app/ephemeral/' . $eid));
        }
    }

    public function test_expired_session_redirects(): void
    {
        $this->get(route('quick.sign', 'deadbeefdeadbeefdeadbeefdeadbeef'))
            ->assertRedirect(route('quick.start'));
    }

    public function test_finalize_requires_verified_otp(): void
    {
        Mail::fake();
        $file = UploadedFile::fake()->createWithContent('rapido.pdf', $this->pdfBytes());
        $res = $this->post(route('quick.upload'), ['file' => $file])->assertRedirect();
        $eid = basename((string) $res->headers->get('Location'));

        try {
            $signed = UploadedFile::fake()->createWithContent('signed.pdf', $this->pdfBytes());
            $this->post(route('quick.finalize', $eid), ['signed' => $signed])->assertStatus(422);
        } finally {
            File::deleteDirectory(storage_path('app/ephemeral/' . $eid));
        }
    }
}
