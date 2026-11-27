<?php

namespace DreaM1\EfakturaConnector\Laravel;

use DreaM1\EfakturaConnector\Core\Configuration\ConnectorRuntimeConfiguration;
use DreaM1\EfakturaConnector\Core\Configuration\ConnectorRuntimeConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Configuration\IntegratorAccountConfiguration;
use DreaM1\EfakturaConnector\Core\Configuration\PdsSigningConfiguration;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientsConfiguration;
use DreaM1\EfakturaConnector\Core\Contracts\IntegratorAccountClientFactoryInterface;
use DreaM1\EfakturaConnector\Core\Contracts\IntegratorAccountClientInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientFactoryInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientRegistryInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblDocumentWriterInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenCacheInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenRefreshLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\IntegratorAccountClientFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\SapiClientFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\SapiClientRegistry;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Laravel\Configuration\Internal\PackageConfigurationMerger;
use DreaM1\EfakturaConnector\Laravel\Configuration\LaravelConnectorBindingsRegistrar;
use DreaM1\EfakturaConnector\Laravel\Configuration\LaravelDefaultClientPortsRegistrar;
use DreaM1\EfakturaConnector\Laravel\Configuration\LaravelPackageConfiguration;
use DreaM1\EfakturaConnector\Laravel\Configuration\LaravelServiceConfiguration;
use DreaM1\EfakturaConnector\Laravel\Connection\TenantEFakturaClientResolver;
use DreaM1\EfakturaConnector\Laravel\Console\CheckConnectorCommand;
use DreaM1\EfakturaConnector\Laravel\Transport\LaravelHttpRequestExecutorFactory;
use DreaM1\EfakturaConnector\Laravel\Transport\LaravelRequestCoordinationRegistrar;
use DreaM1\EfakturaConnector\Laravel\Validation\LaravelValidationServiceRegistrar;
use DreaM1\EfakturaConnector\Support\ConfigurationValues;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use ReflectionException;

/**
 * Registers Laravel bindings for the framework-independent connector services.
 *
 * The provider also assembles the HTTP request executor from runtime settings and optional Laravel
 * integrations, and wires local UBL validation. Host applications can replace supported bindings
 * through the `laravel.services` configuration section or add validation stages through the container tag.
 * This service provider is intentionally extensible.
 *
 * @see LaravelServiceConfiguration
 * @see config/efaktura-connector.php
 */
class EFakturaConnectorServiceProvider extends ServiceProvider
{
    /** Key under which Laravel stores this package's merged configuration. */
    private const PACKAGE_CONFIG_KEY = 'efaktura-connector';

    private ?HttpRequestExecutorInterface $defaultHttpRequestExecutor = null;

    /**
     * Binds connector configuration, transport, API, UBL and validation services in Laravel.
     *
     * @return void
     * @throws BindingResolutionException when resolving a registered service or its dependencies fails
     * @throws InvalidInputException      when configured Laravel service settings are invalid
     * @throws ReflectionException        when a configured class cannot be reflected
     */
    public function register(): void
    {
        $this->registerConfiguration();

        $configuration = $this->app->make(LaravelServiceConfiguration::class);

        LaravelConnectorBindingsRegistrar::register($this->app, $configuration);
        // Coordination stores are registered before the executor factory resolves them.
        LaravelRequestCoordinationRegistrar::register($this->app, $configuration);
        $this->registerHttpRequestExecutor();
        $this->registerSapiClientFactory();
        $this->registerSapiClientRegistry();
        LaravelDefaultClientPortsRegistrar::register($this->app, $configuration);
        $this->registerTenantEFakturaClientResolver();
        $this->registerIntegratorApi();
        LaravelValidationServiceRegistrar::register($this->app, $configuration);
    }

