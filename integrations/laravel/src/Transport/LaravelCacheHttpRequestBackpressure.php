<?php

namespace DreaM1\EfakturaConnector\Laravel\Transport;

use DateTime;
use DreaM1\EfakturaConnector\Core\Retry\RetryDeadline;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureKey;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\CacheFailureMetricsInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\SafeConnectorMetrics;
use DreaM1\EfakturaConnector\Laravel\Cache\LaravelCacheStore;
use Throwable;

/** Shares provider Retry-After cooldowns between workers through the configured Laravel cache. */
final class LaravelCacheHttpRequestBackpressure implements HttpRequestBackpressureInterface
{
    /** Prefix for cache keys storing provider-scoped Retry-After deadlines. */
    private const CACHE_KEY_PREFIX = 'efaktura.http_backpressure.';

    private ?string $store;
    private ?ConnectorMetricsInterface $metrics;

    /**
     * Creates the shared cooldown adapter on the selected Laravel cache store.
     *
     * @param string|null                    $store   Cache store name, or null to use Laravel's default store.
     * @param ConnectorMetricsInterface|null $metrics Receives a cache-failure event when the cooldown is unavailable.
     */
    public function __construct(?string $store = null, ?ConnectorMetricsInterface $metrics = null)
    {
        $this->store   = $store;
        $this->metrics = $metrics;
    }

    /**
     * Reads the shared cooldown deadline from the configured cache.
     *
     * Cache failures are treated as an absent cooldown so that cache availability does not block API calls.
     *
     * @param  HttpExchangeRequest $request Request used to derive the provider and credential scope.
     * @return RetryDeadline|null  Future deadline, or null when no active cooldown is available.
     */
    public function blockedUntil(HttpExchangeRequest $request): ?RetryDeadline
    {
        $key = $this->cacheKey($request);

        try {
            $retryAt = LaravelCacheStore::get($this->store, $key);
        } catch (Throwable $exception) {
            SafeConnectorMetrics::cacheFailure($this->metrics, CacheFailureMetricsInterface::COMPONENT_BACKPRESSURE);

            return null;
        }

        if (! is_int($retryAt) || $retryAt <= time()) {
            // Cache TTL removes expired values; deleting here could race with a newer cooldown write.
            return null;
        }

        return RetryDeadline::fromUnixTimestamp($retryAt);
    }

    /**
     * Stores the later of the current and new deadlines so concurrent 429 responses cannot shorten a cooldown.
     *
     * @param HttpExchangeRequest $request Request used to derive the shared cooldown scope.
     * @param RetryDeadline       $retryAt Earliest time at which requests may proceed.
     */
    public function deferUntil(HttpExchangeRequest $request, RetryDeadline $retryAt): void
    {
        $unixTimestamp = $retryAt->unixTimestamp();

        if (! $retryAt->isAfter(time())) {
            return;
        }

        $key = $this->cacheKey($request);

        try {
            // Serialize the read-extend-write sequence so overlapping 429 responses cannot lose
            // the later deadline when one worker writes after another.
            LaravelCacheLock::synchronizedOnStore($key . '.lock', function () use ($key, $unixTimestamp): void {
                $current  = LaravelCacheStore::get($this->store, $key);
                $deadline = is_int($current) ? max($current, $unixTimestamp) : $unixTimestamp;
                // Set storage expiry to the same absolute deadline; blockedUntil rechecks it when read.
                LaravelCacheStore::put($this->store, $key, $deadline, new DateTime('@' . $deadline));
            }, $this->store);
        } catch (Throwable $exception) {
            // Backpressure is best-effort during cache outages; the original 429 still reaches the host.
            SafeConnectorMetrics::cacheFailure($this->metrics, CacheFailureMetricsInterface::COMPONENT_BACKPRESSURE);
        }
    }

    /**
     * Builds the shared cooldown key for the request's provider and credential scope.
     *
     * @param  HttpExchangeRequest $request Request used to derive the scope.
     * @return string              Namespaced cache key for the cooldown deadline.
     */
    private function cacheKey(HttpExchangeRequest $request): string
    {
        return self::CACHE_KEY_PREFIX . HttpRequestBackpressureKey::forRequest($request);
    }
}
