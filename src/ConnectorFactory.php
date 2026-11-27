<?php

namespace DreaM1\EfakturaConnector;

use DreaM1\EfakturaConnector\Core\Configuration\ConnectorRuntimeConfiguration;
use DreaM1\EfakturaConnector\Core\Configuration\ConnectorRuntimeConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Configuration\IntegratorAccountConfiguration;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Contracts\IntegratorAccountClientInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblDocumentWriterInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\InMemorySapiTokenCache;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenCacheInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenRefreshLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\DefaultHttpRequestExecutorFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorOptions;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Clock\ClockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\IntegratorAccountClientFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\SapiClientFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Writer\UblDocumentWriter;

/**
 * @api Framework-independent composition root for the package's default SAPI and Integrator clients.
 *
 * Each created client receives its own HTTP execution stack built from the runtime configuration.
 * Optional collaborators are added with copy-returning `with*()` methods; the factory itself is
 * immutable, so a configured instance can be shared and reused.
 */
final class ConnectorFactory
{
    private ConnectorRuntimeConfigurationInterface $runtimeConfiguration;
    private SapiTokenCacheInterface $tokenCache;
    private UblDocumentWriterInterface $writer;
    private HttpRequestExecutorOptions $executorOptions;
    private ?SapiTokenRefreshLockInterface $refreshLock = null;
    private ?ConnectorMetricsInterface $metrics         = null;
    private ?ClockInterface $clock                      = null;

    /**
     * Creates a standalone connector factory with process-local defaults.
     *
     * Supply a persistent, protected token cache for applications that need token reuse across
     * client instances or PHP worker processes.
     */
    public function __construct(
        ?ConnectorRuntimeConfigurationInterface $runtimeConfiguration = null,
        ?SapiTokenCacheInterface $tokenCache = null,
        ?UblDocumentWriterInterface $writer = null
    ) {
        $this->runtimeConfiguration = $runtimeConfiguration ?? ConnectorRuntimeConfiguration::fromArray([]);
        $this->tokenCache           = $tokenCache ?? new InMemorySapiTokenCache();
        $this->writer               = $writer ?? new UblDocumentWriter();
        $this->executorOptions      = HttpRequestExecutorOptions::defaults();
    }

    /**
     * Returns a copy that builds HTTP execution stacks from the supplied options.
     *
     * Runtime retry, cooldown and default cURL settings still come from the runtime configuration.
     * Dependency instances in the options, such as a circuit breaker or backpressure store, are
     * shared by every client created afterwards; leave them unset to keep per-client defaults.
     */
    public function withExecutorOptions(HttpRequestExecutorOptions $options): self
    {
        $factory                  = clone $this;
        $factory->executorOptions = $options;

        return $factory;
    }

    /** Returns a copy whose SAPI clients coordinate token refreshes through the supplied lock. */
    public function withTokenRefreshLock(SapiTokenRefreshLockInterface $refreshLock): self
    {
        $factory              = clone $this;
        $factory->refreshLock = $refreshLock;

        return $factory;
    }

    /**
     * Returns a copy that reports HTTP and token-cache metrics to the supplied backend.
     *
     * Metrics already set in the executor options take precedence for HTTP request metrics.
     */
    public function withMetrics(ConnectorMetricsInterface $metrics): self
    {
        $factory          = clone $this;
        $factory->metrics = $metrics;

        return $factory;
    }

    /**
     * Returns a copy that uses the supplied clock for token expiry, retry delays and cooldowns.
     *
     * A clock already set in the executor options takes precedence for the HTTP stack.
     */
    public function withClock(ClockInterface $clock): self
    {
        $factory        = clone $this;
        $factory->clock = $clock;

        return $factory;
    }

    /** Creates one SAPI client with an isolated default HTTP execution stack. */
    public function createSapiClient(
        string $name,
        SapiClientConfigurationInterface $configuration
    ): SapiClientInterface {
        return (new SapiClientFactory(
            function (): HttpRequestExecutorInterface {
                return $this->createExecutor();
            },
            $this->tokenCache,
            $this->writer,
            $this->refreshLock,
            $this->metrics,
            $this->runtimeConfiguration,
            $this->clock
        ))->create($name, $configuration);
    }

    /** Creates one Integrator API client with an isolated default HTTP execution stack. */
    public function createIntegratorAccountClient(
        IntegratorAccountConfiguration $configuration
    ): IntegratorAccountClientInterface {
        $factory = new IntegratorAccountClientFactory(function (): HttpRequestExecutorInterface {
            return $this->createExecutor();
        });

        return $factory->create($configuration);
    }

    /** Builds a new execution stack so clients do not share default resilience state. */
    private function createExecutor(): HttpRequestExecutorInterface
    {
        $options = $this->executorOptions;

        if ($options->metrics() === null && $this->metrics !== null) {
            $options = $options->withMetrics($this->metrics);
        }

        if ($options->clock() === null && $this->clock !== null) {
            $options = $options->withClock($this->clock);
        }

        return DefaultHttpRequestExecutorFactory::createFromRuntimeConfiguration(
            $this->runtimeConfiguration,
            $options
        );
    }
}
