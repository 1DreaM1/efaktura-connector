<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator;

use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientReference;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorId;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;

/** DELETE /v1/integrators/{integratorId}/clients/{clientId} → 204 */
class DeleteClientEndpoint extends AbstractEndpoint
{
    /**
     * Deletes an OAuth client; the provider contract responds with 204 No Content.
     *
     * @param IntegratorId              $integratorId Provider-assigned integrator identifier.
     * @param IntegratorClientReference $request      Reference to the client to delete.
     */
    public function send(IntegratorId $integratorId, IntegratorClientReference $request): void
    {
        $this->api->send(ApiRequest::delete(IntegratorApiPaths::CLIENT, [
            'integratorId' => $integratorId->value(),
            'clientId'     => $request->clientId()->value(),
        ]));
    }
}
