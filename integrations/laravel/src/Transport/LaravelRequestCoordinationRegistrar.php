<?php

namespace DreaM1\EfakturaConnector\Laravel\Transport;

use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerInterface;
use DreaM1\EfakturaConnector\Core\Configuration\ConnectorRuntimeConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientsConfiguration;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\FileHttpRequestExecutionLock;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\ScopedHttpRequestExecutionLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Laravel\Cache\LaravelCacheStore;
use DreaM1\EfakturaConnector\Laravel\Configuration\LaravelExecutionLockLeasePolicy;
use DreaM1\EfakturaConnector\Laravel\Configuration\LaravelServiceConfiguration;

/** @internal Registers the shared request lock and circuit-breaker adapters. */
final class LaravelRequestCoordinationRegistrar
{
    /**
     * @param mixed                       $app           Laravel application container instance.
     * @param LaravelServiceConfiguration $configuration Validated Laravel service configuration.
     */
    public static function register($app, LaravelServiceConfiguration $configuration): void
    {
        if (! $configuration->overrides(ScopedHttpRequestExecutionLockInterface::class)) {
            $app->singleton(ScopedHttpRequestExecutionLockInterface::class, function ($app) {
                $applicationDirectory = $app->basePath();
                $serviceConfiguration = $app->make(LaravelServiceConfiguration::class);

                if ($serviceConfiguration->executionLockDriver() === LaravelServiceConfiguration::EXECUTION_LOCK_DRIVER_FILE) {
                    return new FileHttpRequestExecutionLock(
                        $applicationDirectory,
                        $serviceConfiguration->executionLockTemporaryDirectory()
                    );
                }

                $leaseSeconds = LaravelCacheStore::supportsAtomicLocks($serviceConfiguration->executionLockStore())
                    ? LaravelExecutionLockLeasePolicy::resolveCacheLeaseSeconds(
                        $serviceConfiguration->executionLockLeaseSeconds(),
                        $serviceConfiguration->requestExecutorClass(),
                        $app->make(ConnectorRuntimeConfigurationInterface::class)
                    )
                    : max(1, $serviceConfiguration->executionLockLeaseSeconds() ?? 1);

                return new LaravelCacheHttpRequestExecutionLock(
                    $serviceConfiguration->executionLockStore(),
                    $leaseSeconds,
                    $serviceConfiguration->executionLockWaitSeconds()
                );
            });
        }

        if (! $configuration->overrides(CircuitBreakerInterface::class)) {
            $app->singleton(CircuitBreakerInterface::class, function ($app) {
                return LaravelCacheCircuitBreaker::forConnectionConfiguration(
                    $app->make(SapiClientsConfiguration::class)->defaultClient(),
                    $app->make(ConnectorRuntimeConfigurationInterface::class)->circuitBreakerPolicy(),
                    $app->make(LaravelServiceConfiguration::class)->cacheStore(),
                    $app->bound(ConnectorMetricsInterface::class) ? $app->make(ConnectorMetricsInterface::class) : null
                );
            });
        }
    }
}
