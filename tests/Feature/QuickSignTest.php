<?php

namespace Tests\Feature;

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

    /** Sube un PDF de forma anonima y devuelve el eid de la sesion efimera. */
    private function uploadQuick(): string
    {
        $file = UploadedFile::fake()->createWithContent('rapido.pdf', $this->pdfBytes());
        $res = $this->post(route('quick.upload'), ['file' => $file])->assertRedirect();

        return basename((string) $res->headers->get('Location'));
    }

    public function test_anonymous_sign_without_email_is_ephemeral(): void
    {
        $eid = $this->uploadQuick();

        try {
            $this->get(route('quick.sign', $eid))->assertOk();

            // Nivel 0: firma directa, sin OTP y sin email.
            $signed = UploadedFile::fake()->createWithContent('signed.pdf', $this->pdfBytes());
            $this->post(route('quick.finalize', $eid), ['signed' => $signed])
                ->assertOk()
                ->assertJsonPath('ok', true)
                ->assertJsonStructure(['download_url']);

            // No persiste nada en BD.
            $this->assertDatabaseCount('documents', 0);
        } finally {
            File::deleteDirectory(storage_path('app/ephemeral/'.$eid));
        }
    }

    public function test_optional_email_delivery_does_not_break(): void
    {
        Mail::fake();
        $eid = $this->uploadQuick();

        try {
            $signed = UploadedFile::fake()->createWithContent('signed.pdf', $this->pdfBytes());
            $this->post(route('quick.finalize', $eid), [
                'signed' => $signed,
                'email' => 'ana@example.com',
                'signer_name' => 'Ana',
                'reference' => 'FD-ABCD1234',
            ])->assertOk()->assertJsonPath('ok', true);
        } finally {
            File::deleteDirectory(storage_path('app/ephemeral/'.$eid));
        }
    }

    public function test_expired_session_redirects(): void
    {
        $this->get(route('quick.sign', 'deadbeefdeadbeefdeadbeefdeadbeef'))
            ->assertRedirect(route('quick.start'));
    }
}
