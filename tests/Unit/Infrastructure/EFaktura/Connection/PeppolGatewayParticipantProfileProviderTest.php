<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Connection;

use DreaM1\EfakturaConnector\Core\Connection\Health\ParticipantCapabilities;
use DreaM1\EfakturaConnector\Core\Connection\Health\ParticipantCapability;
use DreaM1\EfakturaConnector\Core\Connection\Health\ParticipantProfile;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetParticipantProfileEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\PeppolGatewayParticipantProfileProvider;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use PHPUnit\Framework\TestCase;

/** Tests Peppol Gateway participant profile mapping. */
class PeppolGatewayParticipantProfileProviderTest extends TestCase
{
    public function testMapsGatewayProfileIntoTypedParticipantData(): void
    {
        $participantId = '0245:synthetic-participant';
        $participant   = Participant::parse($participantId);
        $api           = $this->createMock(GetParticipantProfileEndpoint::class);
        $api->expects($this->once())
            ->method('send')
            ->with($this->equalTo($participant))
            ->willReturn(new ParticipantProfile(
                $participant,
                true,
                'Synthetic Participant',
                new ParticipantCapabilities([new ParticipantCapability(
                    PeppolDocumentTypeIdentifier::fromString(ApiFixtures::DOCUMENT_TYPE_ID),
                    PeppolProcessIdentifier::fromString(ApiFixtures::PROCESS_ID),
                    'peppol-transport-as4-v2_0'
                )])
            ));

        $profile = (new PeppolGatewayParticipantProfileProvider($api))->participantProfile($participant);

        $this->assertInstanceOf(ParticipantProfile::class, $profile);
        $this->assertSame($participantId, $profile->participant()->identifier());
        $this->assertTrue($profile->reachable());
        $this->assertSame('Synthetic Participant', $profile->name());
        $this->assertCount(1, $profile->capabilities());
        $capabilities = iterator_to_array($profile->capabilities());
        $capability   = $capabilities[0];
        $this->assertSame(ApiFixtures::DOCUMENT_TYPE_ID, $capability->documentTypeId()->value());
        $this->assertSame(ApiFixtures::PROCESS_ID, $capability->processId()->value());
        $this->assertSame('peppol-transport-as4-v2_0', $capability->transportProfile());
    }

    public function testRejectsMalformedGatewayProfile(): void
    {
        $api = $this->createMock(GetParticipantProfileEndpoint::class);
        $api->method('send')->willThrowException(new InvalidProviderResponseException('Synthetic malformed response.'));

        $this->expectException(ProviderApiException::class);

        (new PeppolGatewayParticipantProfileProvider($api))->participantProfile(
            Participant::parse('0245:synthetic-participant')
        );
    }

    public function testRejectsGatewayProfileForAnotherParticipant(): void
    {
        $api = $this->createMock(GetParticipantProfileEndpoint::class);
        $api->method('send')->willReturn(new ParticipantProfile(
            Participant::parse('0245:other-participant'),
            true,
            null,
            new ParticipantCapabilities([])
        ));

        $this->expectException(ProviderApiException::class);

        (new PeppolGatewayParticipantProfileProvider($api))->participantProfile(
            Participant::parse('0245:expected-participant')
        );
    }

    public function testMapsNotFoundGatewayResponsesToNotFoundFailures(): void
    {
        $api = $this->createMock(GetParticipantProfileEndpoint::class);
        $api->method('send')->willThrowException(new ProviderErrorResponseException(404));

        try {
            (new PeppolGatewayParticipantProfileProvider($api))->participantProfile(
                Participant::parse('0245:synthetic-participant')
            );
            $this->fail('Gateway errors must reach the caller.');
        } catch (ProviderApiException $exception) {
            $this->assertSame(404, $exception->httpStatusCode()->value());
        }
    }
}
