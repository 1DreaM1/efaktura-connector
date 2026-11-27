<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\Documents\SentDocumentStatus;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\SentDocumentStatusFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use DreaM1\EfakturaConnector\Tests\Support\RecordingTransport;
use DreaM1\EfakturaConnector\Tests\Support\TestConfiguration;
use PHPUnit\Framework\TestCase;
use stdClass;

/** Covers JSON and XML response mapping, provider errors and response-context preservation. */
class ApiConnectorTest extends TestCase
{
    public function testAddsParticipantHeaderOnlyToParticipantScopedRequests(): void
    {
        $transport = RecordingTransport::returning(ApiFixtures::jsonExchange(['ok' => true]));
        $connector = $this->connector($transport);

        $connector->json(
            ApiRequest::get('/v1/public')->withHeader(HttpHeaderName::fromString('X-Test'), 'synthetic'),
            $this->mapper()
        );
        $connector->json(
            ApiRequest::get('/v1/scoped')->withHeader(HttpHeaderName::fromString('X-Test'), 'synthetic')
                ->forParticipant(TestConfiguration::participant()),
            $this->mapper()
        );

        [$public, $scoped] = $transport->requests();
        $this->assertSame(['X-Test: synthetic'], $this->headerValues($public->headers()->all()));
        $this->assertSame(
            ['X-Test: synthetic', 'X-Peppol-Participant-Id: 0245:2012345676'],
            $this->headerValues($scoped->headers()->all())
        );
    }

    public function testJsonMapsDecodedObject(): void
    {
        $connector = $this->connector(RecordingTransport::returning(ApiFixtures::jsonExchange(['webhooks' => []])));

        $this->assertSame(['webhooks' => []], (array) $connector->json(
            ApiRequest::get('/v1/webhooks'),
            $this->mapper()
        ));
    }

    public function testJsonPreservesTheDifferenceBetweenEmptyObjectsAndLists(): void
    {
        $connector = $this->connector(RecordingTransport::returning(
            new HttpExchangeResult(200, '{"object":{},"named":{"0":"value"},"list":[]}', ['content-type' => 'application/json'], microtime(true))
        ));

        $response = $connector->json(ApiRequest::get('/v1/example'), static function (array $data): object {
            return (object) $data;
        });

        $this->assertInstanceOf(stdClass::class, $response->object);
        $this->assertInstanceOf(stdClass::class, $response->named);
        $this->assertSame([], $response->list);
    }

    public function testJsonAddsResponseContextWhenTheMapperRejectsTheResponse(): void
    {
        $connector = $this->connector(RecordingTransport::returning(
            new HttpExchangeResult(200, '{"access_token":42}', ['x-request-id' => 'req-1'], microtime(true))
        ));

        try {
            $connector->json(
                ApiRequest::get('/sapi/auth/token/status'),
                static function (array $data): object {
                    throw new InvalidProviderResponseException(
                        'API response field has an invalid value.',
                        'SapiTokenResponse',
                        'access_token'
                    );
                }
            );
            $this->fail('Expected the mapper to reject the provider response.');
        } catch (InvalidProviderResponseException $exception) {
            $this->assertSame(200, $exception->httpStatusCode()->value());
            $this->assertSame(['x-request-id' => 'req-1'], $exception->responseHeaders());
            $this->assertSame('SapiTokenResponse', $exception->schema());
            $this->assertSame('access_token', $exception->field());
        }
    }

    public function testSentDocumentMapperRejectsBlankOptionalDocumentIdWithResponseContext(): void
    {
        $connector = $this->connector(RecordingTransport::returning(ApiFixtures::jsonExchange([
            'documentId' => '',
        ])));

        try {
            $connector->json(
                ApiRequest::get('/v1/sent-documents/synthetic'),
                static function (array $data): SentDocumentStatus {
                    return SentDocumentStatusFactory::fromArray($data);
                }
            );
            $this->fail('A blank sent-document ID must be rejected as invalid provider data.');
        } catch (InvalidProviderResponseException $exception) {
            $this->assertSame(200, $exception->httpStatusCode()->value());
            $this->assertSame('SentDocumentResponse', $exception->schema());
            $this->assertSame('documentId', $exception->field());
        }
    }

