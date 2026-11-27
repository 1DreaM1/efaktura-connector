<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection;

use DreaM1\EfakturaConnector\Core\Connection\Health\ParticipantProfile;
use DreaM1\EfakturaConnector\Core\Contracts\ParticipantProfileProviderInterface;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetParticipantProfileEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ParticipantResponseValidator;

/** Loads and maps Peppol Gateway participant profile responses. */
class PeppolGatewayParticipantProfileProvider implements ParticipantProfileProviderInterface
{
    /** Diagnostic message used when the profile response has an invalid participant. */
    private const INVALID_PROFILE_MESSAGE = 'Gateway returned an invalid participant profile.';

    private GetParticipantProfileEndpoint $participantProfile;

    /**
     * @param GetParticipantProfileEndpoint $participantProfile Participant profile returned by the provider.
     */
    public function __construct(GetParticipantProfileEndpoint $participantProfile)
    {
        $this->participantProfile = $participantProfile;
    }

    /**
     * Returns the provider profile for the participant.
     *
     * @param  Participant        $participant Participant whose identity scopes the provider operation.
     * @return ParticipantProfile
     *                            {@inheritdoc}
     */
    public function participantProfile(Participant $participant): ParticipantProfile
    {
        $response = $this->participantProfile->send($participant);

        ParticipantResponseValidator::assertMatches(
            $participant,
            $response->participant(),
            self::INVALID_PROFILE_MESSAGE
        );

        return $response;
    }
}
