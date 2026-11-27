<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi;

use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentAcknowledgement;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentReference;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi\ReceivedDocumentAcknowledgementFactory;

/**
 * POST /sapi/document/receive/{documentId}/acknowledge → 200 ReceivedDocumentAcknowledgement
 */
class AcknowledgeReceivedDocumentEndpoint extends AbstractEndpoint
{
    /**
     * Sends the request to the provider endpoint and returns its mapped response.
     *
     * @param  ReceivedDocumentReference       $request Participant and provider document ID identifying
     *                                                  the document to acknowledge.
     * @return ReceivedDocumentAcknowledgement
     */
    public function send(
        ReceivedDocumentReference $request
    ): ReceivedDocumentAcknowledgement {
        // The SAPI contract makes acknowledgement idempotent, so retrying the same document ID is safe.
        return $this->api->json(
            ApiRequest::post(
                SapiApiPaths::ACKNOWLEDGE_RECEIVED_DOCUMENT,
                ['documentId' => $request->providerDocumentId()->value()]
            )
                ->forParticipant($request->participant())
                ->retrySafe()
                ->withContext($request->context()),
            [ReceivedDocumentAcknowledgementFactory::class, 'fromArray']
        );
    }
}
