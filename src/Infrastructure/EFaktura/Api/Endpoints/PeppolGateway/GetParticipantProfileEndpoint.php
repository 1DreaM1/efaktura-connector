<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Connection\Health\ParticipantProfile;
use DreaM1\EfakturaConnector\Domain\EFaktura\CodeList\ParticipantIdentifierSchemeCodeList;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\ParticipantProfileFactory;

/**
 * GET /v1/participants/{participantId} → 200 ParticipantProfile
 *   { participantId, reachable, name?, capabilities: { documentTypeId, processId, transportProfile? }[] }
 */
class GetParticipantProfileEndpoint extends AbstractEndpoint
{
    /**
     * Sends the request to the provider endpoint and returns its mapped response.
     *
     * @param  Participant        $participant Participant whose Peppol profile is being retrieved.
     * @return ParticipantProfile
     */
    public function send(Participant $participant): ParticipantProfile
    {
        ParticipantIdentifierSchemeCodeList::assertAllowedForExistingOperation($participant->scheme());

        return $this->api->json(
            ApiRequest::get(PeppolGatewayApiPaths::PARTICIPANT, [
                'participantId' => $participant->identifier(),
            ]),
            [ParticipantProfileFactory::class, 'fromArray']
        );
    }
}
