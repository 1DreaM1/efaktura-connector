<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection;

use DreaM1\EfakturaConnector\Core\Configuration\SapiClientsConfiguration;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientFactoryInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientRegistryInterface;

/** Lazily creates and retains isolated SAPI clients for configured aliases. */
final class SapiClientRegistry implements SapiClientRegistryInterface
{
    private SapiClientsConfiguration $configuration;
    private SapiClientFactoryInterface $factory;

    /** @var array<string, SapiClientInterface> */
    private array $clients = [];

    /**
     * Creates a lazy client registry for the configured profiles.
     *
     * @param SapiClientsConfiguration   $configuration Named OAuth client profiles and the default alias.
     * @param SapiClientFactoryInterface $factory       Factory used to build a profile on first access.
     */
    public function __construct(SapiClientsConfiguration $configuration, SapiClientFactoryInterface $factory)
    {
        $this->configuration = $configuration;
        $this->factory       = $factory;
    }

    /** {@inheritdoc} */
    public function client(string $name): SapiClientInterface
    {
        if (! array_key_exists($name, $this->clients)) {
            $profile              = $this->configuration->client($name);
            $this->clients[$name] = $this->factory->create($name, $profile);
        }

        return $this->clients[$name];
    }

    /** {@inheritdoc} */
    public function defaultClient(): SapiClientInterface
    {
        return $this->client($this->configuration->defaultClientName());
    }

    /** {@inheritdoc} */
    public function defaultClientName(): string
    {
        return $this->configuration->defaultClientName();
    }

    /** {@inheritdoc} */
    public function clientNames(): array
    {
        return $this->configuration->clientNames();
    }
}
