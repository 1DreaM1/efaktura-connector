<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Documents;

use DreaM1\EfakturaConnector\Core\Documents\DocumentSubmissionResult;
use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use DreaM1\EfakturaConnector\Core\Documents\OutboundDocumentPayload;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentRequest;
use DreaM1\EfakturaConnector\Core\Documents\Sha256Checksum;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\TokenRequestException;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Domain\EFaktura\SubmittedDocumentId;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\SendDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\SapiDocumentSubmissionGateway;
use DreaM1\EfakturaConnector\Support\Timestamp;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use PHPUnit\Framework\TestCase;

/** Tests document submission through the SAPI gateway. */
class SapiDocumentSubmissionGatewayTest extends TestCase
{
    public function testSendUsesPayloadAndIdempotencyKeyAndMapsProviderResponse(): void
    {
        $payload = $this->payload();
        $sapiApi = $this->createMock(SendDocumentEndpoint::class);
        $sapiApi->expects($this->once())
            ->method('send')
            ->with(
                $this->equalTo(new SendDocumentRequest(
                    Participant::parse('0245:2012345676'),
                    $payload,
                    IdempotencyKey::fromString(
                        '00000000-0000-4000-8000-000000000001'
                    )
                ))
            )
            ->willReturn(new DocumentSubmissionResult(
                ProviderDocumentId::fromString('provider-document-1'),
                ProviderDocumentStatus::fromString(ProviderDocumentStatus::ACCEPTED),
                Timestamp::fromIso8601(ApiFixtures::TIMESTAMP),
                Timestamp::fromIso8601(ApiFixtures::TIMESTAMP)
            ));

        $result = (new SapiDocumentSubmissionGateway($sapiApi))->send($this->request($payload));

        $this->assertInstanceOf(DocumentSubmissionResult::class, $result);
        $this->assertSame('provider-document-1', $result->providerDocumentId()->value());
        $this->assertSame('ACCEPTED', $result->status()->value());
        $this->assertSame(ApiFixtures::TIMESTAMP, $result->timestamp()->value());
        $this->assertSame(ApiFixtures::TIMESTAMP, $result->receivedAt()->value());
    }

    public function testSendRejectsAnIncompleteProviderResponse(): void
    {
        $sapiApi = $this->createMock(SendDocumentEndpoint::class);
        $sapiApi->method('send')->willThrowException(new InvalidProviderResponseException(
            'Synthetic invalid response.'
        ));

        $this->expectException(ProviderApiException::class);

        (new SapiDocumentSubmissionGateway($sapiApi))->send($this->request($this->payload()));
    }

    public function testMapsProviderRejectionIntoSubmissionResult(): void
    {
        $sapiApi = $this->createMock(SendDocumentEndpoint::class);
        $sapiApi->expects($this->once())->method('send')->willReturn(
            new DocumentSubmissionResult(
                ProviderDocumentId::fromString('provider-document-1'),
                ProviderDocumentStatus::fromString(ProviderDocumentStatus::REJECTED),
                Timestamp::fromIso8601(ApiFixtures::TIMESTAMP),
                null
            )
        );

        $result = (new SapiDocumentSubmissionGateway($sapiApi))->send($this->request($this->payload()));

        $this->assertSame('REJECTED', $result->status()->value());
        $this->assertSame('provider-document-1', $result->providerDocumentId()->value());
    }

    public function testMalformedSuccessfulResponseHasUnknownSubmissionOutcome(): void
    {
        $responseException = new InvalidProviderResponseException('Synthetic invalid response.');
        $sapiApi           = $this->createMock(SendDocumentEndpoint::class);
        $sapiApi->expects($this->once())->method('send')->willThrowException($responseException);

        try {
            (new SapiDocumentSubmissionGateway($sapiApi))->send($this->request($this->payload()));
            $this->fail('An unusable provider response must propagate.');
        } catch (ProviderApiException $exception) {
            $this->assertSame($responseException, $exception);
        }
    }

    public function testProviderHttpFailureIsNotConvertedToUnknownSubmissionOutcome(): void
    {
        $providerException = new ProviderErrorResponseException(422);
        $sapiApi           = $this->createMock(SendDocumentEndpoint::class);
        $sapiApi->expects($this->once())->method('send')->willThrowException($providerException);

        try {
            (new SapiDocumentSubmissionGateway($sapiApi))->send($this->request($this->payload()));
            $this->fail('An explicit provider rejection must remain a provider failure.');
        } catch (ProviderApiException $exception) {
            $this->assertSame($providerException, $exception);
        }
    }

    public function testOnlyDocumentRequestConnectionFailuresHaveUnknownOutcome(): void
    {
        $connectionException = new ProviderConnectionException('Synthetic connection failure.');
        $sapiApi             = $this->createMock(SendDocumentEndpoint::class);
        $sapiApi->expects($this->once())->method('send')->willThrowException($connectionException);

        try {
            (new SapiDocumentSubmissionGateway($sapiApi))->send($this->request($this->payload()));
            $this->fail('A document request connection failure must be reported.');
        } catch (ProviderApiException $exception) {
            $this->assertSame($connectionException, $exception);
        }
    }

    public function testTokenAcquisitionFailureIsNotReportedAsUnknownSubmissionOutcome(): void
    {
        $tokenException = new TokenRequestException();
        $sapiApi        = $this->createMock(SendDocumentEndpoint::class);
        $sapiApi->expects($this->once())->method('send')->willThrowException($tokenException);

        try {
            (new SapiDocumentSubmissionGateway($sapiApi))->send($this->request($this->payload()));
            $this->fail('Token acquisition failures must be reported.');
        } catch (ProviderApiException $exception) {
            $this->assertSame($tokenException, $exception);
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

    private function request(OutboundDocumentPayload $payload): SendDocumentRequest
    {
        return new SendDocumentRequest(
            Participant::parse('0245:2012345676'),
            $payload,
            IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001')
        );
    }
}
