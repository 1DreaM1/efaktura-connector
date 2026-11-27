<?php

namespace DreaM1\EfakturaConnector\Tests\Laravel;

use DateTime;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\OAuthAccessToken;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiToken;
use DreaM1\EfakturaConnector\Laravel\Authentication\LaravelSapiTokenCache;
use DreaM1\EfakturaConnector\Tests\Support\CoordinationCacheFake;
use Illuminate\Container\Container;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

/** Tests encrypted SAPI token caching through the supported Laravel APIs. */
class LaravelSapiTokenCacheTest extends TestCase
{
    private CoordinationCacheFake $cache;

    private Encrypter $encrypter;

    /** Installs the supported Laravel cache and encryption implementations behind their facades. */
    protected function setUp(): void
    {
        parent::setUp();

        $app             = new Container();
        $this->cache     = new CoordinationCacheFake();
        $this->encrypter = new Encrypter(str_repeat('a', 32), 'AES-256-CBC');

        Facade::setFacadeApplication($app);
        $app->instance('cache', $this->cache);
        $app->instance('encrypter', $this->encrypter);
    }

    /** Removes facade state so the test cannot leak it to other Laravel adapter tests. */
    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);

        parent::tearDown();
    }

    public function testStoresAndLoadsTokensWithRawStringEncryption(): void
    {
        $expiresAt = time() + 300;
        $token     = new SapiToken(OAuthAccessToken::fromString('synthetic-access-token'), $expiresAt);
        $adapter   = new LaravelSapiTokenCache();
        $cacheKey  = 'efaktura.test.token';

        $adapter->store($cacheKey, $token);

        $cachedEntry = $this->cache->get($cacheKey);
        $this->assertIsArray($cachedEntry);
        $this->assertNotSame($token->accessToken()->reveal(), $cachedEntry['token']);
        $this->assertIsInt($cachedEntry['expires_at']);
        $this->assertSame(['token', 'expires_at'], array_keys($cachedEntry));

        $restoredToken = $adapter->load($cacheKey);

        $this->assertInstanceOf(SapiToken::class, $restoredToken);
        $this->assertSame($token->accessToken()->reveal(), $restoredToken->accessToken()->reveal());
        $this->assertSame($token->expiresAt(), $restoredToken->expiresAt());
    }

    public function testUsesTheConfiguredCacheStoreForTokenEntries(): void
    {
        $expiresAt = time() + 300;
        $token     = new SapiToken(OAuthAccessToken::fromString('synthetic-access-token'), $expiresAt);
        $adapter   = new LaravelSapiTokenCache('connector-state');
        $cacheKey  = 'efaktura.test.named-token';

        $adapter->store($cacheKey, $token);

        $this->assertSame('connector-state', $this->cache->lastStoreName());
        $this->assertInstanceOf(SapiToken::class, $adapter->load($cacheKey));
        $this->assertSame('connector-state', $this->cache->lastStoreName());

        $adapter->forget($cacheKey);
        $this->assertSame('connector-state', $this->cache->lastStoreName());
    }

    public function testDiscardsCacheEntriesWithStringExpiryFields(): void
    {
        $adapter   = new LaravelSapiTokenCache();
        $expiresAt = time() + 300;
        $token     = new SapiToken(OAuthAccessToken::fromString('synthetic-access-token'), $expiresAt);

        $cacheKey = 'efaktura.test.expires_at';
        $adapter->store($cacheKey, $token);
        $cachedEntry               = $this->cache->get($cacheKey);
        $cachedEntry['expires_at'] = (string) $cachedEntry['expires_at'];
        $this->cache->put($cacheKey, $cachedEntry, new DateTime('@' . $expiresAt));

        $this->assertNull($adapter->load($cacheKey));
        $this->assertNull($this->cache->get($cacheKey));
    }
}
