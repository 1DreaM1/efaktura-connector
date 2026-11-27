<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\Documents\DocumentRequestContext;
use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\HttpRequestContext;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\HttpRequestTarget;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaders;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\MediaType;
use DreaM1\EfakturaConnector\Infrastructure\Xml\SecureXmlDocumentParser;
use DreaM1\EfakturaConnector\Tests\Support\TestConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TypeError;

/** Covers immutable request builders, encoded paths, typed headers, participant scope and retry safety. */
class ApiRequestTest extends TestCase
{
    public function testHttpRequestsExposeOnlyTypedMethodAccessors(): void
    {
        $this->assertTrue(method_exists(ApiRequest::class, 'httpMethod'));
        $this->assertFalse(method_exists(ApiRequest::class, 'method'));
        $this->assertTrue(method_exists(HttpExchangeRequest::class, 'httpMethod'));
        $this->assertFalse(method_exists(HttpExchangeRequest::class, 'method'));
        $this->assertFalse(method_exists(HttpExchangeRequest::class, 'fromMethod'));
        $this->assertFalse(method_exists(ApiRequest::class, 'headerLines'));
        $this->assertSame(
            HttpHeaders::class,
            (new ReflectionMethod(ApiRequest::class, 'headers'))->getReturnType()->getName()
        );
        $this->assertSame(
            HttpHeaders::class,
            (new ReflectionMethod(HttpExchangeRequest::class, 'headers'))->getReturnType()->getName()
        );
        $this->assertFalse((new ReflectionMethod(HttpExchangeRequest::class, 'withHeaders'))->isPublic());
        $this->assertFalse(method_exists(HttpExchangeRequest::class, 'url'));
        $this->assertFalse(method_exists(HttpExchangeRequest::class, 'withUrl'));
        $this->assertFalse(method_exists(HttpHeaderLine::class, 'fromLine'));
        $this->assertFalse(method_exists(HttpHeaderLine::class, 'assertValidLine'));
        $this->assertFalse(class_exists(SecureXmlDocumentParser::class));
    }

    public function testDefaultsToJsonAndRetrySafetyOnlyForGet(): void
    {
        $get  = ApiRequest::get('/v1/test');
        $post = ApiRequest::post('/v1/test');

        $this->assertSame('GET', $get->httpMethod()->value());
        $this->assertSame(MediaType::APPLICATION_JSON, $get->accept());
        $this->assertTrue($get->isRetrySafe());
        $this->assertFalse($post->isRetrySafe());
        $this->assertTrue($post->retrySafe()->isRetrySafe());
        $this->assertFalse($get->retrySafe(false)->isRetrySafe());
        $this->assertNull($get->participant());
        $this->assertNull($get->body());
    }

    public function testPathParametersAreUrlEncoded(): void
    {
        $request = ApiRequest::get('/v1/sent/{id}/status/{statusId}', ['id' => 'a/b c', 'statusId' => '0245:1']);

        $this->assertSame('/v1/sent/a%2Fb%20c/status/0245%3A1', $request->path());
    }

    /**
     * Rejects missing or blank required path parameter values.
     *
     * @param array<string, mixed> $parameters Path parameters supplied to the request.
     * @dataProvider invalidPathParameterProvider
     */
    public function testRejectsMissingOrBlankPathParameters(array $parameters): void
    {
        $this->expectException(InvalidArgumentException::class);

        ApiRequest::delete('/v1/webhooks/{webhookId}', $parameters);
    }

    /**
     * Provides path parameter values that request construction must reject.
     *
     * @return array<array-key, array<array-key, mixed>> Invalid parameter cases.
     */
    public function invalidPathParameterProvider(): array
    {
        return [
            'missing' => [[]],
            'empty'   => [['webhookId' => '']],
            'blank'   => [['webhookId' => '  ']],
            'array'   => [['webhookId' => ['nested']]],
            'boolean' => [['webhookId' => true]],
            'float'   => [['webhookId' => 1.5]],
        ];
    }

    public function testQueryOmitsNullValuesAndUsesRfc3986Encoding(): void
    {
        $request = ApiRequest::get('/v1/webhooks')
            ->withQuery(['limit' => 10, 'status' => null])
            ->withQuery(['pageToken' => 'next page']);

        $this->assertSame('/v1/webhooks', $request->path());
        $this->assertSame(
            '/v1/webhooks?limit=10&pageToken=next%20page',
            $request->requestTarget()
        );
        $this->assertSame(
            '/v1/webhooks',
            ApiRequest::get('/v1/webhooks')->withQuery(['pageToken' => null])->requestTarget()
        );
    }

    /**
     * Rejects query values outside the documented scalar contract.
     *
     * @param array<string, mixed> $query Query values that must be rejected.
     * @dataProvider invalidQueryValueProvider
     */
    public function testQueryRejectsUnsupportedValueTypes(array $query): void
    {
        $this->expectException(InvalidArgumentException::class);

        ApiRequest::get('/v1/test')->withQuery($query);
    }

    /** @return array<array-key, array<array-key, mixed>> */
    public function invalidQueryValueProvider(): array
    {
        return [
            'boolean' => [['enabled' => true]],
            'float'   => [['limit' => 1.5]],
            'array'   => [['pageToken' => ['nested']]],
            'object'  => [['filter' => (object) ['status' => 'READY']]],
        ];
    }

    public function testQueryPreservesZeroAndEmptyStringValues(): void
    {
        $path = ApiRequest::get('/v1/test')
            ->withQuery(['limit' => 0, 'pageToken' => ''])
            ->requestTarget();

        $this->assertSame('/v1/test?limit=0&pageToken=', $path);
    }

