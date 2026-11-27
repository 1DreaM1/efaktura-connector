<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator;

use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClient;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientReference;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorId;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator\IntegratorClientFactory;

/** GET /v1/integrators/{integratorId}/clients/{clientId} */
class GetClientEndpoint extends AbstractEndpoint
{
    /**
     * Retrieves OAuth client metadata and participant access details.
     *
     * @param  IntegratorId              $integratorId Provider-assigned integrator identifier.
     * @param  IntegratorClientReference $request      Reference to the client to retrieve.
     * @return IntegratorClient          Typed client metadata returned by the provider.
     */
    public function send(IntegratorId $integratorId, IntegratorClientReference $request): IntegratorClient
    {
        return $this->api->json(
            ApiRequest::get(IntegratorApiPaths::CLIENT, [
                'integratorId' => $integratorId->value(),
                'clientId'     => $request->clientId()->value(),
            ]),
            [IntegratorClientFactory::class, 'fromArray']
        );
    }
}
