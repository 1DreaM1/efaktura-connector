<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi\ReceivedDocumentMetadataFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks mapping of required provider metadata and schema context for malformed fields. */
class ReceivedDocumentMetadataFactoryTest extends TestCase
{
    public function testMapsProviderFieldsIntoDomainMetadata(): void
    {
        $metadata = ReceivedDocumentMetadataFactory::fromData(new ProviderResponseReader([
            'documentId'            => 'document-1',
            'senderParticipantId'   => '0245:sender',
            'receiverParticipantId' => '0245:receiver',
            'documentTypeId'        => 'invoice-type',
            'processId'             => 'process-1',
            'creationDateTime'      => '2026-11-20T14:30:00Z',
        ], 'DocumentMetadata'));

        $this->assertSame('document-1', $metadata->documentId()->value());
        $this->assertSame('invoice-type', $metadata->documentTypeId()->value());
        $this->assertSame('process-1', $metadata->processId()->value());
        $this->assertSame('2026-11-20T14:30:00Z', $metadata->creationDateTime()->value());
    }

    public function testRejectsMalformedOptionalProviderFields(): void
    {
        $this->expectException(ProviderApiException::class);

        ReceivedDocumentMetadataFactory::fromData(new ProviderResponseReader([
            'documentId'            => 'document-1',
            'senderParticipantId'   => '0245:sender',
            'receiverParticipantId' => '0245:receiver',
            'processId'             => 'process-1',
            'creationDateTime'      => '2026-11-20T14:30:00Z',
            'documentTypeId'        => ['unexpected' => 'array'],
        ], 'DocumentMetadata'));
    }

    public function testRejectsMissingRequiredProviderFields(): void
    {
        try {
            ReceivedDocumentMetadataFactory::fromData(new ProviderResponseReader([
                'documentId'          => 'document-1',
                'senderParticipantId' => '0245:sender',
            ], 'DocumentMetadata'));
            $this->fail('Missing routing participant must be rejected.');
        } catch (InvalidProviderResponseException $exception) {
            $this->assertSame('DocumentMetadata', $exception->schema());
            $this->assertSame('receiverParticipantId', $exception->field());
        }
    }

    public function testReportsSchemaContextForMalformedParticipantIdentifiers(): void
    {
        try {
            ReceivedDocumentMetadataFactory::fromData(new ProviderResponseReader([
                'documentId'            => 'document-1',
                'senderParticipantId'   => 'invalid-participant',
                'receiverParticipantId' => '0245:receiver',
                'documentTypeId'        => 'invoice-type',
                'processId'             => 'process-1',
                'creationDateTime'      => '2026-11-20T14:30:00Z',
            ], 'DocumentMetadata'));
            $this->fail('A malformed sender participant must be rejected.');
        } catch (InvalidProviderResponseException $exception) {
            $this->assertSame('DocumentMetadata', $exception->schema());
            $this->assertSame('senderParticipantId', $exception->field());
            $this->assertInstanceOf(InvalidArgumentException::class, $exception->getPrevious());
        }
    }
}
