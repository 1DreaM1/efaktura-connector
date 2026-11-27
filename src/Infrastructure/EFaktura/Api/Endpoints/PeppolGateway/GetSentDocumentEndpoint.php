<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Documents\SentDocumentReference;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentStatus;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\SentDocumentStatusFactory;

/**
 * GET /v1/sent-documents/{identifier} → 200 SentDocumentStatus
 */
class GetSentDocumentEndpoint extends AbstractEndpoint
{
    /**
     * Sends the request to the provider endpoint and returns its mapped response.
     *
     * @param  SentDocumentReference $request Participant-scoped reference used to retrieve sent document status.
     * @return SentDocumentStatus
     */
    public function send(
        SentDocumentReference $request
    ): SentDocumentStatus {
        return $this->api->json(
            ApiRequest::get(
                PeppolGatewayApiPaths::SENT_DOCUMENT,
                ['identifier' => $request->identifier()->value()]
            )
                ->forParticipant($request->participant())
                ->withContext($request->context()),
            [SentDocumentStatusFactory::class, 'fromArray']
        );
    }
}
