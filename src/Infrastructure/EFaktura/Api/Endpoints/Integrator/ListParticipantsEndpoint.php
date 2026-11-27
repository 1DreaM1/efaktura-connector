<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator;

use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorId;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorParticipantList;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ListParticipantsRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator\IntegratorParticipantListFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization\IntegratorRequestMapper;

/** GET /v1/integrators/{integratorId}/participants */
class ListParticipantsEndpoint extends AbstractEndpoint
{
    /**
     * Retrieves one cursor-paginated page of participants for the integrator.
     *
     * @param  IntegratorId              $integratorId Provider-assigned integrator identifier.
     * @param  ListParticipantsRequest   $request      Page cursor and size.
     * @return IntegratorParticipantList Participant page and optional continuation token.
     */
    public function send(IntegratorId $integratorId, ListParticipantsRequest $request): IntegratorParticipantList
    {
        return $this->api->json(
            ApiRequest::get(IntegratorApiPaths::PARTICIPANTS, ['integratorId' => $integratorId->value()])
                ->withQuery(IntegratorRequestMapper::listParticipantsQuery($request)),
            [IntegratorParticipantListFactory::class, 'fromArray']
        );
    }
}
