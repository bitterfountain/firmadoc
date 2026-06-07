<?php

namespace Tests\Unit;

use App\Models\SignatureEvent;
use App\Services\PadesSigningService;
use Tests\TestCase;

class SignatureLogicTest extends TestCase
{
    public function test_reference_format_is_stable(): void
    {
        $event = new SignatureEvent();
        $event->id = 7;
        $event->created_at = '2026-06-06 12:00:00';

        $ref = $event->reference;

        $this->assertStringStartsWith('DS-00007-', $ref);
        $this->assertSame($ref, $event->reference, 'La referencia debe ser determinista');
    }

    public function test_pades_disabled_when_config_off(): void
    {
        config()->set('docsigner.pades.enabled', false);
        config()->set('docsigner.pades.script', __FILE__);

        $this->assertFalse((new PadesSigningService())->isEnabled());
    }

    public function test_pades_pemder_requires_key_and_cert(): void
    {
        config()->set('docsigner.pades.enabled', true);
        config()->set('docsigner.pades.script', __FILE__);
        config()->set('docsigner.pades.backend', 'pemder');
        config()->set('docsigner.pades.key', '/no/existe/key.pem');
        config()->set('docsigner.pades.cert', '/no/existe/cert.pem');

        $this->assertFalse((new PadesSigningService())->isEnabled());
    }
}
