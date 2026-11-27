<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi;

use DreaM1\EfakturaConnector\Core\Documents\DocumentSubmissionResult;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi\DocumentSubmissionResultFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization\DocumentSubmissionRequestMapper;

/**
 * POST /sapi/document/send → 202 DocumentSubmissionResult
 *   { providerDocumentId, status: "ACCEPTED"|"REJECTED", receivedAt?, timestamp }
 *
 * Never replayed by the transport; a lost response is resolved through the Idempotency-Key instead.
 */
class SendDocumentEndpoint extends AbstractEndpoint
{
    /**
     * Sends the request to the provider endpoint and returns its mapped response.
     *
     * @param  SendDocumentRequest      $request Participant, outbound document payload and idempotency key to submit.
     * @return DocumentSubmissionResult
     */
    public function send(SendDocumentRequest $request): DocumentSubmissionResult
    {
        // Keep the idempotency key on the request, but never replay document submission automatically.
        return $this->api->json(
            ApiRequest::post(SapiApiPaths::SEND_DOCUMENT)
                ->withIdempotencyKey($request->idempotencyKey())
                ->withJsonBody(DocumentSubmissionRequestMapper::fromPayload($request->payload()))
                ->forParticipant($request->participant()),
            [DocumentSubmissionResultFactory::class, 'fromArray']
        );
    }
}
