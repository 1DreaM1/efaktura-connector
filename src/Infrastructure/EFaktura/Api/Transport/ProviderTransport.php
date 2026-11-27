<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport;

use DreaM1\EfakturaConnector\Core\Configuration\IntegratorAccountConfiguration;
use DreaM1\EfakturaConnector\Core\Configuration\ProviderBaseUrl;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientProfile;
use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureKey;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Binds requests to one configured provider base URL, a backpressure scope and an executor stack. */
final class ProviderTransport implements HttpTransportInterface
{
    private string $configuredBaseUrl;
    private string $backpressureScopeKey;
    private HttpRequestExecutorInterface $requestExecutor;

    /**
     * Creates a transport for one provider account.
     *
     * @param string                       $configuredBaseUrl    Configured provider base URL requests are bound to.
     * @param string                       $backpressureScopeKey Key under which shared provider backpressure is tracked.
     * @param HttpRequestExecutorInterface $requestExecutor      HTTP executor stack used by this transport.
     */
    public function __construct(
        string $configuredBaseUrl,
        string $backpressureScopeKey,
        HttpRequestExecutorInterface $requestExecutor
    ) {
        $this->configuredBaseUrl    = $configuredBaseUrl;
        $this->backpressureScopeKey = $backpressureScopeKey;
        $this->requestExecutor      = $requestExecutor;
    }

    /**
     * Creates a transport for one SAPI client, scoped by its client ID and scope.
     *
     * @param SapiClientConfigurationInterface $configuration   SAPI client used for API calls.
     * @param HttpRequestExecutorInterface     $requestExecutor HTTP executor stack used by the transport.
     */
    public static function forSapiClient(
        SapiClientConfigurationInterface $configuration,
        HttpRequestExecutorInterface $requestExecutor
    ): self {
        $profile = SapiClientProfile::fromConfiguration($configuration);

        return new self(
            $profile->baseUrl(),
            HttpRequestBackpressureKey::scopeForConfiguredProvider(
                $profile->baseUrl(),
                $profile->clientId(),
                $profile->clientScope()
            ),
            $requestExecutor
        );
    }

    /**
     * Creates a transport for one Integrator API account, scoped by its provider URL and integrator ID.
     *
     * The token is not part of the scope, so token rotation keeps the account's shared cooldown and lock,
     * matching the circuit breaker identity.
     *
     * @param IntegratorAccountConfiguration $configuration   Integrator account used for API calls.
     * @param HttpRequestExecutorInterface   $requestExecutor HTTP executor stack used by the transport.
     */
    public static function forIntegratorAccount(
        IntegratorAccountConfiguration $configuration,
        HttpRequestExecutorInterface $requestExecutor
    ): self {
        $baseUrl = $configuration->baseUrl()->value();

        return new self(
            $baseUrl,
            HttpRequestBackpressureKey::scopeForConfiguredProvider(
                $baseUrl,
                $configuration->integratorId()->value()
            ),
            $requestExecutor
        );
    }

    /**
     * Sends the prepared request and returns the provider response.
     *
     * @param  HttpExchangeRequest          $request HTTP request to bind to the configured base URL and send.
     * @return HttpExchangeResult
     * @throws ProviderApiException         when the executor request fails
     * @throws RequestCoordinationException when local request coordination fails
     * @throws ConfigurationException       when required provider configuration is missing or invalid
     * @throws InvalidInputException        when the request path does not form a safe provider URL
     */
    public function send(HttpExchangeRequest $request): HttpExchangeResult
    {
        // Keep the configured base URL and relative path separate until an executor sends the request.
        $prepared = $request
            ->withBaseUrl(ProviderBaseUrl::fromString($this->configuredBaseUrl))
            ->withBackpressureScopeKey($this->backpressureScopeKey);

        return $this->requestExecutor->execute($prepared);
    }
}
