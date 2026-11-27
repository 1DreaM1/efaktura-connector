<?php

namespace DreaM1\EfakturaConnector\Laravel\Authentication;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenRefreshLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\CacheFailureMetricsInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\SafeConnectorMetrics;
use DreaM1\EfakturaConnector\Laravel\Transport\LaravelCacheLock;
use Throwable;

/** Provides best-effort token refresh coordination through Laravel's atomic cache lock API. */
final class LaravelSapiTokenRefreshLock implements SapiTokenRefreshLockInterface
{
    /** Suffix added to the token cache key to form its refresh-lock key. */
    private const CACHE_LOCK_SUFFIX = '.refresh-lock';

    private ?string $store;
    private ?ConnectorMetricsInterface $metrics;

    /**
     * Creates the refresh lock on the selected Laravel cache store.
     *
     * @param string|null                    $store   Cache store name, or null to use Laravel's default store.
     * @param ConnectorMetricsInterface|null $metrics Receives a cache-failure event when the lock is unavailable.
     */
    public function __construct(?string $store = null, ?ConnectorMetricsInterface $metrics = null)
    {
        $this->store   = $store;
        $this->metrics = $metrics;
    }

    /**
     * Runs one token refresh operation under Laravel's atomic cache lock when available.
     *
     * Cache lock errors or contention timeouts are best-effort misses: the callback still runs once,
     * and the token provider rechecks the shared token cache before requesting a token. Concurrent
     * refreshes may still occur when lock acquisition fails. Exceptions from the callback propagate.
     *
     * @param  string            $cacheKey     Cache key identifying the SAPI token.
     * @param  int               $leaseSeconds Maximum lifetime for Laravel's cache lock, in seconds.
     * @param  int               $waitSeconds  Maximum time to wait for another worker's lock, in seconds.
     * @param  callable(): mixed $operation    Token recheck and refresh operation to run.
     * @return mixed             Result returned by the token operation.
     * @throws Throwable         when the token operation fails
     */
    public function synchronized(string $cacheKey, int $leaseSeconds, int $waitSeconds, callable $operation)
    {
        return LaravelCacheLock::synchronizedBestEffort(
            $cacheKey . self::CACHE_LOCK_SUFFIX,
            $operation,
            $leaseSeconds,
            max(0, $waitSeconds),
            $this->store,
            function (): void {
                SafeConnectorMetrics::cacheFailure(
                    $this->metrics,
                    CacheFailureMetricsInterface::COMPONENT_TOKEN_REFRESH_LOCK
                );
            }
        );
    }
}