    public function testJsonRejectsAnEmptyNoContentResponse(): void
    {
        $connector = $this->connector(RecordingTransport::returning(
            new HttpExchangeResult(204, '', [], microtime(true))
        ));

        try {
            $connector->json(
                ApiRequest::delete('/v1/webhooks/{id}', ['id' => 'synthetic']),
                $this->mapper()
            );
            $this->fail('A JSON endpoint must reject an empty successful response.');
        } catch (InvalidProviderResponseException $exception) {
            $this->assertSame(204, $exception->httpStatusCode()->value());
            $this->assertStringContainsString('missing', $exception->getMessage());
        }
    }

    /**
     * Rejects valid JSON roots that cannot represent an API response object.
     *
     * @param string $json JSON response body.
     * @dataProvider nonObjectJsonResponses
     */
    public function testJsonRejectsNonObjectRootValues(string $json): void
    {
        $connector = $this->connector(RecordingTransport::returning(
            new HttpExchangeResult(200, $json, ['content-type' => 'application/json'], microtime(true))
        ));

        $this->expectException(InvalidProviderResponseException::class);

        $connector->json(ApiRequest::get('/sapi/auth/token/status'), $this->mapper());
    }

    /**
     * Provides JSON response roots that cannot be mapped to provider objects.
     *
     * @return array<array-key, array<array-key, mixed>> Non-object JSON cases.
     */
    public function nonObjectJsonResponses(): array
    {
        return [
            'empty list'      => ['[]'],
            'list of objects' => ['[{}]'],
            'string scalar'   => ['"token"'],
            'null scalar'     => ['null'],
        ];
    }

    public function testSendAcceptsAnEmptyNoContentResponse(): void
    {
        $transport = RecordingTransport::returning(new HttpExchangeResult(204, '', [], microtime(true)));

        $this->connector($transport)->send(ApiRequest::delete('/v1/webhooks/{id}', ['id' => 'synthetic']));

        $this->assertSame('/v1/webhooks/synthetic', $transport->lastRequest()->path());
    }

    public function testJsonRejectsMalformedSuccessfulResponse(): void
    {
        $connector = $this->connector(RecordingTransport::returning(
            new HttpExchangeResult(200, 'not-json', [], microtime(true))
        ));

        $this->expectException(ProviderApiException::class);

        $connector->json(ApiRequest::get('/sapi/auth/token/status'), $this->mapper());
    }

    public function testXmlReturnsBodyForXmlContentType(): void
    {
        $xml       = '<ApplicationResponse />';
        $connector = $this->connector(RecordingTransport::returning(
            new HttpExchangeResult(200, $xml, ['content-type' => 'application/xml; charset=utf-8'], microtime(true))
        ));

        $this->assertSame($xml, $connector->xml(ApiRequest::get('/v1/mls/content')->acceptXml()));
    }

    public function testXmlAcceptsCaseInsensitiveContentTypeHeaderName(): void
    {
        $xml       = '<ApplicationResponse />';
        $connector = $this->connector(RecordingTransport::returning(
            new HttpExchangeResult(200, $xml, ['Content-Type' => 'Application/XML; charset=utf-8'], microtime(true))
        ));

        $this->assertSame($xml, $connector->xml(ApiRequest::get('/v1/mls/content')->acceptXml()));
    }

    public function testXmlRejectsNonXmlContentType(): void
    {
        $connector = $this->connector(RecordingTransport::returning(ApiFixtures::jsonExchange(['xml' => false])));

        $this->expectException(ProviderApiException::class);

        $connector->xml(ApiRequest::get('/v1/mls/content')->acceptXml());
    }

