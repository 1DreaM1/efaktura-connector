<?php

namespace DreaM1\EfakturaConnector\Laravel\Configuration;

use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerInterface;
use DreaM1\EfakturaConnector\Core\Configuration\ConnectorRuntimeConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Contracts\CodeListRegistryInterface;
use DreaM1\EfakturaConnector\Core\Contracts\IntegratorAccountClientFactoryInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientFactoryInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientRegistryInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\PeppolBillingDraftRulesInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\PeppolBillingRulesInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblDocumentReaderInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblDocumentWriterInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblXmlDocumentLoaderInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenCacheInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenRefreshLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\ScopedHttpRequestExecutionLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutionLoggerInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\UblDraftSubmission;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\SchematronConcurrencyLimiterInterface;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CodeListRegistry;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader\UblDocumentReader;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader\UblXmlDocumentLoader;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Validation\PeppolBillingDraftRules;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Validation\PeppolBillingHeaderRules;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Writer\UblDocumentWriter;
use DreaM1\EfakturaConnector\Laravel\Authentication\LaravelSapiTokenCache;
use DreaM1\EfakturaConnector\Laravel\Authentication\LaravelSapiTokenRefreshLock;
use DreaM1\EfakturaConnector\Laravel\Logging\LaravelHttpRequestExecutionLogger;
use DreaM1\EfakturaConnector\Laravel\Transport\LaravelCacheHttpRequestBackpressure;

/** @internal Registers default connector bindings and applies host service overrides. */
final class LaravelConnectorBindingsRegistrar
{
    /** Default adapters that are constructed with the configured Laravel cache store. */
    private const CACHE_STORE_ADAPTERS = [
        LaravelSapiTokenCache::class,
        LaravelSapiTokenRefreshLock::class,
        LaravelCacheHttpRequestBackpressure::class,
    ];

    /**
     * @param mixed                       $app           Laravel application container instance.
     * @param LaravelServiceConfiguration $configuration Validated Laravel service configuration.
     */
    public static function register($app, LaravelServiceConfiguration $configuration): void
    {
        $bindings = array_replace(self::defaultBindings(), $configuration->serviceBindings());

        foreach ($bindings as $contract => $implementation) {
            if (in_array($implementation, self::CACHE_STORE_ADAPTERS, true)) {
                $app->singleton($contract, static function ($app) use ($implementation) {
                    $metrics = $app->bound(ConnectorMetricsInterface::class)
                        ? $app->make(ConnectorMetricsInterface::class)
                        : null;

                    return new $implementation(
                        $app->make(LaravelServiceConfiguration::class)->cacheStore(),
                        $metrics
                    );
                });

                continue;
            }

            if (self::isSingletonService($contract)) {
                $app->singleton($contract, $implementation);

                continue;
            }

            $app->bind($contract, $implementation);
        }
    }

    /**
     * Returns the default service contract to implementation class map.
     *
     * @return array<class-string, class-string>
     */
    public static function defaultBindings(): array
    {
        return [
            CodeListRegistryInterface::class           => CodeListRegistry::class,
            SapiTokenCacheInterface::class             => LaravelSapiTokenCache::class,
            SapiTokenRefreshLockInterface::class       => LaravelSapiTokenRefreshLock::class,
            HttpRequestExecutionLoggerInterface::class => LaravelHttpRequestExecutionLogger::class,
            HttpRequestBackpressureInterface::class    => LaravelCacheHttpRequestBackpressure::class,
            UblXmlDocumentLoaderInterface::class       => UblXmlDocumentLoader::class,
            UblDocumentReaderInterface::class          => UblDocumentReader::class,
            UblDocumentWriterInterface::class          => UblDocumentWriter::class,
            UblDraftSubmission::class                  => UblDraftSubmission::class,
            PeppolBillingRulesInterface::class         => PeppolBillingHeaderRules::class,
            PeppolBillingDraftRulesInterface::class    => PeppolBillingDraftRules::class,
        ];
    }

    private static function isSingletonService(string $contract): bool
    {
        return in_array($contract, [
            CodeListRegistryInterface::class,
            SapiClientFactoryInterface::class,
            SapiClientRegistryInterface::class,
            IntegratorAccountClientFactoryInterface::class,
            ConnectorRuntimeConfigurationInterface::class,
            SapiTokenCacheInterface::class,
            SapiTokenRefreshLockInterface::class,
            HttpRequestBackpressureInterface::class,
            ScopedHttpRequestExecutionLockInterface::class,
            CircuitBreakerInterface::class,
            SchematronConcurrencyLimiterInterface::class,
            HttpRequestExecutorInterface::class,
        ], true);
    }
}
