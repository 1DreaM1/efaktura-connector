<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Support;

use DreaM1\EfakturaConnector\Core\Configuration\IntegratorToken;
use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientSecret;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfiguration;
use DreaM1\EfakturaConnector\Core\IntegratorApi\PdsSigningSecret;
use DreaM1\EfakturaConnector\Core\IntegratorApi\PdsVerificationToken;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookSigningSecret;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\OAuthAccessToken;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiToken;
use DreaM1\EfakturaConnector\Support\Exceptions\ConnectorThrowable;
use DreaM1\EfakturaConnector\Support\Exceptions\InvariantViolationException;
use PHPUnit\Framework\TestCase;

/** Secret-holding value objects must never be written out by serialize(). */
class RefusesSerializationTest extends TestCase
{
    private const SECRET = 'synthetic-secret-value';

    /**
     * @dataProvider secrets
     */
    public function testSerializeIsRefusedWithoutRevealingTheSecret(callable $factory): void
    {
        $object = $factory();

        try {
            serialize($object);
            $this->fail(get_class($object) . ' must refuse serialization.');
        } catch (InvariantViolationException $exception) {
            $this->assertInstanceOf(ConnectorThrowable::class, $exception);
            $this->assertStringContainsString(get_class($object), $exception->getMessage());
            $this->assertStringNotContainsString(self::SECRET, $exception->getMessage());
        }
    }

    /**
     * @dataProvider secrets
     */
    public function testUnserializeOfACraftedPayloadIsRefused(callable $factory): void
    {
        $class   = get_class($factory());
        $payload = 'O:' . strlen($class) . ':"' . $class . '":1:{s:5:"value";s:3:"abc";}';

        $this->expectException(InvariantViolationException::class);

        unserialize($payload);
    }

    /**
     * @dataProvider allSecretCarriers
     */
    public function testDebugOutputStaysRedacted(callable $factory): void
    {
        $object = $factory();

        $this->assertStringNotContainsString(self::SECRET, print_r($object, true));

        ob_start();
        var_dump($object);
        $dump = (string) ob_get_clean();

        $this->assertStringNotContainsString(self::SECRET, $dump);
    }

    /**
     * @dataProvider secretHolders
     */
    public function testObjectsHoldingASecretCannotBeSerializedEither(callable $factory): void
    {
        try {
            serialize($factory());
            $this->fail('An object holding a secret must not be serializable.');
        } catch (InvariantViolationException $exception) {
            $this->assertStringContainsString('holds a secret and cannot be serialized', $exception->getMessage());
            $this->assertStringNotContainsString(self::SECRET, $exception->getMessage());
        }
    }

    /** @return array<string, array{callable}> */
    public static function allSecretCarriers(): array
    {
        return self::secrets() + self::secretHolders();
    }
    /** @return array<string, array{callable}> */
    public static function secrets(): array
    {
        return [
            'OAuth client secret' => [static function () {
                return OAuthClientSecret::fromString(self::SECRET);
            }],
            'Integrator token' => [static function () {
                return IntegratorToken::fromString(self::SECRET);
            }],
            'PDS signing secret' => [static function () {
                return PdsSigningSecret::fromString(self::SECRET);
            }],
            'PDS verification token' => [static function () {
                return PdsVerificationToken::fromString(self::SECRET);
            }],
            'Webhook signing secret' => [static function () {
                return WebhookSigningSecret::fromString(self::SECRET);
            }],
            'OAuth access token' => [static function () {
                return OAuthAccessToken::fromString(self::SECRET);
            }],
        ];
    }

    /** @return array<string, array{callable}> */
    public static function secretHolders(): array
    {
        return [
            'SAPI token (holds an access token)' => [static function () {
                return new SapiToken(OAuthAccessToken::fromString(self::SECRET), 2000000000);
            }],
            'SAPI client configuration (holds a client secret)' => [static function () {
                return SapiClientConfiguration::fromArray([
                    'base_url'      => 'https://sapi.example.test',
                    'client_id'     => 'synthetic-client',
                    'client_secret' => self::SECRET,
                    'client_scope'  => 'synthetic-scope',
                ]);
            }],
        ];
    }
}
