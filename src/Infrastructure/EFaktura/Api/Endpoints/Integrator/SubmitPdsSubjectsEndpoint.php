<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator;

use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorId;
use DreaM1\EfakturaConnector\Core\IntegratorApi\PdsSubjectResponse;
use DreaM1\EfakturaConnector\Core\IntegratorApi\SubmitPdsSubjectsRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\PdsRequestSigner;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator\PdsSubjectResponseFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization\PdsSubjectPayloadMapper;

/** POST /v1/integrators/{integratorId}/pds/subjects */
class SubmitPdsSubjectsEndpoint extends AbstractEndpoint
{
    /**
     * Submits PDS subjects with an HMAC-SHA512 signature in X-PDS-Secret.
     *
     * @param  IntegratorId             $integratorId Provider-assigned integrator identifier.
     * @param  SubmitPdsSubjectsRequest $request      Subjects and the signing secret.
     * @return PdsSubjectResponse       Provider acknowledgement.
     */
    public function send(IntegratorId $integratorId, SubmitPdsSubjectsRequest $request): PdsSubjectResponse
    {
        $apiRequest = ApiRequest::post(
            IntegratorApiPaths::PDS_SUBJECTS,
            ['integratorId' => $integratorId->value()]
        )->withJsonBody(PdsSubjectPayloadMapper::toPayload($request));

        return $this->api->json(
            PdsRequestSigner::sign($apiRequest, $request->signingSecret()),
            [PdsSubjectResponseFactory::class, 'fromArray']
        );
    }
}
