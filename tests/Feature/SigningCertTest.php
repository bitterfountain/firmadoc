<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SigningCertTest extends TestCase
{
    use RefreshDatabase;

    /** Genera un .p12 autofirmado en memoria (o salta si OpenSSL no puede). */
    private function makeP12(string $password): string
    {
        $key = @openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($key === false) {
            $this->markTestSkipped('OpenSSL no disponible en este entorno.');
        }
        $csr = @openssl_csr_new(['commonName' => 'Test Cert', 'organizationName' => 'Test Org'], $key, ['digest_alg' => 'sha256']);
        if ($csr === false) {
            $this->markTestSkipped('OpenSSL no puede generar CSR (config).');
        }
        $crt = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);
        $p12 = '';
        openssl_pkcs12_export($crt, $p12, $key, $password);

        return $p12;
    }

    public function test_cert_page_requires_login(): void
    {
        $this->get(route('cert.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_upload_signing_certificate(): void
    {
        $user = User::factory()->create();
        $p12 = $this->makeP12('secret');

        $file = UploadedFile::fake()->createWithContent('mi-cert.p12', $p12);
        $this->actingAs($user)->post(route('cert.store'), [
            'certificate' => $file,
            'password' => 'secret',
        ])->assertRedirect();

        $user->refresh();
        $this->assertTrue($user->hasSigningCert());
        $this->assertNotEmpty($user->signing_cert_subject);
        $this->assertSame('mi-cert.p12', $user->signing_cert_name);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();
        $p12 = $this->makeP12('secret');

        $file = UploadedFile::fake()->createWithContent('mi-cert.p12', $p12);
        $this->actingAs($user)->post(route('cert.store'), [
            'certificate' => $file,
            'password' => 'WRONG',
        ])->assertSessionHasErrors('certificate');

        $this->assertFalse($user->fresh()->hasSigningCert());
    }

    public function test_user_can_remove_certificate(): void
    {
        $user = User::factory()->create([
            'signing_cert' => base64_encode('dummy'),
            'signing_cert_subject' => 'X',
        ]);

        $this->actingAs($user)->delete(route('cert.destroy'))->assertRedirect();
        $this->assertFalse($user->fresh()->hasSigningCert());
    }
}