    /**
     * Rejects empty, malformed and unsafe XML provider responses.
     *
     * @param string $xml XML response body under test.
     * @dataProvider invalidXmlResponseProvider
     */
    public function testXmlRejectsEmptyMalformedAndDtdResponses(string $xml): void
    {
        $connector = $this->connector(RecordingTransport::returning(ApiFixtures::xmlExchange($xml)));

        $this->expectException(ProviderApiException::class);

        $connector->xml(ApiRequest::get('/v1/mls/content')->acceptXml());
    }

    /**
     * Provides empty, malformed and unsafe XML responses.
     *
     * @return array<array-key, array<array-key, mixed>> Invalid XML response cases.
     */
    public function invalidXmlResponseProvider(): array
    {
        return [
            'empty XML'     => [''],
            'malformed XML' => ['<ApplicationResponse>'],
            'DTD XML'       => ['<!DOCTYPE root [<!ENTITY x "unsafe">]><root>&x;</root>'],
        ];
    }

    public function testXmlReportsProviderErrorBeforeCheckingContentType(): void
    {
        $connector = $this->connector(RecordingTransport::returning(
            ApiFixtures::jsonExchange(ApiFixtures::error('Synthetic document is unavailable'), 404)
        ));

        try {
            $connector->xml(ApiRequest::get('/v1/mls/content')->acceptXml());
            $this->fail('Provider HTTP errors must be reported before the XML content check.');
        } catch (ProviderApiException $exception) {
            $this->assertSame(404, $exception->httpStatusCode()->value());
            $this->assertInstanceOf(ProviderErrorResponseException::class, $exception);
        }
    }

    public function testHttpErrorReturnsStatusWithoutParsingProviderBody(): void
    {
        $connector = $this->connector(RecordingTransport::returning(
            ApiFixtures::jsonExchange(ApiFixtures::error('Synthetic token expired', [], 'AUTH'), 401)
        ));

        try {
            $connector->json(ApiRequest::get('/sapi/auth/token/status'), $this->mapper());
            $this->fail('HTTP errors must be reported to the caller.');
        } catch (ProviderApiException $exception) {
            $this->assertSame(401, $exception->httpStatusCode()->value());
            $this->assertInstanceOf(ProviderErrorResponseException::class, $exception);
        }
    }

    public function testHttpErrorIgnoresResponseBody(): void
    {
        $connector = $this->connector(RecordingTransport::returning(
            ApiFixtures::jsonExchange(ApiFixtures::error('Synthetic provider detail'), 502)
        ));

        try {
            $connector->send(ApiRequest::post('/v1/test'));
            $this->fail('HTTP errors must be reported to the caller.');
        } catch (ProviderApiException $exception) {
            $this->assertSame(502, $exception->httpStatusCode()->value());
            $this->assertInstanceOf(ProviderErrorResponseException::class, $exception);
        }
    }

    public function testHttpErrorWithUnparseableBodyStillReturnsTypedProviderException(): void
    {
        $connector = $this->connector(RecordingTransport::returning(
            new HttpExchangeResult(502, 'Synthetic upstream failure', [], microtime(true))
        ));

        try {
            $connector->json(ApiRequest::get('/v1/test'), $this->mapper());
            $this->fail('HTTP errors must be reported even if the provider body is not JSON.');
        } catch (ProviderApiException $exception) {
            $this->assertSame(502, $exception->httpStatusCode()->value());
            $this->assertInstanceOf(ProviderErrorResponseException::class, $exception);
        }
    }

    /** @return callable(array<array-key, mixed>): object Simple response mapper used by connector tests. */
    private function mapper(): callable
    {
        return static function (array $data): object {
            return (object) $data;
        };
    }

    private function connector(RecordingTransport $transport): ApiConnector
    {
        return new ApiConnector($transport);
    }

    /** @param HttpHeaderLine[] $headers */
    private function headerValues(array $headers): array
    {
        return array_map(
            static function (HttpHeaderLine $header): string {
                return $header->toString();
            },
            $headers
        );
    }
}
