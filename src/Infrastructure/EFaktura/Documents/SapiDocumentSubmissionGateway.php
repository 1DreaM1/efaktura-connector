<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents;

use DreaM1\EfakturaConnector\Core\Contracts\DocumentSubmissionGatewayInterface;
use DreaM1\EfakturaConnector\Core\Documents\DocumentSubmissionResult;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentRequest;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\SendDocumentEndpoint;

/** Submits an outbound payload through the SAPI send-document endpoint. */
final class SapiDocumentSubmissionGateway implements DocumentSubmissionGatewayInterface
{
    private SendDocumentEndpoint $sendDocument;

    /**
     * @param SendDocumentEndpoint $sendDocument Endpoint used to submit the document.
     */
    public function __construct(SendDocumentEndpoint $sendDocument)
    {
        $this->sendDocument = $sendDocument;
    }

    /**
     * Sends the prepared request and returns the provider response.
     *
     * @param  SendDocumentRequest          $request Routing metadata, XML content, participant and idempotency key.
     * @return DocumentSubmissionResult
     * @throws ProviderApiException         when the provider operation fails or is deferred
     * @throws RequestEncodingException     when the API request body cannot be encoded
     * @throws RequestCoordinationException when local request
     *                                      coordination cannot be established
     */
    public function send(SendDocumentRequest $request): DocumentSubmissionResult
    {
        $response = $this->sendDocument->send($request);

        return $response;
    }
}
