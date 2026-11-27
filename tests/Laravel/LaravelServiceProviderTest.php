<?php

namespace DreaM1\EfakturaConnector\Tests\Laravel;

use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerInterface;
use DreaM1\EfakturaConnector\Core\CircuitBreaker\InMemoryCircuitBreaker;
use DreaM1\EfakturaConnector\Core\CodeLists\CodeListIdentifiers;
use DreaM1\EfakturaConnector\Core\Configuration\ConnectorRuntimeConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Configuration\IntegratorAccountConfiguration;
use DreaM1\EfakturaConnector\Core\Configuration\PdsSigningConfiguration;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientsConfiguration;
use DreaM1\EfakturaConnector\Core\Contracts\CodeListRegistryInterface;
use DreaM1\EfakturaConnector\Core\Contracts\IntegratorAccountClientInterface;
use DreaM1\EfakturaConnector\Core\Contracts\LocalUblValidationInterface;
use DreaM1\EfakturaConnector\Core\Contracts\PeppolSchematronValidationInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientFactoryInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientRegistryInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SchematronRunnerInterface;
use DreaM1\EfakturaConnector\Core\Contracts\UblDraftSubmissionInterface;
use DreaM1\EfakturaConnector\Core\Contracts\UblSchemaValidationInterface;
use DreaM1\EfakturaConnector\Core\Contracts\WebhookRegistryInterface;
use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\PeppolBillingDraftRulesInterface;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\IntegratorTokenRequestSender;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\PdsAuthenticatedRequestSender;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenCacheInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenProvider;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenRefreshLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\FileHttpRequestExecutionLock;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\InMemoryHttpRequestBackpressure;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\ScopedHttpRequestExecutionLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\CircuitBreakerHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\CurlRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutionLoggerInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\LoggingHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\MetricsHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\PooledCurlRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\RejectionObservingHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpTransportInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\ProviderTransport;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\IntegratorAccountClient;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\UblDraftSubmission;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\PeppolBillingHeaderValidationStage;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schema\UblSchemaValidator;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\SaxonSchematronRunner;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CodeListRegistry;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Validation\PeppolBillingDraftRules;
use DreaM1\EfakturaConnector\Laravel\Configuration\LaravelConnectorBindingsRegistrar;
use DreaM1\EfakturaConnector\Laravel\Configuration\LaravelDefaultClientPortsRegistrar;
use DreaM1\EfakturaConnector\Laravel\Connection\TenantEFakturaClientResolver;
use DreaM1\EfakturaConnector\Laravel\EFakturaConnectorServiceProvider;
use DreaM1\EfakturaConnector\Laravel\Logging\LaravelHttpRequestExecutionLogger;
use DreaM1\EfakturaConnector\Laravel\Transport\LaravelCacheCircuitBreaker;
use DreaM1\EfakturaConnector\Tests\Support\ConfiguredSapiClientFactory;
use DreaM1\EfakturaConnector\Tests\Support\CoordinationCacheFake;
use DreaM1\EfakturaConnector\Tests\Support\HttpExchangeRequestFactory;
use DreaM1\EfakturaConnector\Tests\Support\RecordingConnectorMetrics;
use DreaM1\EfakturaConnector\Tests\Support\ReflectionAccess;
use DreaM1\EfakturaConnector\Tests\Support\UnboundedLaravelTestHttpRequestExecutor;
use DreaM1\EfakturaConnector\Tests\Support\Validation\ConfiguredValidationStage;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionObject;

/** Checks Laravel bindings, configured overrides, named clients and validation-service registration. */
class LaravelServiceProviderTest extends TestCase
{
    /** Installs a cache fake for adapters resolved by bare Laravel test containers. */
    protected function setUp(): void
    {
        parent::setUp();

        Facade::setFacadeApplication(new Application(sys_get_temp_dir()));
        Cache::swap(new CoordinationCacheFake());
    }

    /** Clears facade state after the provider test. */
    protected function tearDown(): void
    {
        Cache::clearResolvedInstance('cache');
        Facade::setFacadeApplication(null);

        parent::tearDown();
    }

    public function testLaravelFillsOmittedNestedSettingsFromPackageDefaults(): void
    {
        $container = $this->createContainerWithProvider([
            'runtime' => ['http' => ['request_timeout_seconds' => 20]],
            'laravel' => ['validation' => ['java_binary' => 'java-custom']],
        ]);

        $configuration = $container->make('config')->get('efaktura-connector');

        $this->assertSame(20, $configuration['runtime']['http']['request_timeout_seconds']);
        $this->assertArrayHasKey('connect_timeout_seconds', $configuration['runtime']['http']);
        $this->assertArrayHasKey('retry', $configuration['runtime']);
        $this->assertSame('java-custom', $configuration['laravel']['validation']['java_binary']);
        $this->assertNotEmpty($configuration['laravel']['validation']['stages']);
        $this->assertArrayHasKey('request_execution', $configuration['laravel']);
    }

