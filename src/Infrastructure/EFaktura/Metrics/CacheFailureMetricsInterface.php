<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics;

/**
 * Optional companion of ConnectorMetricsInterface for shared-cache outages.
 *
 * Cache-backed coordination keeps working during an outage (it fails open), so nothing else shows that it is
 * degraded. A metrics sink that also implements this interface is told once per failed cache operation. It is a
 * separate interface so existing ConnectorMetricsInterface implementations keep working unchanged.
 *
 * @api
 */
interface CacheFailureMetricsInterface
{
    /** Component label for the persistent SAPI token cache. */
    public const COMPONENT_TOKEN_CACHE = 'token_cache';
    /** Component label for the cross-worker token refresh lock. */
    public const COMPONENT_TOKEN_REFRESH_LOCK = 'token_refresh_lock';
    /** Component label for shared circuit breaker state. */
    public const COMPONENT_CIRCUIT_BREAKER = 'circuit_breaker';
    /** Component label for the shared rate-limit cooldown. */
    public const COMPONENT_BACKPRESSURE = 'backpressure';

    /**
     * Records that a shared-cache operation failed and the component continued without it.
     *
     * @param string $component One of the COMPONENT_* labels; never a cache key, token or request value.
     */
    public function recordCacheFailure(string $component): void;
}
