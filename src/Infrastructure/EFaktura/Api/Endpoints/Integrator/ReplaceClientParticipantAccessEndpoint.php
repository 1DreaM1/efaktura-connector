<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator;

use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClient;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorId;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ReplaceClientParticipantAccessRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator\IntegratorClientFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization\IntegratorRequestMapper;

/** PUT /v1/integrators/{integratorId}/clients/{clientId}/participants */
class ReplaceClientParticipantAccessEndpoint extends AbstractEndpoint
{
    /**
     * Replaces all participant permissions for an OAuth client.
     *
     * @param  IntegratorId                          $integratorId Provider-assigned integrator identifier.
     * @param  ReplaceClientParticipantAccessRequest $request      Client reference and complete replacement list.
     * @return IntegratorClient                      Updated client metadata returned by the provider.
     */
    public function send(
        IntegratorId $integratorId,
        ReplaceClientParticipantAccessRequest $request
    ): IntegratorClient {
        return $this->api->json(
            ApiRequest::put(IntegratorApiPaths::CLIENT_PARTICIPANTS, [
                'integratorId' => $integratorId->value(),
                'clientId'     => $request->client()->clientId()->value(),
            ])
                ->withJsonBody(IntegratorRequestMapper::replaceClientParticipantAccessBody($request))
                ->retrySafe(),
            [IntegratorClientFactory::class, 'fromArray']
        );
    }
}
