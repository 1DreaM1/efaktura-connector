<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Connection\CheckReceivingCapabilityRequest;
use DreaM1\EfakturaConnector\Core\Connection\CheckReceivingCapabilityResponse;
use DreaM1\EfakturaConnector\Domain\EFaktura\CodeList\ParticipantIdentifierSchemeCodeList;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\CheckReceivingCapabilityResponseFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization\DiscoveryRequestMapper;

/**
 * POST /v1/discovery/receives → 200 CheckReceivingCapabilityResponse
 *
 * A read-only lookup sent as POST, so the transport may replay the call.
 */
class CheckReceivingCapabilityEndpoint extends AbstractEndpoint
{
    /**
     * Sends the request to the provider endpoint and returns its mapped response.
     *
     * @param  CheckReceivingCapabilityRequest  $request Participant and document type, with an optional process.
     * @return CheckReceivingCapabilityResponse
     */
    public function send(CheckReceivingCapabilityRequest $request): CheckReceivingCapabilityResponse
    {
        ParticipantIdentifierSchemeCodeList::assertAllowedForExistingOperation($request->participant()->scheme());

        return $this->api->json(
            ApiRequest::post(PeppolGatewayApiPaths::DISCOVERY_RECEIVES)
                ->withJsonBody(DiscoveryRequestMapper::receivesBody($request))
                ->retrySafe(),
            [CheckReceivingCapabilityResponseFactory::class, 'fromArray']
        );
    }
}