    public function testLaravelPublishesConnectorConfigurationWithBothTags(): void
    {
        $provider = new EFakturaConnectorServiceProvider(new Application(sys_get_temp_dir()));
        $provider->boot();
        $configurationPath = dirname(__DIR__, 2) . '/config/efaktura-connector.php';
        $publishedPath     = config_path('efaktura-connector.php');

        foreach (['config', 'efaktura-connector-config'] as $tag) {
            $paths = ServiceProvider::pathsToPublish(EFakturaConnectorServiceProvider::class, $tag);

            $this->assertSame($publishedPath, $paths[$configurationPath] ?? null);
        }
    }

    public function testLaravelRegistersTheTenantSapiClientResolver(): void
    {
        $container = $this->createContainerWithProvider([
            'clients' => [
                'default' => ['base_url' => 'https://provider.synthetic.test'],
            ],
        ]);

        $this->assertInstanceOf(
            TenantEFakturaClientResolver::class,
            $container->make(TenantEFakturaClientResolver::class)
        );
    }

    public function testLaravelResolvesIntegratorAccountClientFromDedicatedConfiguration(): void
    {
        $container = $this->createContainerWithProvider([
            'clients' => [
                'default' => ['base_url' => 'https://provider.shared.synthetic.test'],
            ],
            'integrator_api' => [
                'base_url'      => 'https://integrator.synthetic.test/',
                'integrator_id' => 'synthetic-integrator',
                'token'         => 'synthetic-integrator-token',
            ],
        ]);
        $executor = $this->createStub(HttpRequestExecutorInterface::class);
        $container->instance(HttpRequestExecutorInterface::class, $executor);

        $configuration = $container->make(IntegratorAccountConfiguration::class);
        $client        = $container->make(IntegratorAccountClientInterface::class);

        $this->assertSame('https://integrator.synthetic.test', $configuration->baseUrl()->value());
        $this->assertSame('synthetic-integrator', $configuration->integratorId()->value());
        $this->assertSame('synthetic-integrator-token', $configuration->token()->reveal());
        $this->assertInstanceOf(IntegratorAccountClient::class, $client);
        $facades = $client;

        $clientManagementFacade = $this->privateProperty($facades, 'clients');
        $clientListEndpoint     = $this->privateProperty($clientManagementFacade, 'clientList');
        $tokenApi               = $this->privateProperty($clientListEndpoint, 'api');
        $tokenSender            = $this->privateProperty($tokenApi, 'transport');
        $this->assertInstanceOf(IntegratorTokenRequestSender::class, $tokenSender);

        $transport = $this->privateProperty($tokenSender, 'transport');
        $this->assertInstanceOf(ProviderTransport::class, $transport);
        $this->assertSame($executor, $this->privateProperty($transport, 'requestExecutor'));

        $pdsFacade = $this->privateProperty($facades, 'pds');
        $pdsId     = $this->privateProperty($pdsFacade, 'integratorId');
        $this->assertSame($configuration->integratorId()->value(), $pdsId->value());

        $pdsEndpoint = $this->privateProperty($pdsFacade, 'subjects');
        $pdsApi      = $this->privateProperty($pdsEndpoint, 'api');
        $pdsSender   = $this->privateProperty($pdsApi, 'transport');
        $this->assertInstanceOf(PdsAuthenticatedRequestSender::class, $pdsSender);
        $this->assertSame($transport, $this->privateProperty($pdsSender, 'transport'));
    }

    public function testIntegratorAccountUsesACircuitBreakerScopedToItsOwnConfiguration(): void
    {
        $container = $this->createContainerWithProvider([
            'clients' => [
                'default' => [
                    'base_url'      => 'https://provider.shared.synthetic.test',
                    'client_id'     => 'default-sapi-client',
                    'client_secret' => 'default-sapi-secret',
                ],
            ],
            'integrator_api' => [
                'base_url'      => 'https://integrator.synthetic.test',
                'integrator_id' => 'synthetic-integrator',
                'token'         => 'synthetic-integrator-token',
            ],
        ]);

        $client             = $container->make(IntegratorAccountClientInterface::class);
        $facades            = $client;
        $tokenApi           = $this->privateProperty($this->privateProperty($facades, 'clients'), 'clientList');
        $sender             = $this->privateProperty($this->privateProperty($tokenApi, 'api'), 'transport');
        $transport          = $this->privateProperty($sender, 'transport');
        $integratorExecutor = $this->privateProperty($transport, 'requestExecutor');
        $integratorBreaker  = $this->findCircuitBreaker($integratorExecutor);

        $defaultExecutor           = $container->make(HttpRequestExecutorInterface::class);
        $defaultBreaker            = $this->findCircuitBreaker($defaultExecutor);
        $expectedIntegratorBreaker = LaravelCacheCircuitBreaker::forIntegratorAccountConfiguration(
            $container->make(IntegratorAccountConfiguration::class)
        );

        $this->assertInstanceOf(CircuitBreakerInterface::class, $integratorBreaker);
        $this->assertInstanceOf(CircuitBreakerInterface::class, $defaultBreaker);
        $this->assertSame(
            $this->privateProperty($expectedIntegratorBreaker, 'cacheKey'),
            $this->privateProperty($integratorBreaker, 'cacheKey')
        );
        $this->assertNotSame(
            $this->privateProperty($integratorBreaker, 'cacheKey'),
            $this->privateProperty($defaultBreaker, 'cacheKey')
        );
    }

