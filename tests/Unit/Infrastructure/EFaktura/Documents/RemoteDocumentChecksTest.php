<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Documents;

use DreaM1\EfakturaConnector\Core\Connection\CheckReceivingCapabilityRequest;
use DreaM1\EfakturaConnector\Core\Connection\CheckReceivingCapabilityResponse;
use DreaM1\EfakturaConnector\Core\Contracts\DocumentDiscoveryProviderInterface;
use DreaM1\EfakturaConnector\Core\Documents\OutboundDocumentPayload;
use DreaM1\EfakturaConnector\Core\Documents\Sha256Checksum;
use DreaM1\EfakturaConnector\Core\Documents\ValidateDocumentRequest;
use DreaM1\EfakturaConnector\Core\Exceptions\DocumentPreflightException;
use DreaM1\EfakturaConnector\Core\Exceptions\DocumentRejectedByProviderException;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\RecipientUnsupportedException;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\SubmittedDocumentId;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\ValidateDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\Checks\PeppolGatewayDocumentValidationCheck;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\Checks\RecipientCapabilityCheck;
use DreaM1\EfakturaConnector\Support\Timestamp;
use PHPUnit\Framework\TestCase;

/** Tests remote document validation and recipient capability checks. */
class RemoteDocumentChecksTest extends TestCase
{
    public function testRecipientCheckAsksDiscoveryForTheDocumentTypeAndProcess(): void
    {
        $discovery = $this->createMock(DocumentDiscoveryProviderInterface::class);
        $discovery->expects($this->once())
            ->method('receives')
            ->with(
                $this->equalTo(new CheckReceivingCapabilityRequest(
                    Participant::parse('0245:2012345687'),
                    PeppolDocumentTypeIdentifier::fromString('synthetic-document-type'),
                    PeppolProcessIdentifier::fromString('synthetic-process')
                ))
            )
            ->willReturn(new CheckReceivingCapabilityResponse(Participant::parse('0245:2012345687'), true, null, null));

        $check = new RecipientCapabilityCheck($discovery);
        $check->check($this->payload());
    }

    public function testRecipientThatDoesNotReceiveTheDocumentRejectsIt(): void
    {
        $discovery = $this->createMock(DocumentDiscoveryProviderInterface::class);
        $discovery->method('receives')->willReturn(
            new CheckReceivingCapabilityResponse(Participant::parse('0245:2012345687'), false, null, null)
        );

        try {
            (new RecipientCapabilityCheck($discovery))->check($this->payload());
            $this->fail('Expected the recipient check to reject the document.');
        } catch (DocumentPreflightException $exception) {
            $this->assertInstanceOf(RecipientUnsupportedException::class, $exception);
        }
    }

    public function testRecipientLookupFailureIsNotARejection(): void
    {
        $failure   = new ProviderConnectionException('Synthetic outage');
        $discovery = $this->createMock(DocumentDiscoveryProviderInterface::class);
        $discovery->method('receives')->willThrowException($failure);

        try {
            (new RecipientCapabilityCheck($discovery))->check($this->payload());
            $this->fail('Expected the lookup failure to propagate.');
        } catch (ProviderApiException $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    public function testProviderCheckSendsThePayloadAndAcceptsAValidDocument(): void
    {
        $payload = $this->payload();
        $api     = $this->createMock(ValidateDocumentEndpoint::class);
        $api->expects($this->once())->method('send')->with($this->equalTo(
            new ValidateDocumentRequest(
                Participant::parse('0245:2012345676'),
                $payload
            )
        ))
            ->willReturn(true);

        $check = new PeppolGatewayDocumentValidationCheck($api);
        $check->check(new ValidateDocumentRequest(Participant::parse('0245:2012345676'), $payload));
    }

    public function testProviderHttpErrorPropagatesWithTheProviderResponse(): void
    {
        $failure = new ProviderErrorResponseException(400);
        $api     = $this->createMock(ValidateDocumentEndpoint::class);
        $api->method('send')->willThrowException($failure);

        try {
            (new PeppolGatewayDocumentValidationCheck($api))->check(new ValidateDocumentRequest(
                Participant::parse('0245:2012345676'),
                $this->payload()
            ));
            $this->fail('Expected the provider error to propagate; the host decides whether it is a rejection.');
        } catch (ProviderApiException $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    public function testInvalidResponseWithoutDetailsIsARejection(): void
    {
        $api = $this->createMock(ValidateDocumentEndpoint::class);
        $api->method('send')->willReturn(false);

        try {
            (new PeppolGatewayDocumentValidationCheck($api))->check(new ValidateDocumentRequest(
                Participant::parse('0245:2012345676'),
                $this->payload()
            ));
            $this->fail('Expected the provider to reject the document.');
        } catch (DocumentPreflightException $exception) {
            $this->assertInstanceOf(DocumentRejectedByProviderException::class, $exception);
        }
    }

    public function testNonValidationProviderErrorsArePropagated(): void
    {
        $failure = new ProviderErrorResponseException(500);
        $api     = $this->createMock(ValidateDocumentEndpoint::class);
        $api->method('send')->willThrowException($failure);

        try {
            (new PeppolGatewayDocumentValidationCheck($api))->check(new ValidateDocumentRequest(
                Participant::parse('0245:2012345676'),
                $this->payload()
            ));
            $this->fail('Expected the provider error to propagate.');
        } catch (ProviderApiException $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    private function payload(): OutboundDocumentPayload
    {
        return new OutboundDocumentPayload(
            SubmittedDocumentId::fromString('INV-SYNTHETIC-1'),
            PeppolDocumentTypeIdentifier::fromString('synthetic-document-type'),
            PeppolProcessIdentifier::fromString('synthetic-process'),
            Participant::parse('0245:2012345676'),
            Participant::parse('0245:2012345687'),
            Timestamp::fromIso8601('2026-11-15T09:30:00Z'),
            '<Invoice>synthetic</Invoice>',
            Sha256Checksum::fromContent('<Invoice>synthetic</Invoice>')
        );
    }
}
