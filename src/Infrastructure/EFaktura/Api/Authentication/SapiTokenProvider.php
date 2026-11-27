<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication;

use DreaM1\EfakturaConnector\Core\Configuration\OAuthScope;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestDeferredException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;
use DreaM1\EfakturaConnector\Core\Exceptions\TokenRequestException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\OAuthTokenRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\RequestTokenEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi\SapiTokenResponse;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Clock\ClockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Clock\SystemClock;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\SafeConnectorMetrics;
use DreaM1\EfakturaConnector\Support\HashAlgorithm;

/**
 * Manages the SAPI OAuth client-credentials token lifecycle for the configured client and scope.
 *
 * The provider checks its in-memory token and then the injected cache before requesting a new token.
 * The DataHosting API contract targeted by this connector does not expose remote refresh or revoke
 * operations, so an expired or rejected token is replaced by another client-credentials request and
 * forgetting a token only clears local state.
 *
 * The expiry margin makes a token unusable locally before the provider's expiry, reducing races
 * where a request starts with a token that expires in transit. Cache identity includes the provider,
 * OAuth client credentials, and requested scope, not a participant, because participant identity is
 * supplied separately for each participant-scoped operation.
 */
class SapiTokenProvider
{
    /** Subtracted from provider lifetime so cached tokens expire before the provider does. */
    public const EXPIRY_MARGIN_SECONDS = SapiTokenResponse::EXPIRY_MARGIN_SECONDS;

    /** Prefix for cache keys that store tokens managed by this provider. */
    private const CACHE_KEY_PREFIX = 'efaktura.oauth_token.';

    /** Lease duration, in seconds, reserved by one worker refreshing a token. */
    private const REFRESH_LOCK_LEASE_SECONDS = 60;
    /** Maximum time, in seconds, to wait for another worker's token refresh lock. */
    private const REFRESH_LOCK_WAIT_SECONDS = 10;

    private RequestTokenEndpoint $tokenEndpoint;
    private SapiTokenCacheInterface $cache;
    private SapiClientConfigurationInterface $configuration;
    private ?SapiTokenRefreshLockInterface $refreshLock;
    private ?ConnectorMetricsInterface $metrics;
    private ClockInterface $clock;
    private ?SapiToken $currentToken = null;
    private ?string $cacheKey        = null;

    /**
     * @param RequestTokenEndpoint               $tokenEndpoint Endpoint used to obtain a new SAPI token.
     * @param SapiTokenCacheInterface            $cache         Cache store used to persist or coordinate connector state.
     * @param SapiClientConfigurationInterface   $configuration SAPI client credentials, scope and base URL.
     * @param SapiTokenRefreshLockInterface|null $refreshLock   Optional lock to coordinate refreshes across workers
     * @param ConnectorMetricsInterface|null     $metrics       Optional low-cardinality metrics hook
     * @param ClockInterface|null                $clock         Time source for token expiry; defaults to the system clock
     */
    public function __construct(
        RequestTokenEndpoint $tokenEndpoint,
        SapiTokenCacheInterface $cache,
        SapiClientConfigurationInterface $configuration,
        ?SapiTokenRefreshLockInterface $refreshLock = null,
        ?ConnectorMetricsInterface $metrics = null,
        ?ClockInterface $clock = null
    ) {
        $this->tokenEndpoint = $tokenEndpoint;
        $this->cache         = $cache;
        $this->configuration = $configuration;
        $this->refreshLock   = $refreshLock;
        $this->metrics       = $metrics;
        $this->clock         = $clock ?? new SystemClock();
    }

    /**
     * Returns a usable in-memory or cached token, requesting one when neither is usable.
     *
     * Required credentials and scope are resolved before reading the persistent cache so stale cache
     * contents cannot hide an incomplete configuration.
     *
     * @return SapiToken
     * @throws TokenRequestException        when the provider cannot issue a token
     * @throws RequestDeferredException     when local backpressure defers the token request
     * @throws RequestEncodingException     when the token request body cannot be encoded
     * @throws RequestCoordinationException when local HTTP request coordination cannot be established
     * @throws ConfigurationException       when required OAuth settings are missing
     */
    public function getToken(): SapiToken
    {
        $now   = $this->clock->now();
        $scope = $this->configuration->clientScope();

        if ($this->currentToken !== null && ! $this->currentToken->isExpiredAt($now)) {
            SafeConnectorMetrics::tokenCacheEvent($this->metrics, ConnectorMetricsInterface::TOKEN_MEMORY_HIT);

            return $this->currentToken;
        }

        $this->currentToken = null;

        // Resolve credentials before cache access too: an incomplete configuration must not appear
        // to work only because a token from a previous configuration is still present.
        $cacheKey       = $this->cacheKey($scope);
        $this->cacheKey = $cacheKey;

        // The cache may be shared across provider instances, so validate expiry here even if the
        // concrete cache adapter also filters expired entries.
        $cachedToken       = $this->cache->load($cacheKey);
        $usableCachedToken = $this->rememberUsableCachedToken($cachedToken, $now, true);

        if ($usableCachedToken !== null) {
            return $usableCachedToken;
        }

        if ($this->refreshLock === null) {
            // Locking is optional; without it, simultaneous misses may each request a token.
            return $this->replaceToken($cacheKey, $scope, $cachedToken);
        }

        // A lock timeout or cache coordination failure is a best-effort miss, not a reason to block
        // token acquisition; the shared cache is checked again before requesting a token.
        return $this->refreshLock->synchronized(
            $cacheKey,
            self::REFRESH_LOCK_LEASE_SECONDS,
            self::REFRESH_LOCK_WAIT_SECONDS,
            function () use ($cacheKey, $scope) {
                // Another worker may have refreshed the token while this worker waited, or while the
                // optional lock could not be acquired. Re-read before making a duplicate request; the
                // miss was already counted by the first lookup, so only a hit is recorded here.
                $cachedToken       = $this->cache->load($cacheKey);
                $usableCachedToken = $this->rememberUsableCachedToken($cachedToken, $this->clock->now(), false);

                return $usableCachedToken ?? $this->replaceToken($cacheKey, $scope, $cachedToken);
            }
        );
    }