    /**
     * Publishes the package configuration file during Laravel's boot phase.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CheckConnectorCommand::class]);
        }

        $publishedConfiguration = [
            dirname(__DIR__, 3) . '/config/efaktura-connector.php' => config_path('efaktura-connector.php'),
        ];

        // Laravel 5.6 accepts one publish tag per call; later versions also accept an array.
        $this->publishes($publishedConfiguration, 'config');
        $this->publishes($publishedConfiguration, 'efaktura-connector-config');
    }

    /** Registers package defaults and the typed connector configuration. */
    private function registerConfiguration(): void
    {
        $configurationPath = dirname(__DIR__, 3) . '/config/efaktura-connector.php';
        $this->mergeDefaultConfiguration($configurationPath);

        $this->app->singleton(SapiClientsConfiguration::class, function ($app) {
            return SapiClientsConfiguration::fromArray(
                LaravelPackageConfiguration::connectorSettings(self::packageConfiguration($app))
            );
        });
        $this->app->singleton(ConnectorRuntimeConfiguration::class, function ($app) {
            return ConnectorRuntimeConfiguration::fromArray(self::packageConfiguration($app));
        });
        $this->app->alias(ConnectorRuntimeConfiguration::class, ConnectorRuntimeConfigurationInterface::class);
        $this->app->singleton(LaravelServiceConfiguration::class, function ($app) {
            return LaravelServiceConfiguration::fromArray(self::packageConfiguration($app));
        });
    }

    /**
     * Fills values missing from the application configuration with the package defaults, including nested ones.
     *
     * Skipped when the configuration is cached: the cache already holds the merged result.
     */
    private function mergeDefaultConfiguration(string $configurationPath): void
    {
        if (method_exists($this->app, 'configurationIsCached') && $this->app->configurationIsCached()) {
            return;
        }

        $config = $this->app->make('config');
        $config->set(
            self::PACKAGE_CONFIG_KEY,
            PackageConfigurationMerger::merge(
                require $configurationPath,
                (array) $config->get(self::PACKAGE_CONFIG_KEY, [])
            )
        );
    }

    /** Registers the factory that builds SAPI clients unless the host replaces it in `laravel.services`. */
    private function registerSapiClientFactory(): void
    {
        if ($this->hasServiceOverride(SapiClientFactoryInterface::class)) {
            return;
        }

        $this->app->singleton(SapiClientFactoryInterface::class, function ($app) {
            return $this->createSapiClientFactory($app);
        });
    }

    /**
     * Registers the named client registry, sharing transport execution and persistent cache safely.
     *
     * @throws BindingResolutionException when Laravel cannot resolve the registry's configuration or factory.
     * @throws ReflectionException        when a configured service class cannot be reflected.
     */
    private function registerSapiClientRegistry(): void
    {
        if ($this->hasServiceOverride(SapiClientRegistryInterface::class)) {
            return;
        }

        $this->app->singleton(SapiClientRegistryInterface::class, function ($app) {
            return new SapiClientRegistry(
                $app->make(SapiClientsConfiguration::class),
                $app->make(SapiClientFactoryInterface::class)
            );
        });
    }

    /** Registers the resolver for OAuth credentials supplied by tenant models. */
    private function registerTenantEFakturaClientResolver(): void
    {
        $this->app->singleton(TenantEFakturaClientResolver::class, function ($app) {
            // Tenants talk to the same provider environment as the default client profile.
            return new TenantEFakturaClientResolver(
                $app->make(SapiClientFactoryInterface::class),
                $app->make(SapiClientsConfiguration::class)->defaultClient()->configuredBaseUrl()
            );
        });
    }

    /**
     * Registers the separately authenticated Integrator API client and the optional PDS signing settings.
     *
     * The PDS signing secret is read from `integrator_api.pds` and is never part of the Integrator API
     * client configuration; resolving PdsSigningConfiguration does not require Integrator credentials.
     */
    private function registerIntegratorApi(): void
    {
        $this->app->singleton(IntegratorAccountConfiguration::class, function ($app) {
            $integratorSettings = LaravelPackageConfiguration::integratorSettings(self::packageConfiguration($app));
            unset($integratorSettings['pds']);

            return IntegratorAccountConfiguration::fromArray($integratorSettings);
        });

        $this->app->singleton(PdsSigningConfiguration::class, function ($app) {
            return PdsSigningConfiguration::fromArray(ConfigurationValues::section(
                LaravelPackageConfiguration::integratorSettings(self::packageConfiguration($app)),
                'pds',
                'integrator_api.pds'
            ));
        });

        if (! $this->hasServiceOverride(IntegratorAccountClientFactoryInterface::class)) {
            $this->app->singleton(IntegratorAccountClientFactoryInterface::class, function ($app) {
                return new IntegratorAccountClientFactory($this->executorFactory(
                    $app,
                    static function (IntegratorAccountConfiguration $configuration) use ($app) {
                        return LaravelHttpRequestExecutorFactory::createForIntegratorAccount($app, $configuration);
                    }
                ));
            });
        }

        if (! $this->hasServiceOverride(IntegratorAccountClientInterface::class)) {
            $this->app->bind(IntegratorAccountClientInterface::class, function ($app) {
                $configuration = $app->make(IntegratorAccountConfiguration::class);

                return $app->make(IntegratorAccountClientFactoryInterface::class)->create($configuration);
            });
        }
    }

