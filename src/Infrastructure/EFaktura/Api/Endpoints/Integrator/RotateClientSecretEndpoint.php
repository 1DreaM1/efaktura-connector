<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator;

use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientReference;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientSecretRotation;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorId;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator\IntegratorClientSecretRotationFactory;

/** PUT /v1/integrators/{integratorId}/clients/{clientId}/secret */
class RotateClientSecretEndpoint extends AbstractEndpoint
{
    /**
     * Rotates an OAuth client secret without marking the request safe to retry.
     *
     * @param  IntegratorId                   $integratorId Provider-assigned integrator identifier.
     * @param  IntegratorClientReference      $request      Reference to the client whose secret is rotated.
     * @return IntegratorClientSecretRotation New one-time secret, when returned by the provider.
     */
    public function send(
        IntegratorId $integratorId,
        IntegratorClientReference $request
    ): IntegratorClientSecretRotation {
        // Retrying a secret rotation could invalidate the secret from the first successful response.
        return $this->api->json(
            ApiRequest::put(IntegratorApiPaths::CLIENT_SECRET, [
                'integratorId' => $integratorId->value(),
                'clientId'     => $request->clientId()->value(),
            ]),
            [IntegratorClientSecretRotationFactory::class, 'fromArray']
        );
    }
}
