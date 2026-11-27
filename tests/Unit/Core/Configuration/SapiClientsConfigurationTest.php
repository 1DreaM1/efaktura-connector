<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Configuration;

use DreaM1\EfakturaConnector\Core\Configuration\SapiClientsConfiguration;
use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use PHPUnit\Framework\TestCase;

/** Tests parsing and lookup of named OAuth client profiles. */
class SapiClientsConfigurationTest extends TestCase
{
    public function testLoadsMultipleNamedClientsAndConfiguredDefault(): void
    {
        $configuration = SapiClientsConfiguration::fromArray([
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
                    'client_scope'  => 'document:receive',
                ],
            ],
        ]);

        $this->assertSame(['tenant-a', 'tenant-b'], $configuration->clientNames());
        $this->assertSame('tenant-b', $configuration->defaultClientName());
        $this->assertSame(
            'https://provider.synthetic.test',
            $configuration->client('tenant-a')->baseUrl()->value()
        );
        $this->assertSame(
            'https://provider.synthetic.test',
            $configuration->client('tenant-b')->baseUrl()->value()
        );
        $this->assertSame('client-a', $configuration->client('tenant-a')->clientId()->value());
        $this->assertSame('secret-b', $configuration->defaultClient()->clientSecret()->reveal());
    }

    public function testEachProfileReadsItsOwnBaseUrl(): void
    {
        $configuration = SapiClientsConfiguration::fromArray([
            'default_client' => 'live',
            'clients'        => [
                'live' => [
                    'base_url'  => ' https://live.synthetic.test/// ',
                    'client_id' => 'client-live',
                ],
                'test' => [
                    'base_url'  => 'https://test.synthetic.test',
                    'client_id' => 'client-test',
                ],
            ],
        ]);

        $this->assertSame('https://live.synthetic.test', $configuration->client('live')->baseUrl()->value());
        $this->assertSame('https://test.synthetic.test', $configuration->client('test')->baseUrl()->value());
        $this->assertSame('https://live.synthetic.test', $configuration->client('live')->configuredBaseUrl());
    }

    public function testAllowsTheSeparateIntegratorApiSection(): void
    {
        $configuration = SapiClientsConfiguration::fromArray([
            'clients'        => ['default' => ['base_url' => 'https://provider.synthetic.test']],
            'integrator_api' => [
                'base_url'      => 'https://integrator.synthetic.test',
                'integrator_id' => 'synthetic-integrator',
                'token'         => 'synthetic-integrator-token',
            ],
        ]);

        $this->assertSame(['default'], $configuration->clientNames());
    }

    public function testMissingClientsAreRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('At least one SAPI client');

        SapiClientsConfiguration::fromArray(['clients' => []]);
    }


    public function testRejectsUnknownTopLevelAndClientProfileKeys(): void
    {
        try {
            SapiClientsConfiguration::fromArray([
                'client'  => 'typo',
                'clients' => ['default' => [
                    'client_id'     => 'client',
                    'client_secret' => 'secret',
                    'client_scope'  => 'document:send',
                ]],
            ]);
            $this->fail('Expected an unknown top-level key to be rejected.');
        } catch (ConfigurationException $exception) {
            $this->assertStringContainsString('Unknown connector configuration key "client"', $exception->getMessage());
        }

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Unknown clients.default configuration key "client_secert"');
        SapiClientsConfiguration::fromArray([
            'clients' => ['default' => [
                'client_id'     => 'client',
                'client_secret' => 'secret',
                'client_scope'  => 'document:send',
                'client_secert' => 'typo',
            ]],
        ]);
    }

    public function testAssertReadyRequiresEveryClientProfileToHaveExplicitCredentialsAndScope(): void
    {
        $configuration = SapiClientsConfiguration::fromArray([
            'clients' => ['default' => [
                'base_url'      => 'https://provider.synthetic.test',
                'client_id'     => 'client',
                'client_secret' => 'secret',
            ]],
        ]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('OAuth scope is not configured');
        $configuration->assertReady();
    }

    public function testAssertReadyAcceptsValidExplicitClientConfiguration(): void
    {
        $configuration = SapiClientsConfiguration::fromArray([
            'clients' => ['default' => [
                'base_url'      => 'https://provider.synthetic.test',
                'client_id'     => 'client',
                'client_secret' => 'secret',
                'client_scope'  => 'document:send',
            ]],
        ]);

        $configuration->assertReady();
        $this->assertSame('document:send', $configuration->defaultClient()->clientScope()->value());
    }

    public function testDefaultClientMustNameAConfiguredProfile(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('default SAPI client');

        SapiClientsConfiguration::fromArray([
            'default_client' => 'missing',
            'clients'        => ['tenant-a' => []],
        ]);
    }

    public function testMalformedClientAliasesAndProfilesAreRejected(): void
    {
        try {
            SapiClientsConfiguration::fromArray([
                'clients' => ['bad alias' => []],
            ]);
            $this->fail('Expected malformed alias to be rejected.');
        } catch (ConfigurationException $exception) {
            $this->assertStringContainsString('client names', $exception->getMessage());
        }

        try {
            SapiClientsConfiguration::fromArray([
                'clients' => [123 => []],
            ]);
            $this->fail('Expected a numeric client alias to be rejected.');
        } catch (ConfigurationException $exception) {
            $this->assertStringContainsString('client names', $exception->getMessage());
        }

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('profile must be an array');
        SapiClientsConfiguration::fromArray([
            'clients' => ['tenant-a' => 'invalid'],
        ]);
    }

    public function testBaseUrlCannotBeConfiguredAtTheTopLevel(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('clients.<alias>.base_url');

        SapiClientsConfiguration::fromArray([
            'base_url' => 'https://provider.synthetic.test',
            'clients'  => [
                'tenant-a' => [
                    'client_id'     => 'client-a',
                    'client_secret' => 'secret-a',
                ],
            ],
        ]);
    }

    public function testUnknownClientAliasIsRejected(): void
    {
        $configuration = SapiClientsConfiguration::fromArray([
            'clients' => ['default' => []],
        ]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('requested SAPI client');
        $configuration->client('unknown');
    }
}
