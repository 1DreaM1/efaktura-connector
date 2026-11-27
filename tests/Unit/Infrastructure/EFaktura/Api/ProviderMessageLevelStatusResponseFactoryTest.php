<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\MessageStatuses\ProviderMessageLevelStatus;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\ProviderMessageLevelStatusResponseFactory;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use PHPUnit\Framework\TestCase;

/** Message-level status items are mapped straight to the public model, with omitted values left absent. */
final class ProviderMessageLevelStatusResponseFactoryTest extends TestCase
{
    public function testResultAndPartiesAreMapped(): void
    {
        $status = $this->read(ApiFixtures::messageLevelStatus('mls-7', 'RE', [
            'responseDescription' => 'Rejected',
            'reasons'             => [['lineId' => '1', 'code' => 'X', 'description' => 'Bad line']],
        ]));

        $this->assertSame('RE', $status->responseCode()->value());
        $this->assertSame('Rejected', $status->responseDescription());
        $this->assertCount(1, $status->reasons());
        $this->assertSame('Bad line', $status->reasons()[0]->description());
        $this->assertSame('0245:2012345699', $status->sender()->identifier());
        $this->assertSame('0245:2012345676', $status->receiver()->identifier());
        $this->assertSame('mls-7', $status->identifier()->value());
        $this->assertSame('msg-mls-7', $status->messageId()->value());
    }

    public function testAnItemWithoutOptionalValuesLeavesEveryValueAbsent(): void
    {
        $status = $this->read(['identifier' => 'mls-1']);

        $this->assertSame('mls-1', $status->identifier()->value());
        $this->assertNull($status->messageId());
        $this->assertNull($status->responseCode());
        $this->assertNull($status->responseDescription());
        $this->assertSame([], $status->reasons());
        $this->assertNull($status->sender());
        $this->assertNull($status->receiver());
        $this->assertNull($status->documentTypeId());
        $this->assertNull($status->receivedAt());
    }

    public function testAnOmittedItemsListMeansNoStatuses(): void
    {
        $response = ProviderMessageLevelStatusResponseFactory::fromArray([]);

        $this->assertTrue($response->isValid());
        $this->assertSame([], $response->items());
        $this->assertSame(0, $response->invalidItems());
    }

    /** @param array<string, mixed> $item */
    private function read(array $item): ProviderMessageLevelStatus
    {
        $response = ProviderMessageLevelStatusResponseFactory::fromArray(['items' => [$item]]);

        $this->assertSame(0, $response->invalidItems());
        $this->assertCount(1, $response->items());

        return $response->items()[0];
    }
}
