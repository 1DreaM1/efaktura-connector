<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication;

use DreaM1\EfakturaConnector\Core\Configuration\IntegratorAccountConfiguration;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpTransportInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;

/** Adds the configured Integrator API token to token-authenticated requests. */
final class IntegratorTokenRequestSender implements HttpTransportInterface
{
    private HttpTransportInterface $transport;
    private IntegratorAccountConfiguration $configuration;

    /**
     * Creates an Integrator API token sender around a configured HTTP transport.
     *
     * @param HttpTransportInterface         $transport     Transport that binds the provider URL and executes requests.
     * @param IntegratorAccountConfiguration $configuration Integrator API credential configuration.
     */
    public function __construct(HttpTransportInterface $transport, IntegratorAccountConfiguration $configuration)
    {
        $this->transport     = $transport;
        $this->configuration = $configuration;
    }

    /** Replaces caller-supplied authentication headers with the configured Integrator API token. */
    public function send(HttpExchangeRequest $request): HttpExchangeResult
    {
        $authenticatedRequest = $request
            ->withoutHeader(HttpHeaderName::authorization())
            ->withoutHeader(HttpHeaderName::pdsSecret())
            ->withHeader(
                HttpHeaderName::integratorToken(),
                $this->configuration->token()->reveal()
            );

        return $this->transport->send($authenticatedRequest);
    }
}