    /**
     * Drops a token the provider rejected, leaving any newer token in place.
     *
     * SAPI has no token-revocation endpoint, so this only invalidates local state. The in-memory token
     * and the shared cache entry are cleared only while they still hold the rejected token: another
     * worker may already have replaced it, and clearing that fresh token would force a needless refresh.
     * The cache comparison is best-effort rather than atomic.
     *
     * @param  SapiToken $rejected Token that the provider answered with an unauthorized response.
     * @return void
     */
    public function forget(SapiToken $rejected): void
    {
        $rejectedValue = $rejected->accessToken()->reveal();

        if ($this->currentToken !== null && hash_equals($this->currentToken->accessToken()->reveal(), $rejectedValue)) {
            $this->currentToken = null;
        }

        if ($this->cacheKey === null) {
            return;
        }

        $cachedToken = $this->cache->load($this->cacheKey);

        if ($cachedToken !== null && hash_equals($cachedToken->accessToken()->reveal(), $rejectedValue)) {
            $this->cache->forget($this->cacheKey);
        }
    }

    /** Discards an unusable cached entry and requests a replacement token. */
    private function replaceToken(string $cacheKey, OAuthScope $scope, ?SapiToken $cachedToken): SapiToken
    {
        if ($cachedToken !== null) {
            $this->cache->forget($cacheKey);
        }

        return $this->requestToken($cacheKey, $scope);
    }
    /** Remembers a cached token when it is still usable; records the lookup outcome, a miss only when asked to. */
    private function rememberUsableCachedToken(?SapiToken $cachedToken, int $now, bool $recordMiss): ?SapiToken
    {
        if ($cachedToken !== null && ! $cachedToken->isExpiredAt($now)) {
            SafeConnectorMetrics::tokenCacheEvent($this->metrics, ConnectorMetricsInterface::TOKEN_CACHE_HIT);
            $this->currentToken = $cachedToken;

            return $cachedToken;
        }

        if ($recordMiss) {
            SafeConnectorMetrics::tokenCacheEvent(
                $this->metrics,
                $cachedToken === null
                    ? ConnectorMetricsInterface::TOKEN_CACHE_MISS
                    : ConnectorMetricsInterface::TOKEN_CACHE_EXPIRED
            );
        }

        return null;
    }

    /** Requests a client-credentials token for the requested SAPI scope and caches it if still usable. */
    private function requestToken(string $cacheKey, OAuthScope $scope): SapiToken
    {
        $request = new OAuthTokenRequest(
            $this->configuration->clientId(),
            $this->configuration->clientSecret(),
            $scope
        );

        // Use a timestamp from before the network call so a slow response cannot push the
        // locally calculated expiry past the overflow check performed while decoding it.
        $now = $this->clock->now();

        try {
            $response = $this->tokenEndpoint->send($request);
        } catch (ProviderApiException $exception) {
            // An unencodable request (RequestEncodingException) is a local failure and propagates as is.
            SafeConnectorMetrics::tokenCacheEvent(
                $this->metrics,
                ConnectorMetricsInterface::TOKEN_REFRESH_FAILURE
            );

            // Preserve the deadline for a request held locally before it reached the token endpoint.
            if ($exception instanceof RequestDeferredException) {
                throw $exception;
            }

            throw new TokenRequestException($exception);
        }

        $this->currentToken = $this->tokenFromResponse($response, $now);

        // A short provider lifetime can be consumed entirely by the local expiry margin; such a
        // token may be returned for this call, but must not be persisted for later calls.
        if ($this->currentToken->expiresAt() > $now) {
            $this->cache->store($cacheKey, $this->currentToken);
        }

        SafeConnectorMetrics::tokenCacheEvent($this->metrics, ConnectorMetricsInterface::TOKEN_REFRESH_SUCCESS);

        return $this->currentToken;
    }

    /** Applies the local expiry margin before creating a cacheable token. */
    private function tokenFromResponse(SapiTokenResponse $response, int $now): SapiToken
    {
        // Expire locally before SAPI's deadline to leave time for requests already in flight.
        $expiresAt = $now + max(0, $response->expiresIn->seconds() - self::EXPIRY_MARGIN_SECONDS);

        return new SapiToken($response->accessToken, $expiresAt);
    }

    /**
     * A token belongs to the OAuth client at one provider and to the requested scope — not to the participant.
     *
     * @throws ConfigurationException when the provider URL or OAuth client credentials are unavailable.
     */
    private function cacheKey(OAuthScope $scope): string
    {
        // Hash the identity tuple so the cache key separates clients/scopes without exposing the
        // client secret or placing credentials directly in a cache backend's key namespace.
        return self::CACHE_KEY_PREFIX . hash(HashAlgorithm::SHA_256, implode("\0", [
            $this->configuration->baseUrl()->value(),
            $this->configuration->clientId()->value(),
            $this->configuration->clientSecret()->reveal(),
            $scope->value(),
        ]));
    }
}