    /**
     * Builds the default factory with per-client execution stacks unless the host supplies an executor.
     *
     * @param  mixed                      $app Laravel application container instance.
     * @throws BindingResolutionException
     */
    private function createSapiClientFactory($app): SapiClientFactoryInterface
    {
        $tokenCache = $app->make(SapiTokenCacheInterface::class);

        $refreshLock = $app->bound(SapiTokenRefreshLockInterface::class)
            ? $app->make(SapiTokenRefreshLockInterface::class)
            : null;

        $metrics = $app->bound(ConnectorMetricsInterface::class)
            ? $app->make(ConnectorMetricsInterface::class)
            : null;

        return new SapiClientFactory(
            $this->executorFactory(
                $app,
                static function (SapiClientConfigurationInterface $clientConfiguration) use ($app) {
                    return LaravelHttpRequestExecutorFactory::create($app, $clientConfiguration);
                }
            ),
            $tokenCache,
            $app->make(UblDocumentWriterInterface::class),
            $refreshLock,
            $metrics,
            $app->make(ConnectorRuntimeConfigurationInterface::class)
        );
    }

    /**
     * Chooses where client factories take their HTTP executor from.
     *
     * A host-supplied executor is shared by every client; otherwise each profile gets its own stack from the
     * package, so circuit breakers and cooldown scopes stay isolated per OAuth identity or Integrator account.
     *
     * @param  mixed                      $app               Laravel application container instance.
     * @param  callable                   $perAccountFactory Builds the package stack for one client profile or account.
     * @return callable
     * @throws BindingResolutionException when the Laravel container cannot resolve the executor
     */
    private function executorFactory($app, callable $perAccountFactory): callable
    {
        if (! $this->usesSharedRequestExecutor($app)) {
            return $perAccountFactory;
        }

        $sharedExecutor = $app->make(HttpRequestExecutorInterface::class);

        return static function () use ($sharedExecutor) {
            return $sharedExecutor;
        };
    }

    /**
     * Reports whether client factories must reuse the container's HTTP executor instead of building
     * a dedicated execution stack per client configuration.
     *
     * The executor is shared when the host overrides it in `laravel.services`, or when the resolved
     * executor is not the package default (for example an instance bound by the host or a test).
     *
     * @param  mixed                      $app Laravel application container instance.
     * @return bool
     * @throws BindingResolutionException when the Laravel container cannot resolve the executor
     */
    private function usesSharedRequestExecutor($app): bool
    {
        if ($this->hasServiceOverride(HttpRequestExecutorInterface::class)) {
            return true;
        }

        if (! $app->resolved(HttpRequestExecutorInterface::class)) {
            return false;
        }

        return $app->make(HttpRequestExecutorInterface::class) !== $this->defaultHttpRequestExecutor;
    }

    /**
     * Reports whether the Laravel config explicitly overrides a binding built by a factory here.
     *
     * An override prevents this provider from registering its default factory for that contract.
     *
     * @param  string                     $contract Service contract class name.
     * @return bool
     * @throws BindingResolutionException when the Laravel container cannot resolve the configuration
     * @throws InvalidInputException      when configured Laravel service settings are invalid
     */
    private function hasServiceOverride(string $contract): bool
    {
        return $this->app->make(LaravelServiceConfiguration::class)->overrides($contract);
    }

    /**
     * Registers the HTTP executor assembled from configured transport options.
     *
     * The executor is shared so its decorators and their coordination dependencies are reused.
     *
     * @throws BindingResolutionException when resolving a registered service or its dependencies fails
     */
    private function registerHttpRequestExecutor(): void
    {
        if (! $this->hasServiceOverride(HttpRequestExecutorInterface::class)) {
            $this->app->singleton(HttpRequestExecutorInterface::class, function ($app) {
                $this->defaultHttpRequestExecutor = LaravelHttpRequestExecutorFactory::create($app);

                return $this->defaultHttpRequestExecutor;
            });
        }
    }

    /**
     * Returns the package configuration from Laravel's configuration repository.
     *
     * @param  mixed                $app Laravel application container instance.
     * @return array<string, mixed>
     */
    private static function packageConfiguration($app): array
    {
        return (array) $app['config']->get(self::PACKAGE_CONFIG_KEY, []);
    }
}
