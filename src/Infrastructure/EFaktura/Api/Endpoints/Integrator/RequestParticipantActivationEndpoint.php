<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator;

use DreaM1\EfakturaConnector\Core\IntegratorApi\ActivateParticipantRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorId;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ParticipantActivation;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator\ParticipantActivationFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization\IntegratorRequestMapper;

/** POST /v1/integrators/{integratorId}/participants/{participantId}/activations */
class RequestParticipantActivationEndpoint extends AbstractEndpoint
{
    /**
     * Requests activation of a participant contract using the supplied idempotency key.
     *
     * @param  IntegratorId               $integratorId Provider-assigned integrator identifier.
     * @param  ActivateParticipantRequest $request      Participant, contract mode and request key.
     * @return ParticipantActivation      Activation metadata returned by the provider.
     */
    public function send(IntegratorId $integratorId, ActivateParticipantRequest $request): ParticipantActivation
    {
        return $this->api->json(
            ApiRequest::post(IntegratorApiPaths::PARTICIPANT_ACTIVATIONS, [
                'integratorId'  => $integratorId->value(),
                'participantId' => $request->participantId()->identifier(),
            ])
                ->withIdempotencyKey($request->idempotencyKey())
                ->withJsonBody(IntegratorRequestMapper::activateParticipantBody($request))
                ->retrySafe(),
            [ParticipantActivationFactory::class, 'fromArray']
        );
    }
}
