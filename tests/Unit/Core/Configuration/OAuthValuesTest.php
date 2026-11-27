<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Configuration;

use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientSecret;
use DreaM1\EfakturaConnector\Core\Configuration\OAuthScope;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests OAuth scope and secret value objects. */
class OAuthValuesTest extends TestCase
{
    public function testOAuthScopeExposesTokensWithoutRestrictingProviderNames(): void
    {
        $scope = OAuthScope::fromString('document:send provider-specific_scope object/read');

        $this->assertSame('document:send provider-specific_scope object/read', $scope->value());
        $this->assertSame(['document:send', 'provider-specific_scope', 'object/read'], $scope->tokens());
        $this->assertTrue($scope->contains('provider-specific_scope'));
        $this->assertFalse($scope->contains('document:receive'));
    }

    /**
     * @dataProvider invalidScopeProvider
     */
    public function testOAuthScopeRejectsMalformedScopeStrings(string $scope): void
    {
        $this->expectException(InvalidArgumentException::class);

        OAuthScope::fromString($scope);
    }

    /** @return array<string, array{string}> Malformed OAuth scope inputs and their case names. */
    public function invalidScopeProvider(): array
    {
        return [
            'blank'              => [''],
            'whitespace'         => [" \t"],
            'repeated separator' => ['document:send  document:receive'],
            'quote in token'     => ['document:"send'],
        ];
    }

    public function testOAuthClientSecretRevealsOnlyWhenRequestedAndRedactsDebugOutput(): void
    {
        $secret = OAuthClientSecret::fromString('synthetic-secret');
        ob_start();
        var_dump($secret);
        $debugOutput = ob_get_clean();

        $this->assertSame('synthetic-secret', $secret->reveal());
        $this->assertStringContainsString('[redacted]', $debugOutput);
        $this->assertStringNotContainsString('synthetic-secret', $debugOutput);
    }

    public function testOAuthClientSecretRejectsBlankValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OAuthClientSecret::fromString(" \t");
    }
}
