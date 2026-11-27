<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Connection;

use DreaM1\EfakturaConnector\Core\Connection\CheckReceivingCapabilityRequest;
use DreaM1\EfakturaConnector\Core\Connection\CheckReceivingCapabilityResponse;
use DreaM1\EfakturaConnector\Core\Connection\Health\ParticipantCapability;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\CheckReceivingCapabilityEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\PeppolGatewayDiscoveryProvider;
use PHPUnit\Framework\TestCase;

/** Tests Peppol Gateway participant discovery mapping. */
class PeppolGatewayDiscoveryProviderTest extends TestCase
{
    public function testChecksTheRequestedParticipantAndDocumentCapability(): void
    {
        $participantId  = '0245:synthetic-participant';
        $participant    = Participant::parse($participantId);
        $documentTypeId = 'synthetic-document-type';
        $processId      = 'synthetic-process';
        $request        = new CheckReceivingCapabilityRequest($participant, PeppolDocumentTypeIdentifier::fromString($documentTypeId), PeppolProcessIdentifier::fromString($processId));
        $response       = new CheckReceivingCapabilityResponse(
            $participant,
            true,
            'https://ap.example.test/as4',
            new ParticipantCapability(
                PeppolDocumentTypeIdentifier::fromString('synthetic-document-type'),
                PeppolProcessIdentifier::fromString('synthetic-process'),
                'as4'
            )
        );
        $api = $this->createMock(CheckReceivingCapabilityEndpoint::class);
        $api->expects($this->once())
            ->method('send')
            ->with($this->equalTo($request))
            ->willReturn($response);

        $result = (new PeppolGatewayDiscoveryProvider($api))->receives($request);
        $this->assertSame($participantId, $result->participant->identifier());
        $this->assertTrue($result->receives);
        $this->assertSame('https://ap.example.test/as4', $result->endpoint);
        $this->assertSame('synthetic-document-type', $result->capability->documentTypeId()->value());
        $this->assertSame('synthetic-process', $result->capability->processId()->value());
        $this->assertSame('as4', $result->capability->transportProfile());
    }

    public function testRejectsMismatchedParticipantIdInDiscoveryResponse(): void
    {
        $api = $this->createMock(CheckReceivingCapabilityEndpoint::class);
        $api->method('send')->willReturn(new CheckReceivingCapabilityResponse(
            Participant::parse('0245:other'),
            true,
            null,
            null
        ));

        $this->expectException(ProviderApiException::class);

        (new PeppolGatewayDiscoveryProvider($api))->receives(new CheckReceivingCapabilityRequest(
            Participant::parse('0245:synthetic-participant'),
            PeppolDocumentTypeIdentifier::fromString('synthetic-document-type'),
            PeppolProcessIdentifier::fromString('synthetic-process')
        ));
    }

    public function testMalformedDiscoveryResponseIsReportedAsUnavailableGateway(): void
    {
        $api = $this->createMock(CheckReceivingCapabilityEndpoint::class);
        $api->method('send')->willThrowException(new InvalidProviderResponseException('Synthetic malformed response.'));

        try {
            (new PeppolGatewayDiscoveryProvider($api))->receives(new CheckReceivingCapabilityRequest(
                Participant::parse('0245:synthetic-participant'),
                PeppolDocumentTypeIdentifier::fromString('doc-type'),
                PeppolProcessIdentifier::fromString('process')
            ));
            $this->fail('Malformed responses must reach the caller.');
        } catch (ProviderApiException $exception) {
            $this->assertInstanceOf(InvalidProviderResponseException::class, $exception);
        }
    }

    public function testMapsUnauthorizedGatewayResponsesToAuthenticationFailures(): void
    {
        $api = $this->createMock(CheckReceivingCapabilityEndpoint::class);
        $api->method('send')->willThrowException(
            new ProviderErrorResponseException(401)
        );

        try {
            (new PeppolGatewayDiscoveryProvider($api))->receives(new CheckReceivingCapabilityRequest(
                Participant::parse('0245:synthetic-participant'),
                PeppolDocumentTypeIdentifier::fromString('synthetic-document-type'),
                PeppolProcessIdentifier::fromString('synthetic-process')
            ));
            $this->fail('Gateway errors must reach the caller.');
        } catch (ProviderApiException $exception) {
            $this->assertSame(401, $exception->httpStatusCode()->value());
        }
    }
}
