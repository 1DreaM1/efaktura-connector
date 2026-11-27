<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Serialization;

use DreaM1\EfakturaConnector\Core\Connection\CheckReceivingCapabilityRequest;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization\DiscoveryRequestMapper;
use PHPUnit\Framework\TestCase;

/** The receiving-capability check is mapped to the DiscoveryReceivesRequest wire shape. */
final class DiscoveryRequestMapperTest extends TestCase
{
    public function testOmitsAbsentProcessId(): void
    {
        $body = DiscoveryRequestMapper::receivesBody(new CheckReceivingCapabilityRequest(
            Participant::parse('0245:receiver'),
            PeppolDocumentTypeIdentifier::fromString('doc-type')
        ));

        $this->assertSame(['participantId' => '0245:receiver', 'documentTypeId' => 'doc-type'], $body);
    }

    public function testIncludesProcessIdWhenSupplied(): void
    {
        $body = DiscoveryRequestMapper::receivesBody(new CheckReceivingCapabilityRequest(
            Participant::parse('0245:receiver'),
            PeppolDocumentTypeIdentifier::fromString('doc-type'),
            PeppolProcessIdentifier::fromString('process-1')
        ));

        $this->assertSame(
            ['participantId' => '0245:receiver', 'documentTypeId' => 'doc-type', 'processId' => 'process-1'],
            $body
        );
    }
}
