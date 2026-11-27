<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\Facades;

use DreaM1\EfakturaConnector\Core\IntegratorApi\ActivateParticipantRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorId;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorParticipantList;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ListParticipantsRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ParticipantActivation;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator\ListParticipantsEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator\RequestParticipantActivationEndpoint;

/**
 * Groups the participant operations for one Integrator account.
 *
 * @internal
 */
final class IntegratorParticipantManagementFacade
{
    private IntegratorId $integratorId;
    private ListParticipantsEndpoint $participantList;
    private RequestParticipantActivationEndpoint $participantActivation;

    /**
     * Creates participant operations scoped to one Integrator account.
     *
     * @param IntegratorId $integratorId Account identifier used for each endpoint request.
     * @param ApiConnector $api          Connector authenticated with the Integrator API token.
     */
    public function __construct(IntegratorId $integratorId, ApiConnector $api)
    {
        $this->integratorId          = $integratorId;
        $this->participantList       = new ListParticipantsEndpoint($api);
        $this->participantActivation = new RequestParticipantActivationEndpoint($api);
    }

    /**
     * Lists participants matching the supplied cursor.
     *
     * @param  ListParticipantsRequest   $request Pagination settings.
     * @return IntegratorParticipantList The requested participant page.
     */
    public function listParticipants(ListParticipantsRequest $request): IntegratorParticipantList
    {
        return $this->participantList->send($this->integratorId, $request);
    }

    /**
     * Requests activation for a participant in the configured account.
     *
     * @param  ActivateParticipantRequest $request Participant and activation mode.
     * @return ParticipantActivation      Provider activation result.
     */
    public function requestParticipantActivation(ActivateParticipantRequest $request): ParticipantActivation
    {
        return $this->participantActivation->send($this->integratorId, $request);
    }
}
