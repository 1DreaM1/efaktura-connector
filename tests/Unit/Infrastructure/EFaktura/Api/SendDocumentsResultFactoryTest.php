<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\Documents\SendDocumentsResult;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\SendDocumentsResultFactory;
use PHPUnit\Framework\TestCase;

/** The batch response maps straight to the public result and keeps its provider-response errors. */
final class SendDocumentsResultFactoryTest extends TestCase
{
    public function testMapsCountAndIdentifiers(): void
    {
        $result = SendDocumentsResultFactory::fromArray([
            'count'       => 2,
            'identifiers' => ['gateway-1', 'gateway-2'],
        ]);

        $this->assertInstanceOf(SendDocumentsResult::class, $result);
        $this->assertSame(2, $result->count());
        $this->assertSame('gateway-1', $result->identifiers()[0]->value());
        $this->assertSame('gateway-2', $result->identifiers()[1]->value());
    }

    public function testAnEmptyBatchIsAccepted(): void
    {
        $result = SendDocumentsResultFactory::fromArray(['count' => 0, 'identifiers' => []]);

        $this->assertSame(0, $result->count());
        $this->assertSame([], $result->identifiers());
    }

    /**
     * @param array<string, mixed> $data  Provider body that must be rejected.
     * @param string               $field Field the error is expected to name.
     * @dataProvider invalidBodyProvider
     */
    public function testInvalidBodiesRaiseProviderResponseErrorsForTheSchema(array $data, string $field): void
    {
        try {
            SendDocumentsResultFactory::fromArray($data);
            $this->fail('An invalid batch response must be rejected.');
        } catch (InvalidProviderResponseException $exception) {
            $this->assertSame('SendDocumentsResponse', $exception->schema());
            $this->assertSame($field, $exception->field());
        }
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public function invalidBodyProvider(): array
    {
        return [
            'negative count'      => [['count' => -1, 'identifiers' => []], 'count'],
            'count too high'      => [['count' => 2, 'identifiers' => ['gateway-1']], 'count'],
            'count too low'       => [['count' => 0, 'identifiers' => ['gateway-1']], 'count'],
            'missing count'       => [['identifiers' => []], 'count'],
            'missing identifiers' => [['count' => 0], 'identifiers'],
        ];
    }
}
