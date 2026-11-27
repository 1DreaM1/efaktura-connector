<?php

namespace DreaM1\EfakturaConnector\Laravel\Authentication;

use DateTime;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\OAuthAccessToken;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiToken;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenCacheInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\CacheFailureMetricsInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\SafeConnectorMetrics;
use DreaM1\EfakturaConnector\Laravel\Cache\LaravelCacheStore;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Stores SAPI tokens encrypted in the selected Laravel cache store, or the default store when none is selected.
 *
 * Cache failures are deliberately treated as cache misses: token acquisition and the active API
 * request remain available when the optional cache backend is unavailable. A decrypt failure or
 * malformed entry is discarded so corrupt or stale data is never returned as a token.
 */
final class LaravelSapiTokenCache implements SapiTokenCacheInterface
{
    /** Cache-envelope field containing the encrypted token payload. */
    private const CACHE_FIELD_TOKEN = 'token';
    /** Cache-envelope field containing the token expiration time. */
    private const CACHE_FIELD_EXPIRES_AT = 'expires_at';

    private ?string $store;
    private ?ConnectorMetricsInterface $metrics;

    /**
     * Creates the encrypted token cache on the selected Laravel cache store.
     *
     * @param string|null $store Cache store name, or null to use Laravel's default store.
     */
    public function __construct(?string $store = null, ?ConnectorMetricsInterface $metrics = null)
    {
        $this->store   = $store;
        $this->metrics = $metrics;
    }

    /**
     * Loads and decrypts a non-expired cached token; returns null when it is unavailable.
     *
     * @param  string         $key Cache key containing the encrypted SAPI token entry.
     * @return SapiToken|null Decrypted token when a valid cached entry exists.
     */
    public function load(string $key): ?SapiToken
    {
        try {
            $cached = LaravelCacheStore::get($this->store, $key);
        } catch (Throwable $exception) {
            // A cache outage reads as a miss, so the token is requested again; make the outage visible.
            SafeConnectorMetrics::cacheFailure($this->metrics, CacheFailureMetricsInterface::COMPONENT_TOKEN_CACHE);

            return null;
        }

        if ($cached === null) {
            return null;
        }

        // Validate the cache envelope before decrypting so incomplete or incompatible entries
        // (including entries written by an older format) are discarded without treating
        // arbitrary stored values as token data.
        if (
            ! is_array($cached)
            || ! is_string($cached[self::CACHE_FIELD_TOKEN] ?? null)
            || $cached[self::CACHE_FIELD_TOKEN] === ''
        ) {
            $this->forgetSafely($key);

            return null;
        }

        // Check the connector's local expiry before decrypting; backend TTL alone is not the
        // authority because entries can be copied between stores or written by other adapters.
        $expiresAt = $cached[self::CACHE_FIELD_EXPIRES_AT] ?? null;

        if (! is_int($expiresAt) || $expiresAt <= time()) {
            $this->forgetSafely($key);

            return null;
        }

        try {
            $accessToken = OAuthAccessToken::fromString($this->decryptToken($cached[self::CACHE_FIELD_TOKEN]));
        } catch (Throwable $exception) {
            $this->forgetSafely($key);

            return null;
        }

        return new SapiToken($accessToken, $expiresAt);
    }

    /**
     * Encrypts and stores a token until its local expiry; an already expired token is not stored.
     *
     * @param  string    $key   Cache key under which the encrypted token envelope is stored.
     * @param  SapiToken $token Token whose value and local expiry are persisted.
     * @return void
     */
    public function store(string $key, SapiToken $token): void
    {
        if ($token->isExpiredAt(time())) {
            $this->forgetSafely($key);

            return;
        }

        try {
            $cached = [
                // Keep OAuth token material encrypted; the expiry is stored beside it so the entry can be
                // validated before decryption.
                self::CACHE_FIELD_TOKEN      => $this->encryptToken($token->accessToken()->reveal()),
                self::CACHE_FIELD_EXPIRES_AT => $token->expiresAt(),
            ];

            // Use the already margin-adjusted absolute expiry as the backend TTL as well.
            LaravelCacheStore::put($this->store, $key, $cached, new DateTime('@' . $token->expiresAt()));
        } catch (Throwable $exception) {
            // A cache outage must not prevent the current API request.
            SafeConnectorMetrics::cacheFailure($this->metrics, CacheFailureMetricsInterface::COMPONENT_TOKEN_CACHE);
        }
    }

    /**
     * Removes the cached token entry associated with the key, when present.
     *
     * @param  string $key Cache key whose entry should be removed.
     * @return void
     */
    public function forget(string $key): void
    {
        $this->forgetSafely($key);
    }

    /** Encrypts a token without serializing it. */
    private function encryptToken(string $token): string
    {
        return Crypt::encryptString($token);
    }

    /** Decrypts a cached token without unserializing it. */
    private function decryptToken(string $token): string
    {
        return Crypt::decryptString($token);
    }

    /**
     * Ignores cache cleanup errors so they cannot replace the provider operation result.
     *
     * @param string $key Token cache key to remove.
     */
    private function forgetSafely(string $key): void
    {
        try {
            LaravelCacheStore::forget($this->store, $key);
        } catch (Throwable $exception) {
            // Cache cleanup must not replace the provider response.
        }
    }
}
