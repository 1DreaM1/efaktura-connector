<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection;

use DreaM1\EfakturaConnector\Core\Configuration\HttpRequestBodyLimit;
use DreaM1\EfakturaConnector\Core\Configuration\HttpRuntimeConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientFactoryInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientInterface;
use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblDocumentWriterInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\AuthenticatedRequestSender;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenCacheInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenProvider;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenRefreshLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\RequestTokenEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\ProviderTransport;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Clock\ClockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;

/** Builds an authenticated API pipeline and application-facing services for one client profile. */
final class SapiClientFactory implements SapiClientFactoryInterface
{
    private SapiTokenCacheInterface $tokenCache;
    private ?SapiTokenRefreshLockInterface $refreshLock;
    private ?ConnectorMetricsInterface $metrics;
    private UblDocumentWriterInterface $writer;
    private HttpRequestBodyLimit $requestBodyLimit;
    private ?ClockInterface $clock;
    /** @var callable(SapiClientConfigurationInterface): HttpRequestExecutorInterface */
    private $executorFactory;

    /**
     * Creates a factory that obtains the HTTP executor stack from a callback for each client profile.
     *
     * A host that shares one executor between all clients returns the same instance from the callback.
     *
     * @param callable(SapiClientConfigurationInterface): HttpRequestExecutorInterface $executorFactory      Receives the client profile and returns its executor.
     * @param SapiTokenCacheInterface                                                  $tokenCache           Persistent cache whose keys are scoped to OAuth credentials.
     * @param UblDocumentWriterInterface                                               $writer               Shared local UBL writer.
     * @param SapiTokenRefreshLockInterface|null                                       $refreshLock          Optional shared token refresh coordination.
     * @param ConnectorMetricsInterface|null                                           $metrics              Optional low-cardinality metrics hook.
     * @param HttpRuntimeConfigurationInterface|null                                   $runtimeConfiguration Runtime settings for request limits.
     * @param ClockInterface|null                                                      $clock                Optional time source for token expiry.
     */
    public function __construct(
        callable $executorFactory,
        SapiTokenCacheInterface $tokenCache,
        UblDocumentWriterInterface $writer,
        ?SapiTokenRefreshLockInterface $refreshLock = null,
        ?ConnectorMetricsInterface $metrics = null,
        ?HttpRuntimeConfigurationInterface $runtimeConfiguration = null,
        ?ClockInterface $clock = null
    ) {
        $this->executorFactory  = $executorFactory;
        $this->tokenCache       = $tokenCache;
        $this->writer           = $writer;
        $this->refreshLock      = $refreshLock;
        $this->metrics          = $metrics;
        $this->clock            = $clock;
        $this->requestBodyLimit = $runtimeConfiguration === null
            ? HttpRequestBodyLimit::default()
            : $runtimeConfiguration->httpRequestBodyLimit();
    }

    /**
     * Builds a client with an isolated token provider and authenticated API transport.
     *
     * @throws ConfigurationException when the executor factory does not return an HTTP request executor.
     */
    public function create(string $name, SapiClientConfigurationInterface $configuration): SapiClientInterface
    {
        $executor = ($this->executorFactory)($configuration);

        if (! $executor instanceof HttpRequestExecutorInterface) {
            throw new ConfigurationException('The executor factory returned an invalid executor.');
        }

        $transport = ProviderTransport::forSapiClient($configuration, $executor);

        $tokenProvider = new SapiTokenProvider(
            new RequestTokenEndpoint($transport),
            $this->tokenCache,
            $configuration,
            $this->refreshLock,
            $this->metrics,
            $this->clock
        );

        $api = new ApiConnector(new AuthenticatedRequestSender($transport, $tokenProvider));

        // The token status call must report the token this client holds. Replacing a rejected token and replaying
        // would make it check a different token, so its sender forgets the token and returns the 401 instead.
        $tokenStatusApi = new ApiConnector(new AuthenticatedRequestSender($transport, $tokenProvider, 0));

        return new SapiClient($name, $configuration, $api, $this->writer, $this->requestBodyLimit, $tokenStatusApi);
    }
}
