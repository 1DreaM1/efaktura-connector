<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Configuration;

use DreaM1\EfakturaConnector\Core\Configuration\IntegratorAccountConfiguration;
use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests parsing Integrator API credentials and provider settings. */
class IntegratorAccountConfigurationTest extends TestCase
{
    public function testFromArrayCreatesConfigurationAndRedactsTokenDebugInfo(): void
    {
        $configuration = IntegratorAccountConfiguration::fromArray([
            'base_url'      => 'https://integrator.synthetic.test/',
            'integrator_id' => ' synthetic-integrator ',
            'token'         => 'synthetic-integrator-token',
        ]);

        $this->assertSame('https://integrator.synthetic.test', $configuration->baseUrl()->value());
        $this->assertSame('synthetic-integrator', $configuration->integratorId()->value());
        $this->assertSame('synthetic-integrator-token', $configuration->token()->reveal());
        $this->assertSame('[redacted]', $configuration->token()->__debugInfo()['value']);
    }

    public function testFromArrayRejectsUnknownSettings(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Unknown integrator_api configuration key "api_tokken"');

        IntegratorAccountConfiguration::fromArray([
            'base_url'      => 'https://integrator.synthetic.test',
            'integrator_id' => 'synthetic-integrator',
            'token'         => 'synthetic-integrator-token',
            'api_tokken'    => 'typo',
        ]);
    }

    public function testFromArrayRequiresAnIntegratorIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Integrator identifier must not be blank');

        IntegratorAccountConfiguration::fromArray([
            'base_url' => 'https://integrator.synthetic.test',
            'token'    => 'synthetic-integrator-token',
        ]);
    }

    public function testFromArrayRequiresAToken(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Integrator API token must not be blank');

        IntegratorAccountConfiguration::fromArray([
            'base_url'      => 'https://integrator.synthetic.test',
            'integrator_id' => 'synthetic-integrator',
        ]);
    }

    public function testFromArrayRequiresASafeHttpsBaseUrl(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Provider base URL must be a safe HTTPS URL');

        IntegratorAccountConfiguration::fromArray([
            'base_url'      => 'http://integrator.synthetic.test',
            'integrator_id' => 'synthetic-integrator',
            'token'         => 'synthetic-integrator-token',
        ]);
    }
}
