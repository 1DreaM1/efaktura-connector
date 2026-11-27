<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator;

use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientList;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorId;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ListClientsRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator\IntegratorClientListFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization\IntegratorRequestMapper;

/** GET /v1/integrators/{integratorId}/clients */
class ListClientsEndpoint extends AbstractEndpoint
{
    /**
     * Retrieves one cursor-paginated page of OAuth clients.
     *
     * @param  IntegratorId         $integratorId Provider-assigned integrator identifier.
     * @param  ListClientsRequest   $request      Page cursor, size and optional status filter.
     * @return IntegratorClientList Client page and optional continuation token.
     */
    public function send(IntegratorId $integratorId, ListClientsRequest $request): IntegratorClientList
    {
        return $this->api->json(
            ApiRequest::get(IntegratorApiPaths::CLIENTS, ['integratorId' => $integratorId->value()])
                ->withQuery(IntegratorRequestMapper::listClientsQuery($request)),
            [IntegratorClientListFactory::class, 'fromArray']
        );
    }
}
