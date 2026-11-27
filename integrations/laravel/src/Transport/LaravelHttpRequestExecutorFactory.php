<?php

namespace DreaM1\EfakturaConnector\Laravel\Transport;

use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerInterface;
use DreaM1\EfakturaConnector\Core\Configuration\ConnectorRuntimeConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Configuration\HttpRuntimeConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Configuration\IntegratorAccountConfiguration;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfigurationInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\ScopedHttpRequestExecutionLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\CurlRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\DefaultHttpRequestExecutorFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutionLoggerInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorOptions;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\PooledCurlRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Laravel\Configuration\LaravelServiceConfiguration;
use Illuminate\Contracts\Container\BindingResolutionException;

/** Builds the Laravel HTTP execution stack for the default or a named client profile. */
final class LaravelHttpRequestExecutorFactory
{
    /**
     * Builds the configured executor and its resilience decorators.
     *
     * @param  mixed                                 $app                            Laravel application container instance.
     * @param  SapiClientConfigurationInterface|null $clientConfiguration            Optional client profile used to scope its breaker.
     * @param  IntegratorAccountConfiguration|null   $integratorAccountConfiguration Optional Integrator account used to scope its breaker.
     * @return HttpRequestExecutorInterface
     * @throws BindingResolutionException            when resolving a configured executor or its dependencies fails
     */
    public static function create(
        $app,
        ?SapiClientConfigurationInterface $clientConfiguration = null,
        ?IntegratorAccountConfiguration $integratorAccountConfiguration = null
    ): HttpRequestExecutorInterface {
        $runtimeConfiguration = $app->make(ConnectorRuntimeConfigurationInterface::class);
        $serviceConfiguration = $app->make(LaravelServiceConfiguration::class);
        $logger               = $serviceConfiguration->httpLoggingEnabled()
            && $app->bound(HttpRequestExecutionLoggerInterface::class)
            ? $app->make(HttpRequestExecutionLoggerInterface::class)
            : null;

        $metrics = $serviceConfiguration->httpMetricsEnabled()
            && $app->bound(ConnectorMetricsInterface::class)
            ? $app->make(ConnectorMetricsInterface::class)
            : null;

        // The layer switches come from runtime configuration; here they only decide which services to resolve.
        $backpressureEnabled   = $runtimeConfiguration->backpressureEnabled();
        $circuitBreakerEnabled = $runtimeConfiguration->circuitBreakerEnabled();
        $executionLockEnabled  = $runtimeConfiguration->executionLockEnabled();

        $baseExecutor = self::createBaseExecutor(
            $app,
            $runtimeConfiguration,
            $serviceConfiguration->requestExecutorClass()
        );

        $options = HttpRequestExecutorOptions::defaults()
            ->withLogger($logger)
            ->withBaseExecutor($baseExecutor)
            ->withCircuitBreaker(
                $circuitBreakerEnabled
                    ? self::resolveCircuitBreaker(
                        $app,
                        $serviceConfiguration,
                        $runtimeConfiguration,
                        $clientConfiguration,
                        $integratorAccountConfiguration,
                        $metrics
                    )
                    : null
            )
            ->withBackpressure(
                $backpressureEnabled ? $app->make(HttpRequestBackpressureInterface::class) : null
            )
            ->withMetrics($metrics)
            ->withExecutionLock(
                $executionLockEnabled ? $app->make(ScopedHttpRequestExecutionLockInterface::class) : null
            );

        return DefaultHttpRequestExecutorFactory::createFromRuntimeConfiguration(
            $runtimeConfiguration,
            $options
        );
    }

    /**
     * Builds a request executor with a breaker isolated to the supplied Integrator account.
     *
     * @param mixed $app Laravel application container instance.
     */
    public static function createForIntegratorAccount(
        $app,
        IntegratorAccountConfiguration $configuration
    ): HttpRequestExecutorInterface {
        return self::create($app, null, $configuration);
    }

    /**
     * Resolves a breaker scoped to one profile when the package default is active.
     *
     * @param mixed $app Laravel application container instance.
     */
    private static function resolveCircuitBreaker(
        $app,
        LaravelServiceConfiguration $serviceConfiguration,
        HttpRuntimeConfigurationInterface $runtimeConfiguration,
        ?SapiClientConfigurationInterface $clientConfiguration,
        ?IntegratorAccountConfiguration $integratorAccountConfiguration,
        ?ConnectorMetricsInterface $metrics = null
    ): CircuitBreakerInterface {
        if (
            $integratorAccountConfiguration !== null
            && ! $serviceConfiguration->overrides(CircuitBreakerInterface::class)
        ) {
            return LaravelCacheCircuitBreaker::forIntegratorAccountConfiguration(
                $integratorAccountConfiguration,
                $runtimeConfiguration->circuitBreakerPolicy(),
                $serviceConfiguration->cacheStore(),
                $metrics
            );
        }

        if (
            $clientConfiguration !== null
            && ! $serviceConfiguration->overrides(CircuitBreakerInterface::class)
        ) {
            return LaravelCacheCircuitBreaker::forConnectionConfiguration(
                $clientConfiguration,
                $runtimeConfiguration->circuitBreakerPolicy(),
                $serviceConfiguration->cacheStore(),
                $metrics
            );
        }

        return $app->make(CircuitBreakerInterface::class);
    }

    /**
     * Creates the configured base HTTP executor.
     *
     * @param mixed $app Laravel application container instance.
     */
    private static function createBaseExecutor(
        $app,
        HttpRuntimeConfigurationInterface $configuration,
        string $executorClass
    ): HttpRequestExecutorInterface {
        if (! in_array($executorClass, [CurlRequestExecutor::class, PooledCurlRequestExecutor::class], true)) {
            return $app->make($executorClass);
        }

        return new $executorClass(
            $configuration->httpConnectTimeoutSeconds(),
            $configuration->httpRequestTimeoutSeconds(),
            $configuration->httpResponseBodyLimit()
        );
    }
}
