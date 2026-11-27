<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Configuration;

use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfiguration;
use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use PHPUnit\Framework\TestCase;

/** Covers provider URL normalization, explicit OAuth scopes and lazy credential readiness checks. */
class SapiClientConfigurationTest extends TestCase
{
    public function testBaseUrlIsNormalized(): void
    {
        $configuration = $this->configuration(' https://sapi.synthetic.test/// ');

        $this->assertSame('https://sapi.synthetic.test', $configuration->baseUrl()->value());
    }

    public function testBaseUrlAcceptsAnUppercaseHttpsScheme(): void
    {
        $configuration = $this->configuration('HTTPS://sapi.synthetic.test/');

        $this->assertSame('HTTPS://sapi.synthetic.test', $configuration->baseUrl()->value());
    }

    public function testSapiSuffixWithTrailingSlashIsPreserved(): void
    {
        $configuration = $this->configuration('https://sapi.synthetic.test/sapi/');

        $this->assertSame('https://sapi.synthetic.test/sapi', $configuration->baseUrl()->value());
    }

    public function testSapiSuffixIsPreservedAfterAReverseProxyPrefix(): void
    {
        $configuration = $this->configuration('https://proxy.synthetic.test/provider/sapi');

        $this->assertSame('https://proxy.synthetic.test/provider/sapi', $configuration->baseUrl()->value());
    }

    public function testFromArrayTrimsConfiguredValuesAndRequiresAnExplicitScope(): void
    {
        $configuration = SapiClientConfiguration::fromArray([
            'base_url'      => ' https://sapi.synthetic.test/ ',
            'client_id'     => ' synthetic-client ',
            'client_secret' => 'synthetic-secret',
            'client_scope'  => ' document:send ',
        ]);

        $this->assertSame('https://sapi.synthetic.test', $configuration->baseUrl()->value());
        $this->assertSame('synthetic-client', $configuration->clientId()->value());
        $this->assertSame('synthetic-secret', $configuration->clientSecret()->reveal());
        $this->assertSame('document:send', $configuration->clientScope()->value());
    }

    public function testMissingScopeDoesNotFallBackToBroadPermissions(): void
    {
        $configuration = SapiClientConfiguration::fromArray([
            'base_url'      => 'https://sapi.synthetic.test',
            'client_id'     => 'synthetic-client',
            'client_secret' => 'synthetic-secret',
        ]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('OAuth scope is not configured');

        $configuration->assertReady();
    }

    public function testFromArrayReadsStandaloneSettings(): void
    {
        $configuration = SapiClientConfiguration::fromArray([
            'base_url'      => ' https://sapi.synthetic.test/ ',
            'client_id'     => ' synthetic-client ',
            'client_secret' => 'synthetic-secret',
            'client_scope'  => ' document:send ',
        ]);

        $this->assertSame('https://sapi.synthetic.test', $configuration->baseUrl()->value());
        $this->assertSame('synthetic-client', $configuration->clientId()->value());
        $this->assertSame('synthetic-secret', $configuration->clientSecret()->reveal());
        $this->assertSame('document:send', $configuration->clientScope()->value());
    }


    public function testFromArrayRejectsMissingScopeWhenUsed(): void
    {
        $configuration = SapiClientConfiguration::fromArray([
            'base_url'      => 'https://sapi.synthetic.test',
            'client_id'     => 'synthetic-client',
            'client_secret' => 'synthetic-secret',
            'client_scope'  => null,
        ]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('OAuth scope is not configured');
        $configuration->clientScope();

        $configuration->clientScope();
    }

    public function testFromArrayRejectsNonScalarScopeValuesWhenUsed(): void
    {
        $configuration = SapiClientConfiguration::fromArray([
            'base_url'      => 'https://sapi.synthetic.test',
            'client_id'     => 'synthetic-client',
            'client_secret' => 'synthetic-secret',
            'client_scope'  => [],
        ]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('OAuth scope is not configured');
        $configuration->clientScope();
    }

    public function testBlankScopeIsRejectedWhenRead(): void
    {
        $configuration = new SapiClientConfiguration(
            'https://sapi.synthetic.test',
            'synthetic-client',
            'synthetic-secret',
            '  '
        );

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('OAuth scope');
        $configuration->clientScope();
    }

    public function testMalformedScopeIsRejectedWhenRead(): void
    {
        $configuration = new SapiClientConfiguration(
            'https://sapi.synthetic.test',
            'synthetic-client',
            'synthetic-secret',
            'document:send  document:receive'
        );

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('OAuth scope configuration');
        $configuration->clientScope();
    }

    /**
     * Rejects base URLs that do not meet the connector's HTTPS requirements.
     *
     * @param string $baseUrl URL value supplied to connector configuration.
     * @dataProvider invalidBaseUrlProvider
     */
    public function testBaseUrlMustBeSafeHttps(string $baseUrl): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Provider base URL');

        $this->configuration($baseUrl)->baseUrl();
    }

    /**
     * Provides base URLs rejected by connector configuration validation.
     *
     * @return array<array-key, array<array-key, mixed>> Invalid URL cases.
     */
    public function invalidBaseUrlProvider(): array
    {
        return [
            'empty'              => [''],
            'plain HTTP'         => ['http://sapi.synthetic.test'],
            'missing host'       => ['https:///v1'],
            'URL credentials'    => ['https://user:password@sapi.synthetic.test'],
            'query string'       => ['https://sapi.synthetic.test?debug=1'],
            'fragment'           => ['https://sapi.synthetic.test#section'],
            'control characters' => ["https://sapi.synthetic.test\r\nX-Test: injected"],
        ];
    }

    /**
     * Defers credential validation until the credential-dependent operation is used.
     *
     * @param string $clientId     OAuth client identifier under test.
     * @param string $clientSecret OAuth client secret under test.
     * @dataProvider missingCredentialsProvider
     */
    public function testCredentialsAreRequiredLazily(string $clientId, string $clientSecret): void
    {
        $configuration = new SapiClientConfiguration(
            'https://sapi.synthetic.test',
            $clientId,
            $clientSecret,
            'document:send'
        );

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('OAuth client ID or secret');

        $configuration->clientSecret();
    }

    /**
     * Provides credential combinations that are incomplete or blank.
     *
     * @return array<array-key, array<array-key, mixed>> Invalid credential cases.
     */
    public function missingCredentialsProvider(): array
    {
        return [
            'client id'     => [' ', 'synthetic-secret'],
            'client secret' => ['synthetic-client', ''],
        ];
    }

    private function configuration(string $baseUrl): SapiClientConfiguration
    {
        return new SapiClientConfiguration($baseUrl, 'synthetic-client', 'synthetic-secret', 'document:send');
    }
}
