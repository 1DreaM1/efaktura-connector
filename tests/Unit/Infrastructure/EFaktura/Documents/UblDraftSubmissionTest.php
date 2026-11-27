<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Documents;

use DreaM1\EfakturaConnector\Core\Contracts\DocumentSubmissionGatewayInterface;
use DreaM1\EfakturaConnector\Core\Contracts\UblDraftSubmissionInterface;
use DreaM1\EfakturaConnector\Core\Documents\DocumentSubmissionResult;
use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use DreaM1\EfakturaConnector\Core\Documents\OutboundDocumentPayload;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentRequest;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblDocumentWriterInterface;
use DreaM1\EfakturaConnector\Core\Ubl\PeppolDocumentTypeIdentifierBuilder;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolCustomizationIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\CountryCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\CurrencyCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDecimal;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentDraft;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentNumber;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftParty;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftTotals;
use DreaM1\EfakturaConnector\Domain\Ubl\LocalDate;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentRoot;
use DreaM1\EfakturaConnector\Domain\Ubl\UblEndpointIdentifier;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\UblDraftSubmission;
use DreaM1\EfakturaConnector\Support\Timestamp;
use PHPUnit\Framework\TestCase;

/** Tests UBL serialization and submission service behavior. */
class UblDraftSubmissionTest extends TestCase
{
    public function testPreparePayloadMapsDraftMetadataAndChecksumsWriterXml(): void
    {
        $draft  = $this->draft();
        $xml    = '<CreditNote>synthetic</CreditNote>';
        $writer = $this->createMock(UblDocumentWriterInterface::class);
        $writer->expects($this->once())
            ->method('write')
            ->with($this->identicalTo($draft))
            ->willReturn($xml);
        $gateway = $this->createMock(DocumentSubmissionGatewayInterface::class);
        $gateway->expects($this->never())->method('send');
        $sender   = Participant::parse('0245:transport-sender');
        $receiver = Participant::parse('0088:transport-receiver');
        $service  = new UblDraftSubmission($writer, $gateway);

        $this->assertInstanceOf(UblDraftSubmissionInterface::class, $service);

        $payload = $service->preparePayload(
            $sender,
            $receiver,
            $draft,
            Timestamp::fromIso8601('2026-11-21T12:30:00Z')
        );

        $this->assertSame('CN-2026-001', $payload->documentId()->value());
        $this->assertSame(
            PeppolDocumentTypeIdentifierBuilder::forDocument(
                UblDocumentRoot::CREDIT_NOTE,
                'urn:example:billing'
            )->value(),
            $payload->documentTypeId()->value()
        );
        $this->assertSame('urn:example:process', $payload->processId()->value());
        $this->assertSame('0245:transport-sender', $payload->sender()->identifier());
        $this->assertSame('0088:transport-receiver', $payload->receiver()->identifier());
        $this->assertSame('2026-11-21T12:30:00Z', $payload->creationDateTime()->value());
        $this->assertSame($xml, $payload->xml());
        $this->assertSame(hash('sha256', $xml), $payload->checksum()->value());
    }

    public function testSendPassesPreparedPayloadAndStableKeyToExistingGateway(): void
    {
        $draft  = $this->draft();
        $xml    = '<CreditNote>synthetic</CreditNote>';
        $result = new DocumentSubmissionResult(
            ProviderDocumentId::fromString('provider-document-1'),
            ProviderDocumentStatus::fromString(ProviderDocumentStatus::ACCEPTED),
            Timestamp::fromIso8601('2026-11-21T12:31:00Z'),
            Timestamp::fromIso8601('2026-11-21T12:31:01Z')
        );
        $writer = $this->createMock(UblDocumentWriterInterface::class);
        $writer->expects($this->once())->method('write')->with($this->identicalTo($draft))->willReturn($xml);
        $gateway  = $this->createMock(DocumentSubmissionGatewayInterface::class);
        $sender   = Participant::parse('0245:transport-sender');
        $receiver = Participant::parse('0088:transport-receiver');
        $gateway->expects($this->once())
            ->method('send')
            ->with(
                $this->callback(static function ($request) use ($xml, $sender): bool {
                    if (! $request instanceof SendDocumentRequest) {
                        return false;
                    }

                    $payload = $request->payload();

                    return $request->participant()->equals($sender)
                        && $request->idempotencyKey()->value() === '00000000-0000-4000-8000-000000000001'
                        && $payload->sender()->identifier() === '0245:transport-sender'
                        && $payload->receiver()->identifier() === '0088:transport-receiver'
                        && $payload->xml() === $xml
                        && $payload->creationDateTime()->value() === '2026-11-21T12:30:00Z';
                }),
            )
            ->willReturn($result);

        $actual = (new UblDraftSubmission($writer, $gateway))->send(
            $sender,
            $receiver,
            $draft,
            Timestamp::fromIso8601('2026-11-21T12:30:00Z'),
            IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001')
        );

        $this->assertSame($result, $actual);
    }

    public function testSendPropagatesProviderFailureFromGatewayUnchanged(): void
    {
        $draft  = $this->draft();
        $writer = $this->createMock(UblDocumentWriterInterface::class);
        $writer->expects($this->once())->method('write')->with($this->identicalTo($draft))
            ->willReturn('<CreditNote>synthetic</CreditNote>');

        $failure = new ProviderConnectionException('Synthetic connection failure.');
        $gateway = $this->createMock(DocumentSubmissionGatewayInterface::class);
        $gateway->expects($this->once())->method('send')->willThrowException($failure);

        try {
            (new UblDraftSubmission($writer, $gateway))->send(
                Participant::parse('0245:transport-sender'),
                Participant::parse('0088:transport-receiver'),
                $draft,
                Timestamp::fromIso8601('2026-11-21T12:30:00Z'),
                IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001')
            );
            $this->fail('The draft submission service must preserve provider failures from its gateway.');
        } catch (ProviderApiException $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    private function draft(): UblDocumentDraft
    {
        $supplier = new UblDraftParty(
            UblEndpointIdentifier::fromSchemeAndValue('0245', '1234567890'),
            'Supplier Ltd',
            CountryCode::fromCanonical('SK')
        );
        $customer = new UblDraftParty(
            UblEndpointIdentifier::fromSchemeAndValue('0245', '9876543210'),
            'Customer Ltd',
            CountryCode::fromCanonical('SK')
        );
        $totals = new UblDraftTotals(
            UblDecimal::fromString('100.00'),
            UblDecimal::fromString('100.00'),
            UblDecimal::fromString('120.00'),
            null,
            UblDecimal::fromString('120.00'),
            UblDecimal::fromString('20.00'),
            []
        );

        return (new UblDocumentDraft(
            UblDocumentRoot::fromString(UblDocumentRoot::CREDIT_NOTE),
            UblDocumentNumber::fromString('CN-2026-001'),
            LocalDate::fromString('2026-11-21'),
            CurrencyCode::fromCanonical('EUR'),
            $supplier,
            $customer,
            $totals
        ))->withSpecification(
            PeppolCustomizationIdentifier::fromString('urn:example:billing'),
            PeppolProcessIdentifier::fromString('urn:example:process')
        );
    }
}
