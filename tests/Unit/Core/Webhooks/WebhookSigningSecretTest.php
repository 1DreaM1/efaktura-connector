<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Webhooks;

use DreaM1\EfakturaConnector\Core\Webhooks\WebhookSigningSecret;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests the WebhookSigningSecret value object. */
class WebhookSigningSecretTest extends TestCase
{
    public function testRevealsTheSecretForSignatureVerification(): void
    {
        $secret = WebhookSigningSecret::fromString('synthetic-secret');

        $this->assertSame('synthetic-secret', $secret->reveal());
    }

    /**
     * @dataProvider blankSecrets
     * @param string $value Blank secret candidate.
     */
    public function testRejectsBlankSecrets(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        WebhookSigningSecret::fromString($value);
    }

    /** @return array<string, array{string}> */
    public static function blankSecrets(): array
    {
        return [
            'empty'                => [''],
            'spaces'               => ['  '],
            'tabs and line breaks' => ["\t\r\n"],
        ];
    }

    public function testRedactsSecretFromDebugOutput(): void
    {
        $secret = WebhookSigningSecret::fromString('synthetic-secret');

        ob_start();
        var_dump($secret);
        $debugOutput = ob_get_clean();

        $this->assertIsString($debugOutput);
        $this->assertStringContainsString('[redacted]', $debugOutput);
        $this->assertStringNotContainsString('synthetic-secret', $debugOutput);
    }
}