    public function testBuildersAreImmutable(): void
    {
        $base       = ApiRequest::post('/v1/test');
        $configured = $base
            ->withIdempotencyKey(IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001'))
            ->withJsonBody(['a' => 1])
            ->acceptXml()
            ->forParticipant(TestConfiguration::participant('0245:1'))
            ->withContext(new DocumentRequestContext('doc-1', null));

        $this->assertSame([], $base->headers()->all());
        $this->assertNull($base->body());
        $this->assertSame(
            ['Idempotency-Key: 00000000-0000-4000-8000-000000000001', 'X-Peppol-Participant-Id: 0245:1'],
            $this->headerValues($configured->headers()->all())
        );
        $this->assertSame('{"a":1}', $configured->body());
        $this->assertSame(MediaType::APPLICATION_XML, $configured->accept());
        $this->assertSame('0245:1', $configured->participant()->identifier());
        $this->assertSame((new DocumentRequestContext('doc-1', null))->toArray(), $configured->context());
    }

    public function testForParticipantReplacesEarlierParticipantHeaders(): void
    {
        $first = ApiRequest::get('/v1/test')
            ->forParticipant(TestConfiguration::participant('0245:first'));
        $second = $first
            ->withHeader(HttpHeaderName::fromString('x-peppol-participant-id'), '0245:stale')
            ->forParticipant(TestConfiguration::participant('0245:second'));

        $this->assertSame(['X-Peppol-Participant-Id: 0245:first'], $this->headerValues($first->headers()->all()));
        $this->assertSame(['X-Peppol-Participant-Id: 0245:second'], $this->headerValues($second->headers()->all()));
        $this->assertSame('0245:second', $second->participant()->identifier());
    }

    public function testParticipantHeaderAddedAfterParticipantReplacesTheScopedIdentity(): void
    {
        $request = ApiRequest::get('/v1/test')
            ->forParticipant(TestConfiguration::participant('0245:first'))
            ->withHeader(HttpHeaderName::fromString('x-peppol-participant-id'), '0245:second');

        $this->assertSame(['X-Peppol-Participant-Id: 0245:second'], $this->headerValues($request->headers()->all()));
        $this->assertSame('0245:second', $request->participant()->identifier());
    }

    public function testRawParticipantHeaderIsParsedAndValidated(): void
    {
        $request = ApiRequest::get('/v1/test')->withHeader(
            HttpHeaderName::peppolParticipantId(),
            '0245:first'
        );

        $this->assertSame(['X-Peppol-Participant-Id: 0245:first'], $this->headerValues($request->headers()->all()));
        $this->assertSame('0245:first', $request->participant()->identifier());
    }

    public function testRawParticipantHeaderRejectsRemovedScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ApiRequest::get('/v1/test')->withHeader(
            HttpHeaderName::peppolParticipantId(),
            '0037:sample'
        );
    }

    public function testParticipantHeaderCannotBeAdded(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ApiRequest::get('/v1/test')->withAddedHeader(
            HttpHeaderName::peppolParticipantId(),
            '0245:participant'
        );
    }

    public function testSecondIdempotencyKeyCannotBeAddedCaseInsensitively(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ApiRequest::post('/v1/test')
            ->withHeader(HttpHeaderName::idempotencyKey(), 'first')
            ->withAddedHeader(HttpHeaderName::fromString('idempotency-key'), 'second');
    }

    public function testJsonBodyIsEncodedImmediately(): void
    {
        $this->expectException(RequestEncodingException::class);

        ApiRequest::post('/v1/test')->withJsonBody(['value' => "\xB1"]);
    }

    public function testRejectsHeaderValuesContainingLineBreaks(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ApiRequest::get('/v1/test')->withHeader(
            HttpHeaderName::fromString('X-Test'),
            "safe\r\nAuthorization: Bearer injected"
        );
    }

    public function testRejectsInvalidHeaderNames(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ApiRequest::get('/v1/test')->withHeader(
            HttpHeaderName::fromString("X-Test\r\nAuthorization"),
            'value'
        );
    }

    public function testTransportRequestRequiresTypedHeaders(): void
    {
        $this->expectException(TypeError::class);

        new HttpExchangeRequest(
            HttpMethod::fromString('GET'),
            HttpRequestTarget::fromResolvedTarget('/v1/test'),
            ['X-Test: safe'],
            null,
            HttpRequestContext::empty()
        );
    }

    public function testTypedTransportHeaderRejectsInjectedValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HttpHeaderLine::fromParts(
            HttpHeaderName::fromString('X-Test'),
            "safe\r\nAuthorization: Bearer injected"
        );
    }

    public function testApiRequestKeepsHeadersInOrder(): void
    {
        $request = ApiRequest::post('/v1/test')
            ->withIdempotencyKey(IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001'))
            ->withJsonBody(['a' => 1])
            ->forParticipant(TestConfiguration::participant('0245:1'));

        $this->assertSame('POST', $request->httpMethod()->value());
        $this->assertSame('/v1/test', $request->requestTarget());
        $this->assertSame(
            ['Idempotency-Key: 00000000-0000-4000-8000-000000000001', 'X-Peppol-Participant-Id: 0245:1'],
            $this->headerValues($request->headers()->all())
        );
        $this->assertSame('{"a":1}', $request->body());
        $this->assertFalse($request->isRetrySafe());
    }

    public function testParticipantHeaderRejectsRemovedScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ApiRequest::get('/v1/test')->forParticipant(Participant::parse('0037:sample'));
    }

    public function testParticipantHeaderRejectsDeprecatedSchemeForExistingOperation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ApiRequest::get('/v1/test')->forParticipant(Participant::parse('9909:sample'));
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
