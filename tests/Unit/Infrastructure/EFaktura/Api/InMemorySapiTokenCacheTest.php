<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\InMemorySapiTokenCache;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\OAuthAccessToken;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiToken;
use PHPUnit\Framework\TestCase;

class InMemorySapiTokenCacheTest extends TestCase
{
    public function testItStoresAndForgetsTokensByOpaqueKey(): void
    {
        $cache = new InMemorySapiTokenCache();
        $token = new SapiToken(OAuthAccessToken::fromString('synthetic-access-token'), time() + 60);

        $cache->store('opaque-cache-key', $token);
        $this->assertSame($token, $cache->load('opaque-cache-key'));

        $cache->forget('another-key');
        $this->assertSame($token, $cache->load('opaque-cache-key'));

        $cache->forget('opaque-cache-key');
        $this->assertNull($cache->load('opaque-cache-key'));
    }
}