    public function testLaravelResolvesThePdsSigningSecretSeparatelyFromIntegratorCredentials(): void
    {
        $container = $this->createContainerWithProvider([
            'clients' => [
                'default' => ['base_url' => 'https://provider.shared.synthetic.test'],
            ],
            'integrator_api' => [
                'base_url'      => 'https://integrator.synthetic.test',
                'integrator_id' => 'synthetic-integrator',
                'token'         => 'synthetic-integrator-token',
                'pds'           => ['signing_secret' => 'synthetic-pds-secret'],
            ],
        ]);

        $pdsConfiguration = $container->make(PdsSigningConfiguration::class);

        $this->assertSame($pdsConfiguration, $container->make(PdsSigningConfiguration::class));
        $this->assertSame('synthetic-pds-secret', $pdsConfiguration->requireSigningSecret()->reveal());
        $this->assertSame(
            'synthetic-integrator-token',
            $container->make(IntegratorAccountConfiguration::class)->token()->reveal()
        );
    }

    public function testPdsSigningConfigurationDoesNotRequireIntegratorCredentials(): void
    {
        $container = $this->createContainerWithProvider([
            'integrator_api' => [
                'pds' => ['signing_secret' => ''],
            ],
        ]);

        $this->assertFalse($container->make(PdsSigningConfiguration::class)->hasSigningSecret());
    }

