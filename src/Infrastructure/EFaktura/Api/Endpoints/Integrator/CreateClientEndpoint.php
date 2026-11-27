<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator;

use DreaM1\EfakturaConnector\Core\IntegratorApi\CreateClientRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientCreation;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorId;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator\IntegratorClientCreationFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization\IntegratorRequestMapper;

/** POST /v1/integrators/{integratorId}/clients */
class CreateClientEndpoint extends AbstractEndpoint
{
    /**
     * Creates an OAuth client and maps its metadata and one-time credentials.
     *
     * @param  IntegratorId             $integratorId Provider-assigned integrator identifier.
     * @param  CreateClientRequest      $request      Client details, access list and idempotency key.
     * @return IntegratorClientCreation Created client and its one-time secret, when returned.
     */
    public function send(IntegratorId $integratorId, CreateClientRequest $request): IntegratorClientCreation
    {
        return $this->api->json(
            ApiRequest::post(IntegratorApiPaths::CLIENTS, ['integratorId' => $integratorId->value()])
                ->withIdempotencyKey($request->idempotencyKey())
                ->withJsonBody(IntegratorRequestMapper::createClientBody($request))
                ->retrySafe(),
            [IntegratorClientCreationFactory::class, 'fromArray']
        );
    }
}
