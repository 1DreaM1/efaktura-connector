<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Configuration;

use DreaM1\EfakturaConnector\Core\Configuration\PdsSigningConfiguration;
use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use PHPUnit\Framework\TestCase;

/** Tests parsing the optional PDS signing secret configured separately from the Integrator API token. */
class PdsSigningConfigurationTest extends TestCase
{
    public function testFromArrayReturnsTheConfiguredSecret(): void
    {
        $configuration = PdsSigningConfiguration::fromArray(['signing_secret' => 'synthetic-pds-secret']);

        $this->assertTrue($configuration->hasSigningSecret());
        $this->assertSame('synthetic-pds-secret', $configuration->requireSigningSecret()->reveal());
        $this->assertSame($configuration->requireSigningSecret(), $configuration->signingSecret());
    }

    /**
     * @dataProvider unconfiguredSections
     * @param array<string, mixed> $section
     */
    public function testAbsentNullOrEmptySecretIsNotConfigured(array $section): void
    {
        $configuration = PdsSigningConfiguration::fromArray($section);

        $this->assertFalse($configuration->hasSigningSecret());
        $this->assertNull($configuration->signingSecret());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public function unconfiguredSections(): array
    {
        return [
            'empty section' => [[]],
            'null secret'   => [['signing_secret' => null]],
            'empty secret'  => [['signing_secret' => '']],
        ];
    }

    public function testRequireSigningSecretFailsWhenNotConfigured(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('PDS signing secret is not configured');

        (new PdsSigningConfiguration())->requireSigningSecret();
    }

    public function testFromArrayRejectsUnknownSettings(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Unknown integrator_api.pds configuration key "secret"');

        PdsSigningConfiguration::fromArray(['secret' => 'typo']);
    }

    public function testFromArrayRejectsAWhitespaceOnlySecret(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('integrator_api.pds.signing_secret setting must not be blank');

        PdsSigningConfiguration::fromArray(['signing_secret' => '   ']);
    }

    public function testFromArrayRejectsANonStringSecret(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('integrator_api.pds.signing_secret setting must be a string');

        PdsSigningConfiguration::fromArray(['signing_secret' => ['nested']]);
    }

    public function testDebugOutputDoesNotRevealTheSecret(): void
    {
        $configuration = PdsSigningConfiguration::fromArray(['signing_secret' => 'synthetic-pds-secret']);

        $this->assertStringNotContainsString('synthetic-pds-secret', print_r($configuration, true));
    }
}