    public function testPdsSigningConfigurationRejectsUnknownSettings(): void
    {
        $container = $this->createContainerWithProvider([
            'integrator_api' => [
                'pds' => ['secret' => 'typo'],
            ],
        ]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Unknown integrator_api.pds configuration key "secret"');

        $container->make(PdsSigningConfiguration::class);
    }

    public function testLaravelRegistersTheBundledCodeListRegistry(): void
    {
        $container = $this->createContainerWithProvider();
        $registry  = $container->make(CodeListRegistryInterface::class);

        $this->assertInstanceOf(CodeListRegistry::class, $registry);
        $this->assertContains(CodeListIdentifiers::UNITS, $registry->identifiers());

        $unitOptions = $registry->get(CodeListIdentifiers::UNITS)->options();
        $this->assertNotEmpty($unitOptions);
        $unitValues = [];

        foreach ($unitOptions as $option) {
            $unitValues[] = $option->value();
        }

        $this->assertContains('H87', $unitValues);
    }

    public function testNamedClientsHaveIsolatedConfigurationsAndDefaultBindingsUseSelectedClient(): void
    {
        $container = $this->createContainerWithProvider([
            'default_client' => 'tenant-b',
            'clients'        => [
                'tenant-a' => [
                    'base_url'      => 'https://provider.synthetic.test',
                    'client_id'     => 'client-a',
                    'client_secret' => 'secret-a',
                    'client_scope'  => 'document:send',
                ],
                'tenant-b' => [
                    'base_url'      => 'https://provider.synthetic.test',
                    'client_id'     => 'client-b',
                    'client_secret' => 'secret-b',
                ],
            ],
        ]);
        $container->instance(SapiTokenCacheInterface::class, $this->createStub(SapiTokenCacheInterface::class));
        $container->instance(
            SapiTokenRefreshLockInterface::class,
            $this->createStub(SapiTokenRefreshLockInterface::class)
        );
        $sharedExecutor = $this->createStub(HttpRequestExecutorInterface::class);
        $container->instance(HttpRequestExecutorInterface::class, $sharedExecutor);

        $registry = $container->make(SapiClientRegistryInterface::class);
        $tenantA  = $registry->client('tenant-a');
        $tenantB  = $registry->client('tenant-b');

        $this->assertSame(['tenant-a', 'tenant-b'], $registry->clientNames());
        $this->assertSame('tenant-b', $registry->defaultClientName());
        $this->assertSame($tenantA, $registry->client('tenant-a'));
        $this->assertNotSame($tenantA, $tenantB);
        $this->assertSame('client-a', $tenantA->profile()->clientId());
        $this->assertSame('client-b', $tenantB->profile()->clientId());
        $this->assertSame('https://provider.synthetic.test', $tenantA->profile()->baseUrl());
        $this->assertSame('https://provider.synthetic.test', $tenantB->profile()->baseUrl());
        $this->assertSame('client-b', $container->make(SapiClientsConfiguration::class)->defaultClient()->clientId()->value());

        $facadesA = $tenantA;
        $facadesB = $tenantB;
        $apiA     = $this->privateProperty($facadesA, 'api');
        $apiB     = $this->privateProperty($facadesB, 'api');
        $this->assertNotSame($apiA, $apiB);
        $this->assertNotSame(
            $this->privateProperty($this->privateProperty($apiA, 'transport'), 'tokenProvider'),
            $this->privateProperty($this->privateProperty($apiB, 'transport'), 'tokenProvider')
        );
        $this->assertSame($sharedExecutor, $this->clientExecutor($tenantA));
        $this->assertSame($sharedExecutor, $this->clientExecutor($tenantB));
    }

    public function testNamedClientsUseCircuitBreakersScopedToTheirConfiguration(): void
    {
        $container = $this->createContainerWithProvider([
            'default_client' => 'tenant-a',
            'clients'        => [
                'tenant-a' => [
                    'base_url'      => 'https://provider.synthetic.test',
                    'client_id'     => 'client-a',
                    'client_secret' => 'secret-a',
                ],
                'tenant-b' => [
                    'base_url'      => 'https://provider.synthetic.test',
                    'client_id'     => 'client-b',
                    'client_secret' => 'secret-b',
                ],
            ],
        ]);

        $registry = $container->make(SapiClientRegistryInterface::class);
        $clientA  = $registry->client('tenant-a');
        $clientB  = $registry->client('tenant-b');

        $executorA = $this->clientExecutor($clientA);
        $executorB = $this->clientExecutor($clientB);
        $breakerA  = $this->findCircuitBreaker($executorA);
        $breakerB  = $this->findCircuitBreaker($executorB);

        $this->assertInstanceOf(CircuitBreakerInterface::class, $breakerA);
        $this->assertInstanceOf(CircuitBreakerInterface::class, $breakerB);
        $this->assertNotSame($breakerA, $breakerB);
        $this->assertNotSame(
            $this->privateProperty($breakerA, 'cacheKey'),
            $this->privateProperty($breakerB, 'cacheKey')
        );
    }

    public function testResolvingDefaultExecutorBeforeRegistryDoesNotShareClientCircuitBreakers(): void
    {
        $container = $this->createContainerWithProvider([
            'default_client' => 'tenant-a',
            'clients'        => [
                'tenant-a' => [
                    'base_url'      => 'https://provider.synthetic.test',
                    'client_id'     => 'client-a',
                    'client_secret' => 'secret-a',
                ],
                'tenant-b' => [
                    'base_url'      => 'https://provider.synthetic.test',
                    'client_id'     => 'client-b',
                    'client_secret' => 'secret-b',
                ],
            ],
        ]);

        $defaultExecutor = $container->make(HttpRequestExecutorInterface::class);
        $registry        = $container->make(SapiClientRegistryInterface::class);
        $clientA         = $registry->client('tenant-a');
        $clientB         = $registry->client('tenant-b');
        $breakerA        = $this->findCircuitBreaker($this->clientExecutor($clientA));
        $breakerB        = $this->findCircuitBreaker($this->clientExecutor($clientB));

        $this->assertInstanceOf(HttpRequestExecutorInterface::class, $defaultExecutor);
        $this->assertInstanceOf(CircuitBreakerInterface::class, $breakerA);
        $this->assertInstanceOf(CircuitBreakerInterface::class, $breakerB);
        $this->assertNotSame($breakerA, $breakerB);
        $this->assertNotSame(
            $this->privateProperty($breakerA, 'cacheKey'),
            $this->privateProperty($breakerB, 'cacheKey')
        );
    }

    public function testDefaultPortsAreTakenFromTheDefaultSapiClient(): void
    {
        $container = $this->createContainerWithProvider([
            'default_client' => 'second',
            'clients'        => [
                'default' => ['client_id' => 'first-client', 'client_secret' => 'first-secret'],
                'second'  => ['client_id' => 'second-client', 'client_secret' => 'second-secret'],
            ],
        ]);
        $container->instance(SapiTokenCacheInterface::class, $this->createStub(SapiTokenCacheInterface::class));
        $container->instance(
            SapiTokenRefreshLockInterface::class,
            $this->createStub(SapiTokenRefreshLockInterface::class)
        );

        $defaultClient = $container->make(SapiClientRegistryInterface::class)->defaultClient();

        foreach (LaravelDefaultClientPortsRegistrar::ports() as $contract => $clientMethod) {
            $this->assertSame($defaultClient->{$clientMethod}(), $container->make($contract), $contract);
        }

        $this->assertFalse($container->bound(HttpTransportInterface::class));
        $this->assertFalse($container->bound(ApiConnector::class));
        $this->assertFalse($container->bound(SapiTokenProvider::class));
    }

    public function testConfiguredServiceOverrideReplacesADefaultClientPort(): void
    {
        $replacement = get_class($this->createStub(WebhookRegistryInterface::class));
        $container   = $this->createContainerWithProvider([
            'laravel' => [
                'services' => [
                    WebhookRegistryInterface::class => $replacement,
                ],
            ],
        ]);

        $this->assertInstanceOf($replacement, $container->make(WebhookRegistryInterface::class));
    }

    public function testEveryDefaultBindingResolvesFromTheContainer(): void
    {
        $container = $this->createContainerWithProvider([
            'clients' => [
                'default' => [
                    'base_url'      => 'https://sapi.synthetic.test',
                    'client_id'     => 'synthetic-client',
                    'client_secret' => 'synthetic-secret',
                ],
            ],
        ]);
        $container->instance(SapiTokenCacheInterface::class, $this->createStub(SapiTokenCacheInterface::class));
        $container->instance(
            SapiTokenRefreshLockInterface::class,
            $this->createStub(SapiTokenRefreshLockInterface::class)
        );

        foreach (LaravelConnectorBindingsRegistrar::defaultBindings() as $contract => $implementation) {
            $this->assertTrue(
                class_exists($implementation),
                sprintf('Default implementation %s for %s does not exist.', $implementation, $contract)
            );
            $this->assertInstanceOf($contract, $container->make($contract), $contract);
        }
    }

    public function testUblDraftSubmissionCanBeResolvedFromLaravelContainer(): void
    {
        $container = $this->createContainerWithProvider();

        $this->assertTrue($container->bound(UblDraftSubmission::class));
        $this->assertInstanceOf(
            UblDraftSubmissionInterface::class,
            $container->make(UblDraftSubmissionInterface::class)
        );
        $this->assertInstanceOf(
            UblDraftSubmission::class,
            $container->make(UblDraftSubmission::class)
        );
    }

    public function testPeppolBillingDraftRulesCanBeResolvedFromLaravelContainer(): void
    {
        $container = $this->createContainerWithProvider();

        $this->assertInstanceOf(
            PeppolBillingDraftRules::class,
            $container->make(PeppolBillingDraftRulesInterface::class)
        );
    }

    public function testConfiguredValidationStagesAreResolvedByTheContainer(): void
    {
        $container = $this->createContainerWithProvider([
            'laravel' => [
                'validation' => [
                    'stages' => [ConfiguredValidationStage::class],
                ],
            ],
        ]);

        $validator = $container->make(LocalUblValidationInterface::class);
        $xml       = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" />';
        $result    = $validator->validateDocument(UblXmlDocument::fromXml($xml));

        $this->assertSame([ConfiguredValidationStage::ID], array_keys($result->stepResults()));
    }

    public function testDefaultValidationStagesIncludePeppolBillingHeaderRules(): void
    {
        $container = $this->createContainerWithProvider();
        $validator = $container->make(LocalUblValidationInterface::class);
        $pipeline  = $this->privateProperty($validator, 'pipeline');
        $stages    = $this->privateProperty($pipeline, 'stages');

        $this->assertSame(
            [
                'schema',
                PeppolBillingHeaderValidationStage::ID,
                'peppol_schematron',
            ],
            array_keys($stages)
        );
        $this->assertInstanceOf(
            PeppolBillingHeaderValidationStage::class,
            $stages[PeppolBillingHeaderValidationStage::ID]
        );
    }

    public function testRegisteredMetricsAreInjectedIntoHttpAndTokenServices(): void
    {
        $container = $this->createContainerWithProvider();
        $metrics   = new RecordingConnectorMetrics();
        $container->instance(ConnectorMetricsInterface::class, $metrics);
        $container->instance(CircuitBreakerInterface::class, new InMemoryCircuitBreaker());
        $container->instance(
            HttpRequestBackpressureInterface::class,
            $this->createStub(HttpRequestBackpressureInterface::class)
        );
        $container->instance(SapiTokenCacheInterface::class, $this->createStub(SapiTokenCacheInterface::class));
        $container->instance(
            SapiTokenRefreshLockInterface::class,
            $this->createStub(SapiTokenRefreshLockInterface::class)
        );

        $executor = $container->make(HttpRequestExecutorInterface::class);
        $this->assertInstanceOf(RejectionObservingHttpRequestExecutor::class, $executor);
        $httpMetricsExecutor = $this->findMetricsExecutor($executor);
        $this->assertInstanceOf(MetricsHttpRequestExecutor::class, $httpMetricsExecutor);
        $metricsProperty = (new ReflectionClass($httpMetricsExecutor))->getProperty('metrics');
        ReflectionAccess::makeAccessible($metricsProperty);
        $this->assertSame($metrics, $metricsProperty->getValue($httpMetricsExecutor));

        $client        = $container->make(SapiClientRegistryInterface::class)->defaultClient();
        $api           = $this->privateProperty($client, 'api');
        $sender        = $this->privateProperty($api, 'transport');
        $tokenProvider = $this->privateProperty($sender, 'tokenProvider');
        $this->assertInstanceOf(SapiTokenProvider::class, $tokenProvider);
        $metricsProperty = (new ReflectionClass($tokenProvider))->getProperty('metrics');
        ReflectionAccess::makeAccessible($metricsProperty);
        $this->assertSame($metrics, $metricsProperty->getValue($tokenProvider));
    }

    public function testLaravelRegistersTheDefaultHttpExecutionLogger(): void
    {
        $container = $this->createContainerWithProvider();

        $this->assertInstanceOf(
            LaravelHttpRequestExecutionLogger::class,
            $container->make(HttpRequestExecutionLoggerInterface::class)
        );
    }

    public function testRegisteredMetricsBackendReceivesHttpRequestMetrics(): void
    {
        $container = $this->createContainerWithProvider([
            'runtime' => [
                'retry'           => ['enabled' => false],
                'circuit_breaker' => ['enabled' => false],
                'backpressure'    => ['enabled' => false, 'execution_lock' => false],
            ],
            'laravel' => [
                'request_execution' => [
                    'executor' => UnboundedLaravelTestHttpRequestExecutor::class,
                    'logging'  => false,
                ],
            ],
        ]);
        $metrics = new RecordingConnectorMetrics();
        $container->instance(ConnectorMetricsInterface::class, $metrics);
        $executor = $container->make(HttpRequestExecutorInterface::class);
        $request  = HttpExchangeRequestFactory::create(HttpMethod::post(), '/v1/documents/synthetic');

        $exchange = $executor->execute($request);

        $this->assertSame(200, $exchange->rawStatusCode());
        $this->assertCount(1, $metrics->apiRequests);
        $this->assertSame('POST', $metrics->apiRequests[0]['method']);
        $this->assertSame(200, $metrics->apiRequests[0]['status_code']);
        $this->assertSame(ConnectorMetricsInterface::API_SUCCESS, $metrics->apiRequests[0]['outcome']);
        $this->assertGreaterThanOrEqual(0.0, $metrics->apiRequests[0]['duration_seconds']);
    }

    public function testLaravelCanSelectPooledCurlAsTheBaseHttpExecutor(): void
    {
        $container = $this->createContainerWithProvider([
            'laravel' => [
                'request_execution' => [
                    'executor' => PooledCurlRequestExecutor::class,
                ],
            ],
        ]);
        $container->instance(CircuitBreakerInterface::class, new InMemoryCircuitBreaker());
        $container->instance(
            HttpRequestBackpressureInterface::class,
            $this->createStub(HttpRequestBackpressureInterface::class)
        );

        $executor = $container->make(HttpRequestExecutorInterface::class);
        $this->assertInstanceOf(PooledCurlRequestExecutor::class, $this->findBaseExecutor($executor));
    }

    public function testRuntimeOptionsAreResolvedFromTheGroupedConfiguration(): void
    {
        $container = $this->createContainerWithProvider([
            'runtime' => [
                'http' => [
                    'connect_timeout_seconds' => 6,
                    'request_timeout_seconds' => 24,
                ],
            ],
        ]);

        $configuration = $container->make(ConnectorRuntimeConfigurationInterface::class);

        $this->assertSame(6, $configuration->httpConnectTimeoutSeconds());
        $this->assertSame(24, $configuration->httpRequestTimeoutSeconds());
    }

    public function testDefaultCacheExecutionLockRejectsLeaseShorterThanOneAttempt(): void
    {
        $container = $this->createContainerWithProvider([
            'runtime' => [
                'http' => ['request_timeout_seconds' => 45],
            ],
            'laravel' => [
                'request_execution' => [
                    'execution_lock' => [
                        'driver'        => 'cache',
                        'lease_seconds' => 49,
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 50 seconds');

        $container->make(HttpRequestExecutorInterface::class);
    }

    public function testDefaultCacheExecutionLockRejectsACustomExecutorWithoutARuntimeBound(): void
    {
        $container = $this->createContainerWithProvider([
            'laravel' => [
                'request_execution' => [
                    'executor'       => UnboundedLaravelTestHttpRequestExecutor::class,
                    'execution_lock' => [
                        'driver'        => 'cache',
                        'lease_seconds' => 1000,
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be validated for a custom HTTP request executor');

        $container->make(HttpRequestExecutorInterface::class);
    }

    public function testFileExecutionLockAllowsAConfiguredCustomExecutor(): void
    {
        $container = $this->createContainerWithProvider([
            'laravel' => [
                'request_execution' => [
                    'executor'       => UnboundedLaravelTestHttpRequestExecutor::class,
                    'execution_lock' => ['driver' => 'file'],
                ],
            ],
        ]);

        $executor = $container->make(HttpRequestExecutorInterface::class);

        $this->assertInstanceOf(UnboundedLaravelTestHttpRequestExecutor::class, $this->findBaseExecutor($executor));
    }

    public function testRequestExecutionLayersCanBeConfigured(): void
    {
        $container = $this->createContainerWithProvider([
            'runtime' => [
                'retry'           => ['enabled' => false],
                'circuit_breaker' => ['enabled' => false],
                'backpressure'    => ['enabled' => false],
            ],
        ]);

        $executor = $container->make(HttpRequestExecutorInterface::class);

        $this->assertInstanceOf(LoggingHttpRequestExecutor::class, $executor);
        $this->assertInstanceOf(CurlRequestExecutor::class, $this->findBaseExecutor($executor));
    }

    public function testValidationResourcesAndIssueLimitArePassedToValidators(): void
    {
        $container = $this->createContainerWithProvider([
            'runtime' => [
                'validation' => ['max_issues' => 4],
                'schematron' => [
                    'process_timeout_seconds'     => 72,
                    'max_concurrent_validations'  => 4,
                    'max_report_bytes'            => 12000000,
                    'max_error_output_bytes'      => 24000,
                    'max_error_detail_characters' => 240,
                ],
            ],
            'laravel' => [
                'validation' => [
                    'resource_directory'         => '/opt/connector/validation',
                    'working_directory'          => '/var/tmp/connector',
                    'concurrency_lock_directory' => '/var/tmp/connector-schematron-locks',
                    'java_binary'                => '/usr/bin/java-custom',
                ],
            ],
        ]);

        $validator = $container->make(UblSchemaValidationInterface::class);
        $this->assertInstanceOf(UblSchemaValidator::class, $validator);
        $this->assertSame('/opt/connector/validation', $this->privateProperty($validator, 'resourceDirectory'));
        $this->assertSame(4, $this->privateProperty($validator, 'maxIssues'));

        $peppolValidator = $container->make(PeppolSchematronValidationInterface::class);
        $this->assertSame(
            '/opt/connector/validation',
            $this->privateProperty($peppolValidator, 'resourceDirectory')
        );
        $this->assertSame(4, $this->privateProperty($peppolValidator, 'maxIssues'));

        $runner = $container->make(SchematronRunnerInterface::class);
        $this->assertInstanceOf(SaxonSchematronRunner::class, $runner);
        $this->assertSame('/opt/connector/validation', $this->privateProperty($runner, 'resourceDirectory'));
        $this->assertSame('/var/tmp/connector', $this->privateProperty($runner, 'workingDirectory'));
        $this->assertSame('/usr/bin/java-custom', $this->privateProperty($runner, 'configuredJavaBinary'));

        $processExecutor = $this->privateProperty($runner, 'processExecutor');
        $batchRunner     = $this->privateProperty($processExecutor, 'batchRunner');
        $this->assertSame(12000000, $this->privateProperty($batchRunner, 'maxReportBytes'));
        $this->assertSame(24000, $this->privateProperty($batchRunner, 'maxErrorOutputBytes'));
        $this->assertSame(240, $this->privateProperty($batchRunner, 'maxErrorDetailCharacters'));
        $this->assertSame(72, $this->privateProperty($processExecutor, 'processTimeoutSeconds'));
        $concurrencyLimiter = $this->privateProperty($processExecutor, 'concurrencyLimiter');
        $this->assertSame(4, $this->privateProperty($concurrencyLimiter, 'maxConcurrentValidations'));
        $lockFileStore = $this->privateProperty($concurrencyLimiter, 'lockFileStore');
        $this->assertSame(
            '/var/tmp/connector-schematron-locks',
            $this->privateProperty($lockFileStore, 'lockDirectory')
        );
    }

    public function testLaravelCanSelectAServiceImplementationFromConfiguration(): void
    {
        $container = $this->createContainerWithProvider([
            'laravel' => [
                'services' => [
                    HttpRequestBackpressureInterface::class => InMemoryHttpRequestBackpressure::class,
                ],
            ],
        ]);

        $this->assertInstanceOf(
            InMemoryHttpRequestBackpressure::class,
            $container->make(HttpRequestBackpressureInterface::class)
        );
    }

    public function testLaravelCanReplaceTheNamedSapiClientFactoryFromConfiguration(): void
    {
        $container = $this->createContainerWithProvider([
            'default_client' => 'tenant-a',
            'clients'        => [
                'tenant-a' => [
                    'base_url'      => 'https://provider.synthetic.test',
                    'client_id'     => 'client-a',
                    'client_secret' => 'secret-a',
                ],
            ],
            'laravel' => [
                'services' => [
                    SapiClientFactoryInterface::class => ConfiguredSapiClientFactory::class,
                ],
            ],
        ]);
        $client = $this->createStub(SapiClientInterface::class);
        $container->instance(SapiClientInterface::class, $client);

        $registry = $container->make(SapiClientRegistryInterface::class);
        $factory  = $container->make(SapiClientFactoryInterface::class);

        $this->assertInstanceOf(ConfiguredSapiClientFactory::class, $factory);
        $this->assertSame($client, $registry->client('tenant-a'));
        $this->assertSame($client, $registry->client('tenant-a'));
        $this->assertSame([['tenant-a', 'client-a']], $factory->createdProfiles());
    }

    public function testLaravelScopesHttpExecutionLockToApplicationBasePath(): void
    {
        $basePath = ResourcePath::join(
            sys_get_temp_dir(),
            'efaktura-laravel-lock-' . bin2hex(random_bytes(8))
        );
        mkdir($basePath, 0700);

        try {
            $container = new class ($basePath) extends Container {
                private string $applicationBasePath;

                /**
                 * Creates a container that reports the supplied application base path.
                 *
                 * @param string $applicationBasePath Application directory used by the test.
                 */
                public function __construct(string $applicationBasePath)
                {
                    $this->applicationBasePath = $applicationBasePath;
                }

                /** Returns the application base path configured for this test container. */
                public function basePath(): string
                {
                    return $this->applicationBasePath;
                }
            };
            $container->instance('config', new Repository([
                'efaktura-connector' => [
                    'laravel' => [
                        'request_execution' => [
                            'execution_lock' => ['driver' => 'file', 'temporary_directory' => $basePath],
                        ],
                    ],
                ],
            ]));
            (new EFakturaConnectorServiceProvider($container))->register();

            $lock     = $container->make(ScopedHttpRequestExecutionLockInterface::class);
            $expected = new FileHttpRequestExecutionLock($basePath, $basePath);

            $this->assertInstanceOf(FileHttpRequestExecutionLock::class, $lock);
            $this->assertSame($this->lockDirectory($expected), $this->lockDirectory($lock));
        } finally {
            @rmdir($basePath);
        }
    }

    public function testLaravelContainerPassesTheSharedCacheStoreToDefaultAdapters(): void
    {
        $container = $this->createContainerWithProvider([
            'clients' => [
                'default' => [
                    'base_url'      => 'https://provider.synthetic.test',
                    'client_id'     => 'synthetic-client',
                    'client_secret' => 'synthetic-secret',
                    'client_scope'  => 'document:send',
                ],
            ],
            'laravel' => [
                'cache_store' => 'connector-state',
            ],
        ]);

        $this->assertSame(
            'connector-state',
            $this->privateProperty($container->make(SapiTokenCacheInterface::class), 'store')
        );
        $this->assertSame(
            'connector-state',
            $this->privateProperty($container->make(SapiTokenRefreshLockInterface::class), 'store')
        );
        $this->assertSame(
            'connector-state',
            $this->privateProperty($container->make(HttpRequestBackpressureInterface::class), 'store')
        );
        $this->assertSame(
            'connector-state',
            $this->privateProperty($container->make(CircuitBreakerInterface::class), 'store')
        );
        $this->assertSame(
            'connector-state',
            $this->privateProperty($container->make(ScopedHttpRequestExecutionLockInterface::class), 'store')
        );
    }

    /** @param array<string, mixed> $packageConfig */
    private function createContainerWithProvider(array $packageConfig = []): Container
    {
        $container = new Application(sys_get_temp_dir());
        $container->instance('config', new Repository([
            'efaktura-connector' => $packageConfig,
        ]));
        (new EFakturaConnectorServiceProvider($container))->register();

        return $container;
    }

    /** Returns the HTTP executor held by a named SAPI client. */
    private function clientExecutor(object $client): HttpRequestExecutorInterface
    {
        $facades   = $client;
        $api       = $this->privateProperty($facades, 'api');
        $sender    = $this->privateProperty($api, 'transport');
        $transport = $this->privateProperty($sender, 'transport');

        return $this->privateProperty($transport, 'requestExecutor');
    }

    /** Finds the breaker within an HTTP executor decorator chain. */
    private function findCircuitBreaker(HttpRequestExecutorInterface $executor): ?CircuitBreakerInterface
    {
        if ($executor instanceof CircuitBreakerHttpRequestExecutor) {
            return $this->privateProperty($executor, 'circuitBreaker');
        }

        $reflection = new ReflectionObject($executor);

        if (! $reflection->hasProperty('executor')) {
            return null;
        }

        $executorProperty = $reflection->getProperty('executor');
        ReflectionAccess::makeAccessible($executorProperty);
        $nestedExecutor = $executorProperty->getValue($executor);

        return $nestedExecutor instanceof HttpRequestExecutorInterface
            ? $this->findCircuitBreaker($nestedExecutor)
            : null;
    }

    /**
     * Finds the metrics executor decorator within the configured executor chain.
     *
     * @param  HttpRequestExecutorInterface    $executor Executor chain to inspect.
     * @return MetricsHttpRequestExecutor|null Metrics decorator, when present.
     */
    private function findMetricsExecutor(HttpRequestExecutorInterface $executor): ?MetricsHttpRequestExecutor
    {
        if ($executor instanceof MetricsHttpRequestExecutor) {
            return $executor;
        }

        $reflection = new ReflectionObject($executor);

        if (! $reflection->hasProperty('executor')) {
            return null;
        }

        $executorProperty = $reflection->getProperty('executor');
        ReflectionAccess::makeAccessible($executorProperty);
        $nestedExecutor = $executorProperty->getValue($executor);

        return $nestedExecutor instanceof HttpRequestExecutorInterface
            ? $this->findMetricsExecutor($nestedExecutor)
            : null;
    }

    /**
     * Walks executor decorators and returns the innermost base executor.
     *
     * @param  HttpRequestExecutorInterface $executor Executor chain to inspect.
     * @return HttpRequestExecutorInterface Innermost executor found in the chain.
     */
    private function findBaseExecutor(HttpRequestExecutorInterface $executor): HttpRequestExecutorInterface
    {
        $reflection = new ReflectionObject($executor);

        if (! $reflection->hasProperty('executor')) {
            return $executor;
        }

        $executorProperty = $reflection->getProperty('executor');
        ReflectionAccess::makeAccessible($executorProperty);
        $nestedExecutor = $executorProperty->getValue($executor);

        return $nestedExecutor instanceof HttpRequestExecutorInterface
            ? $this->findBaseExecutor($nestedExecutor)
            : $executor;
    }

    /**
     * Reads the directory configured on a file-backed HTTP execution lock.
     *
     * @param  FileHttpRequestExecutionLock $lock Lock instance to inspect.
     * @return string                       Absolute lock directory path.
     */
    private function lockDirectory(FileHttpRequestExecutionLock $lock): string
    {
        $fileLock = $this->privateProperty($lock, 'fileLock');

        return $this->privateProperty($fileLock, 'lockDirectory');
    }

    /**
     * Reads a private property for container wiring assertions.
     *
     * @param  object $object Object whose property is inspected.
     * @param  string $name   Property name.
     * @return mixed  Property value.
     */
    private function privateProperty(object $object, string $name)
    {
        $property = (new ReflectionClass($object))->getProperty($name);
        ReflectionAccess::makeAccessible($property);

        return $property->getValue($object);
    }
}
