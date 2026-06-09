<?php

namespace Tests\Feature;

use App\Services\PadesSigningService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PadesRemoteTest extends TestCase
{
    public function test_enabled_when_remote_node_configured(): void
    {
        config()->set('docsigner.pades.enabled', true);
        config()->set('docsigner.pades.remote_url', 'https://node.test/sign');

        $this->assertTrue(app(PadesSigningService::class)->isEnabled());
    }

    public function test_sign_delegates_to_remote_node(): void
    {
        config()->set('docsigner.pades.enabled', true);
        config()->set('docsigner.pades.remote_url', 'https://node.test/sign');
        config()->set('docsigner.pades.remote_token', 'secret-token');

        Http::fake(['node.test/*' => Http::response('SEALED-PDF-BYTES', 200)]);

        $in = tempnam(sys_get_temp_dir(), 'in');
        $out = tempnam(sys_get_temp_dir(), 'out');
        file_put_contents($in, '%PDF-1.4 fake');

        try {
            app(PadesSigningService::class)->sign($in, $out, ['reason' => 'Test', 'name' => 'Ana']);

            $this->assertSame('SEALED-PDF-BYTES', file_get_contents($out));
            Http::assertSent(fn ($req) => $req->url() === 'https://node.test/sign'
                && $req->hasHeader('Authorization', 'Bearer secret-token')
                && $req->header('X-Sign-Name')[0] === 'Ana');
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }
}
