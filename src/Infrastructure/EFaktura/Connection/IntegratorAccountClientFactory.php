<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection;

use DreaM1\EfakturaConnector\Core\Configuration\IntegratorAccountConfiguration;
use DreaM1\EfakturaConnector\Core\Contracts\IntegratorAccountClientFactoryInterface;
use DreaM1\EfakturaConnector\Core\Contracts\IntegratorAccountClientInterface;
use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\IntegratorTokenRequestSender;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\PdsAuthenticatedRequestSender;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\ProviderTransport;

/** Builds Integrator API clients, their authentication paths and typed operation facades. */
final class IntegratorAccountClientFactory implements IntegratorAccountClientFactoryInterface
{
    /** @var callable(IntegratorAccountConfiguration): HttpRequestExecutorInterface */
    private $executorFactory;

    /**
     * Creates a factory that obtains the HTTP executor stack from a callback for each Integrator account.
     *
     * A host that shares one executor between accounts returns the same instance from the callback.
     *
     * @param callable(IntegratorAccountConfiguration): HttpRequestExecutorInterface $executorFactory Receives the account configuration and returns its executor.
     */
    public function __construct(callable $executorFactory)
    {
        $this->executorFactory = $executorFactory;
    }

    /**
     * {@inheritdoc}
     *
     * Builds the token and PDS connectors, composes their typed facades and passes the bundle to the client.
     *
     * @throws ConfigurationException when the executor factory does not return an HTTP request executor.
     */
    public function create(IntegratorAccountConfiguration $configuration): IntegratorAccountClientInterface
    {
        $executor = ($this->executorFactory)($configuration);

        if (! $executor instanceof HttpRequestExecutorInterface) {
            throw new ConfigurationException('The executor factory returned an invalid executor.');
        }

        $transport         = ProviderTransport::forIntegratorAccount($configuration, $executor);
        $tokenApiConnector = new ApiConnector(new IntegratorTokenRequestSender($transport, $configuration));
        $pdsApiConnector   = new ApiConnector(new PdsAuthenticatedRequestSender($transport));
        return new IntegratorAccountClient($configuration, $tokenApiConnector, $pdsApiConnector);
    }
}
