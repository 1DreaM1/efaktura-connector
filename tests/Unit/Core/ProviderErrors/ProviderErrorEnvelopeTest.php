<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\ProviderErrors;

use DreaM1\EfakturaConnector\Core\ProviderErrors\ProviderErrorCategory;
use DreaM1\EfakturaConnector\Core\ProviderErrors\ProviderErrorEnvelope;
use PHPUnit\Framework\TestCase;
use stdClass;

/** Covers tolerant provider-error parsing, preservation of unknown fields and invalid envelope values. */
class ProviderErrorEnvelopeTest extends TestCase
{
    public function testReadsKnownAndUnknownProviderErrorFieldsTolerantly(): void
    {
        $data = [
            'category' => 'VALIDATION',
            'code'     => 422,
            'message'  => 'Invalid document',
            'details'  => [
                ['field' => 'amount', 'issue' => 'must be positive', 'value' => -1],
                ['field' => false, 'issue' => null, 'value' => ['unexpected']],
                'malformed detail',
            ],
            'retryable'          => false,
            'correlation_id'     => 12345,
            'provider_extension' => ['preserved' => true],
        ];
        $body = ProviderErrorEnvelope::fromArray(['error' => $data]);

        $this->assertNotNull($body);
        $this->assertSame('VALIDATION', $body->category()->value());
        $this->assertTrue($body->category()->isKnown());
        $this->assertSame('422', $body->code()->value());
        $this->assertSame('Invalid document', $body->message());
        $this->assertFalse($body->retryable());
        $this->assertSame('12345', $body->correlationId());
        $this->assertSame($data, $body->toArray());
        $this->assertCount(2, $body->details());
        $this->assertSame('amount', $body->details()[0]->field());
        $this->assertSame('must be positive', $body->details()[0]->issue());
        $this->assertSame('-1', $body->details()[0]->value());
        $this->assertSame(
            ['field' => 'amount', 'issue' => 'must be positive', 'value' => -1],
            $body->details()[0]->toArray()
        );
        $this->assertSame('', $body->details()[1]->field());
        $this->assertNull($body->details()[1]->issue());
        $this->assertNull($body->details()[1]->value());
    }

    public function testPreservesUnknownCategoryValuesWithoutClassifyingThemAsKnown(): void
    {
        $data = ['category' => 'FUTURE_PROVIDER_CATEGORY', 'message' => 'Synthetic failure'];
        $body = ProviderErrorEnvelope::fromArray(['error' => $data]);

        $this->assertNotNull($body);
        $this->assertInstanceOf(ProviderErrorCategory::class, $body->category());
        $this->assertSame('FUTURE_PROVIDER_CATEGORY', $body->category()->value());
        $this->assertFalse($body->category()->isKnown());
        $this->assertSame($data, $body->toArray());
    }

    public function testRejectsInvalidArrayEnvelopes(): void
    {
        $this->assertNull(ProviderErrorEnvelope::fromArray([]));
        $this->assertNull(ProviderErrorEnvelope::fromArray(['error' => 'unavailable']));
        $this->assertNull(ProviderErrorEnvelope::fromArray(['error' => ['message' => ['invalid']]]));
    }

    public function testInvalidOptionalValuesRemainNullInsteadOfBeingCoerced(): void
    {
        $body = ProviderErrorEnvelope::fromArray(['error' => [
            'message'        => '',
            'category'       => ['invalid'],
            'code'           => null,
            'retryable'      => 'yes',
            'correlation_id' => new stdClass(),
            'details'        => 'not-a-list',
        ]]);

        $this->assertNotNull($body);
        $this->assertSame('', $body->message());
        $this->assertNull($body->category());
        $this->assertNull($body->code());
        $this->assertNull($body->retryable());
        $this->assertNull($body->correlationId());
        $this->assertSame([], $body->details());
    }
}
