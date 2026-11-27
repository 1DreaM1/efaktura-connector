<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi;

use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentPayload;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentReference;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi\ReceivedDocumentPayloadFactory;

/**
 * GET /sapi/document/receive/{documentId} → 200 ReceivedDocumentPayload
 *   { metadata: DocumentMetadata, payload, payloadFormat }
 */
class GetReceivedDocumentEndpoint extends AbstractEndpoint
{
    /**
     * Sends the request to the provider endpoint and returns its mapped response.
     *
     * @param  ReceivedDocumentReference $request Participant and provider document ID identifying
     *                                            the document to retrieve.
     * @return ReceivedDocumentPayload
     */
    public function send(
        ReceivedDocumentReference $request
    ): ReceivedDocumentPayload {
        return $this->api->json(
            ApiRequest::get(
                SapiApiPaths::RECEIVED_DOCUMENT,
                ['documentId' => $request->providerDocumentId()->value()]
            )
                ->forParticipant($request->participant())
                ->withContext($request->context()),
            [ReceivedDocumentPayloadFactory::class, 'fromArray']
        );
    }
}
