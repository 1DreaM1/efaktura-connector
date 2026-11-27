<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Http\Protocol;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaders;
use PHPUnit\Framework\TestCase;

/** Tests the generic immutable HTTP header collection. */
class HttpHeadersTest extends TestCase
{
    public function testAddedHeadersPreserveInsertionOrder(): void
    {
        $headers = HttpHeaders::create()
            ->withAddedHeader(HttpHeaderName::fromString('X-First'), 'one')
            ->withAddedHeader(HttpHeaderName::fromString('X-Repeatable'), 'first')
            ->withAddedHeader(HttpHeaderName::fromString('X-Last'), 'three')
            ->withAddedHeader(HttpHeaderName::fromString('X-Repeatable'), 'second');

        $this->assertSame(
            [
                'X-First: one',
                'X-Repeatable: first',
                'X-Last: three',
                'X-Repeatable: second',
            ],
            $this->headerValues($headers->all())
        );
    }

    public function testAllNamedReturnsMatchingHeadersCaseInsensitivelyInOriginalOrder(): void
    {
        $headers = HttpHeaders::create()
            ->withAddedHeader(HttpHeaderName::fromString('X-First'), 'one')
            ->withAddedHeader(HttpHeaderName::fromString('X-Repeatable'), 'first')
            ->withAddedHeader(HttpHeaderName::fromString('x-repeatable'), 'second')
            ->withAddedHeader(HttpHeaderName::fromString('X-Last'), 'three');

        $this->assertSame(
            [
                'X-Repeatable: first',
                'x-repeatable: second',
            ],
            $this->headerValues($headers->allNamed(HttpHeaderName::fromString('X-REPEATABLE')))
        );
    }

    public function testAllNamedReturnsEmptyArrayWhenNoHeaderMatches(): void
    {
        $headers = HttpHeaders::create()
            ->withAddedHeader(HttpHeaderName::fromString('X-First'), 'one');

        $this->assertSame([], $headers->allNamed(HttpHeaderName::fromString('X-Missing')));
    }

    public function testWithHeaderReplacesAllMatchingNamesCaseInsensitivelyInPlace(): void
    {
        $headers = HttpHeaders::create()
            ->withAddedHeader(HttpHeaderName::fromString('X-First'), 'one')
            ->withAddedHeader(HttpHeaderName::fromString('X-Value'), 'old-first')
            ->withAddedHeader(HttpHeaderName::fromString('X-Last'), 'three')
            ->withAddedHeader(HttpHeaderName::fromString('x-value'), 'old-second')
            ->withHeader(HttpHeaderName::fromString('X-VALUE'), 'new')
            ->withAddedHeader(HttpHeaderName::fromString('X-After'), 'four');

        $this->assertSame(
            [
                'X-First: one',
                'X-VALUE: new',
                'X-Last: three',
                'X-After: four',
            ],
            $this->headerValues($headers->all())
        );
    }

    public function testCollectionDoesNotApplyApiRequestSpecificHeaderRules(): void
    {
        $headers = HttpHeaders::create()
            ->withAddedHeader(HttpHeaderName::idempotencyKey(), 'first')
            ->withAddedHeader(HttpHeaderName::fromString('idempotency-key'), 'second')
            ->withAddedHeader(HttpHeaderName::peppolParticipantId(), '0245:first')
            ->withAddedHeader(HttpHeaderName::fromString('x-peppol-participant-id'), '0245:second');

        $this->assertCount(4, $headers->all());
    }

    /**
     * @param  HttpHeaderLine[] $headers Ordered header lines to convert to their serialized values.
     * @return string[]         Header values in the same insertion order.
     */
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
