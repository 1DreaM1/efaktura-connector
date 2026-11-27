<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\Connection\Health\ParticipantProfile;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;

/** Loads the participant profile published by the Peppol Gateway. */
interface ParticipantProfileProviderInterface
{
    /**
     * Retrieves the profile for a participant identifier.
     *
     * @param  Participant                  $participant Participant identifier whose published profile is requested.
     * @return ParticipantProfile
     * @throws ProviderApiException         when the request fails or the response is invalid; HTTP 404 means the participant is not registered
     * @throws RequestCoordinationException when local request
     *                                      coordination cannot be established
     */
    public function participantProfile(Participant $participant): ParticipantProfile;
}
