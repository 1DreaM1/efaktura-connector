<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Logging;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Logging\SensitiveDataRedactor;
use PHPUnit\Framework\TestCase;

/** Tests sensitive API data redaction behavior. */
class SensitiveDataRedactorTest extends TestCase
{
    public function testRedactsBearerTokensAndCredentialFieldsInDifferentFormats(): void
    {
        $input = 'Authorization: Bearer abc.def/ghi==; "client_secret": "client-value", '
            . "access-token='access-value' refresh_token=refresh-value; password: p@ssword";

        $this->assertSame(
            'Authorization: Bearer [redacted]; "client_secret": "[redacted]", '
                . "access-token='[redacted]' refresh_token=[redacted]; password: [redacted]",
            SensitiveDataRedactor::redactCredentials($input)
        );
    }

    public function testRedactsApiAndPrivateKeysWithoutChangingUnrelatedText(): void
    {
        $input = 'api-key=synthetic-key private_key:synthetic-private tokenized=keep customer=visible';

        $this->assertSame(
            'api-key=[redacted] private_key:[redacted] tokenized=keep customer=visible',
            SensitiveDataRedactor::redactCredentials($input)
        );
    }

    public function testUsesTheSharedSensitiveFieldVocabularyForCredentialNames(): void
    {
        $this->assertSame(
            'client_credential=[redacted] tenant=visible',
            SensitiveDataRedactor::redactCredentials('client_credential=synthetic-value tenant=visible')
        );
    }

    public function testRedactsCredentialValuesContainingEscapedQuotes(): void
    {
        $this->assertSame(
            'client_secret="[redacted]"',
            SensitiveDataRedactor::redactCredentials('client_secret="prefix\"suffix"')
        );
    }

    public function testKeepsFieldsAfterARedactedValueInFormEncodedText(): void
    {
        $this->assertSame(
            'client_id=x&client_secret=[redacted]&grant_type=client_credentials',
            SensitiveDataRedactor::redactCredentials(
                'client_id=x&client_secret=synthetic-secret&grant_type=client_credentials'
            )
        );
    }

    public function testRedactsEveryCredentialInAFormEncodedChain(): void
    {
        $this->assertSame(
            'client_secret=[redacted]&password=[redacted]&access_token=[redacted]&scope=visible',
            SensitiveDataRedactor::redactCredentials(
                'client_secret=one&password=two&access_token=three&scope=visible'
            )
        );
    }

    public function testRedactsAnEmptyOrPercentEncodedFormValueWithoutLeakingItsTail(): void
    {
        $this->assertSame(
            'token=[redacted]&x=1 client_secret=[redacted]&grant_type=x',
            SensitiveDataRedactor::redactCredentials('token=&x=1 client_secret=p%26ss&grant_type=x')
        );
    }

    public function testRedactsTheWholeValueAfterAColonEvenWhenItContainsAnAmpersand(): void
    {
        $this->assertSame(
            'password: [redacted]',
            SensitiveDataRedactor::redactCredentials('password: p&ss&word')
        );
    }
    public function testBearerOnlyRedactionIsCaseInsensitive(): void
    {
        $this->assertSame(
            'Bearer [redacted], Bearer [redacted]',
            SensitiveDataRedactor::redactBearerTokens('bearer first-token, BEARER second+/token==')
        );
    }
}
