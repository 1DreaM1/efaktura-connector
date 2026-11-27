<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi\SapiTokenResponse;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use PHPUnit\Framework\TestCase;

/** Checks required SAPI token response fields, token types and malformed provider values. */
class SapiTokenResponseTest extends TestCase
{
    public function testParsesRequestTokenResponseSchema(): void
    {
        $response = SapiTokenResponse::fromArray(ApiFixtures::token());

        $this->assertSame('synthetic-access-token', $response->accessToken->reveal());
        $this->assertSame('Bearer', $response->tokenType->value());
        $this->assertSame(900, $response->expiresIn->seconds());
        $this->assertSame('document:send document:receive', $response->scope->value());
    }

    public function testTokenResponseRequiresExpiry(): void
    {
        $this->expectException(ProviderApiException::class);

        SapiTokenResponse::fromArray(['access_token' => 'synthetic-access-token', 'token_type' => 'Bearer']);
    }

    public function testBearerTokenTypeIsCaseInsensitiveAndNormalized(): void
    {
        $response = SapiTokenResponse::fromArray(ApiFixtures::token(['token_type' => 'bearer']));

        $this->assertSame('Bearer', $response->tokenType->value());
    }

    /**
     * Rejects malformed access-token response values and fields.
     *
     * @param array<string, mixed> $data  Token response data.
     * @param string               $field Field expected to trigger validation failure.
     * @dataProvider invalidTokenFields
     */
    public function testRejectsInvalidTokenValues(array $data, string $field): void
    {
        try {
            SapiTokenResponse::fromArray($data);
            $this->fail('Invalid token response should be rejected.');
        } catch (InvalidProviderResponseException $exception) {
            $this->assertSame('SapiTokenResponse', $exception->schema());
            $this->assertSame($field, $exception->field());
        }
    }

    /**
     * Provides invalid access-token values and response fields.
     *
     * @return array<array-key, array<array-key, mixed>> Invalid token field cases.
     */
    public function invalidTokenFields(): array
    {
        return [
            'invalid token syntax' => [[
                'access_token' => 'token with spaces', 'token_type' => 'Bearer', 'expires_in' => 60,
            ], 'access_token'],
            'unsupported type' => [[
                'access_token' => 'valid-token', 'token_type' => 'Basic', 'expires_in' => 60,
            ], 'token_type'],
            'negative expiry' => [[
                'access_token' => 'valid-token', 'token_type' => 'Bearer', 'expires_in' => -1,
            ], 'expires_in'],
            'invalid optional scope' => [[
                'access_token' => 'valid-token', 'token_type' => 'Bearer', 'expires_in' => 60, 'scope' => [],
            ], 'scope'],
        ];
    }
}
