<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Exceptions;

use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Core\Exceptions\DocumentPreflightException;
use DreaM1\EfakturaConnector\Core\Exceptions\DocumentRejectedByProviderException;
use DreaM1\EfakturaConnector\Core\Exceptions\EFakturaException;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\RecipientUnsupportedException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestBodyTooLargeException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;
use DreaM1\EfakturaConnector\Core\Exceptions\TokenRequestException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\InvalidXmlException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\SchemaValidationException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\UblValidationException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\UnsupportedDocumentTypeException;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblSchemaValidationIssue;
use DreaM1\EfakturaConnector\Support\HttpStatusCode;
use PHPUnit\Framework\TestCase;

/**
 * A failure is identified by its class and described by typed accessors; there are no reason codes.
 */
class ExceptionFamiliesTest extends TestCase
{
    /**
     * Verifies each exception class belongs to its documented failure family.
     *
     * @param EFakturaException $exception Exception instance to classify.
     * @param string            $family    Expected failure family identifier.
     * @dataProvider familyProvider
     */
    public function testEveryFailureBelongsToItsFamily(EFakturaException $exception, string $family): void
    {
        $this->assertInstanceOf($family, $exception);
        $this->assertNotSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    /**
     * Provides exception classes and expected failure-family identifiers.
     *
     * @return array<array-key, array<array-key, mixed>> Exception family cases.
     */
    public function familyProvider(): array
    {
        return [
            'connection'             => [new ProviderConnectionException('Synthetic failure.'), ProviderApiException::class],
            'error response'         => [new ProviderErrorResponseException(500), ProviderApiException::class],
            'invalid response'       => [new InvalidProviderResponseException('Bad.'), ProviderApiException::class],
            'malformed JSON'         => [InvalidProviderResponseException::malformedJson(), ProviderApiException::class],
            'malformed XML'          => [InvalidProviderResponseException::malformedXml(), ProviderApiException::class],
            'token'                  => [new TokenRequestException(), ProviderApiException::class],
            'encoding'               => [new RequestEncodingException(), EFakturaException::class],
            'request body too large' => [new RequestBodyTooLargeException(1024), EFakturaException::class],
            'request coordination'   => [new RequestCoordinationException(), EFakturaException::class],
            'configuration'          => [new ConfigurationException('Synthetic setup failure.'), EFakturaException::class],
            'recipient'              => [new RecipientUnsupportedException(), DocumentPreflightException::class],
            'provider rejection'     => [new DocumentRejectedByProviderException(), DocumentPreflightException::class],
            'unsafe XML'             => [InvalidXmlException::unsafe(), UblValidationException::class],
            'unsupported type'       => [new UnsupportedDocumentTypeException(), UblValidationException::class],
            'infrastructure'         => [new LocalValidationInfrastructureException('Java missing.'), EFakturaException::class],
        ];
    }

    public function testErrorResponseKeepsTheResponseAsReceived(): void
    {
        $exception = new ProviderErrorResponseException(401, '{"error":{}}', ['x-request-id' => 'req-1']);

        $this->assertSame(401, $exception->httpStatusCode()->value());
        $this->assertTrue($exception->httpStatusCode()->equals(HttpStatusCode::fromInt(401)));
        $this->assertSame('{"error":{}}', $exception->body());
        $this->assertSame(['x-request-id' => 'req-1'], $exception->responseHeaders());
    }

    public function testConnectionFailureKeepsReceivedHeaders(): void
    {
        $exception = new ProviderConnectionException('Synthetic timeout.', 504, ['x-request-id' => 'req-1']);

        $this->assertSame(504, $exception->httpStatusCode()->value());
        $this->assertSame(['x-request-id' => 'req-1'], $exception->responseHeaders());
    }

    public function testInvalidStatusIsPreservedRawButHasNoTypedValue(): void
    {
        $exception = new ProviderConnectionException('Synthetic response.', 600);

        $this->assertSame(600, $exception->rawStatusCode());
        $this->assertNull($exception->httpStatusCode());
    }

    public function testTokenFailureKeepsStatusAndCause(): void
    {
        $cause = new ProviderErrorResponseException(401, '', ['x-request-id' => 'req-1']);

        $exception = new TokenRequestException($cause);

        $this->assertSame(401, $exception->httpStatusCode()->value());
        $this->assertSame(['x-request-id' => 'req-1'], $exception->responseHeaders());
        $this->assertSame($cause, $exception->getPrevious());
    }

    public function testInvalidResponseNamesTheInvalidField(): void
    {
        $exception = new InvalidProviderResponseException('Bad.', 'SapiTokenResponse', 'expires_in');

        $this->assertSame('SapiTokenResponse', $exception->schema());
        $this->assertSame('expires_in', $exception->field());
        $this->assertNull(InvalidProviderResponseException::malformedJson()->field());
    }

    public function testRejectedDocumentIsNotAProviderFailure(): void
    {
        $this->assertNotInstanceOf(ProviderApiException::class, new DocumentRejectedByProviderException());
        $this->assertNotInstanceOf(ProviderApiException::class, new RecipientUnsupportedException());
    }

    public function testInvalidXmlTellsWhetherItWasUnsafe(): void
    {
        $this->assertTrue(InvalidXmlException::unsafe()->isUnsafe());
        $this->assertFalse(InvalidXmlException::malformed()->isUnsafe());
    }

    public function testSchemaValidationFailureCarriesItsIssues(): void
    {
        $schemaIssue = new UblSchemaValidationIssue(3, 7, 'Synthetic XSD failure.');

        $schema = new SchemaValidationException('2.1', [$schemaIssue], 2);

        $this->assertSame('2.1', $schema->version());
        $this->assertSame([$schemaIssue], $schema->issues());
        $this->assertSame(2, $schema->omittedCount());
    }
}
