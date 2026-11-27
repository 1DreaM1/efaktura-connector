<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Transport;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureKey;
use PHPUnit\Framework\TestCase;

/** Checks that backpressure state is scoped to the provider origin and credentials. */
final class HttpRequestBackpressureKeyTest extends TestCase
{
    public function testConnectorScopeIncludesOriginAndOAuthIdentity(): void
    {
        $first = HttpRequestBackpressureKey::scopeForConfiguredProvider(
            'https://API.example:443/v1/',
            'client-a',
            'document:send'
        );
        $sameOrigin = HttpRequestBackpressureKey::scopeForConfiguredProvider(
            'https://api.example/v2',
            'client-a',
            'document:send'
        );
        $otherOrigin = HttpRequestBackpressureKey::scopeForConfiguredProvider(
            'https://sandbox.example/v1',
            'client-a',
            'document:send'
        );
        $otherPort = HttpRequestBackpressureKey::scopeForConfiguredProvider(
            'https://api.example:8443/v1',
            'client-a',
            'document:send'
        );
        $otherClient = HttpRequestBackpressureKey::scopeForConfiguredProvider(
            'https://api.example/v1',
            'client-b',
            'document:send'
        );
        $otherScope = HttpRequestBackpressureKey::scopeForConfiguredProvider(
            'https://api.example/v1',
            'client-a',
            'document:receive'
        );

        $this->assertSame($first, $sameOrigin);
        $this->assertNotSame($first, $otherOrigin);
        $this->assertNotSame($first, $otherPort);
        $this->assertNotSame($first, $otherClient);
        $this->assertNotSame($first, $otherScope);
        $this->assertStringNotContainsString('client-a', $first);
        $this->assertStringNotContainsString('document:send', $first);
    }

    public function testIntegratorScopeUsesOriginAndHashedIntegratorIdentity(): void
    {
        $first = HttpRequestBackpressureKey::scopeForConfiguredProvider(
            'https://API.example:443/integrator/v1',
            'integrator-1'
        );
        $sameOrigin = HttpRequestBackpressureKey::scopeForConfiguredProvider(
            'https://api.example/integrator/v2',
            'integrator-1'
        );
        $otherOrigin = HttpRequestBackpressureKey::scopeForConfiguredProvider(
            'https://sandbox.example/integrator/v1',
            'integrator-1'
        );
        $otherIntegrator = HttpRequestBackpressureKey::scopeForConfiguredProvider(
            'https://api.example/integrator/v1',
            'integrator-2'
        );

        $this->assertSame($first, $sameOrigin);
        $this->assertNotSame($first, $otherOrigin);
        $this->assertNotSame($first, $otherIntegrator);
        $this->assertStringNotContainsString('integrator-1', $first);
    }
}
