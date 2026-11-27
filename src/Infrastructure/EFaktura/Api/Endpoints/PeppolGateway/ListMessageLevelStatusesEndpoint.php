<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Documents\SentDocumentReference;
use DreaM1\EfakturaConnector\Core\MessageStatuses\ProviderMessageLevelStatusResponse;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\ProviderMessageLevelStatusResponseFactory;

/**
 * GET /v1/sent-documents/{identifier}/message-level-statuses → 200 ProviderMessageLevelStatusResponse
 */
class ListMessageLevelStatusesEndpoint extends AbstractEndpoint
{
    /**
     * Sends the request to the provider endpoint and returns its mapped response.
     *
     * @param  SentDocumentReference              $request Sent document reference used to retrieve provider message-level statuses.
     * @return ProviderMessageLevelStatusResponse
     */
    public function send(
        SentDocumentReference $request
    ): ProviderMessageLevelStatusResponse {
        return $this->api->json(
            ApiRequest::get(
                PeppolGatewayApiPaths::MESSAGE_LEVEL_STATUSES,
                ['identifier' => $request->identifier()->value()]
            )
                ->forParticipant($request->participant())
                ->withContext($request->context()),
            [ProviderMessageLevelStatusResponseFactory::class, 'fromArray']
        );
    }
}
