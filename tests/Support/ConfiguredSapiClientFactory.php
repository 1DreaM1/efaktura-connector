<?php

namespace DreaM1\EfakturaConnector\Tests\Support;

use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientFactoryInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientInterface;

/** Test factory used to verify configured Laravel client-factory overrides. */
final class ConfiguredSapiClientFactory implements SapiClientFactoryInterface
{
    private SapiClientInterface $client;

    /** @var array<int, array{string, string}> */
    private array $createdProfiles = [];

    /** @param SapiClientInterface $client Client returned for every requested profile. */
    public function __construct(SapiClientInterface $client)
    {
        $this->client = $client;
    }

    /** {@inheritdoc} */
    public function create(string $name, SapiClientConfigurationInterface $configuration): SapiClientInterface
    {
        $this->createdProfiles[] = [$name, $configuration->configuredClientId()];

        return $this->client;
    }

    /** @return array<int, array{string, string}> */
    public function createdProfiles(): array
    {
        return $this->createdProfiles;
    }
}
