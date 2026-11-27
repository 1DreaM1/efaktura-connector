<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\Documents\DocumentSubmissionResult;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentRequest;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;

/** Submits an outbound document through the configured eFaktura provider. */
interface DocumentSubmissionGatewayInterface
{
    /**
     * Sends a document using the supplied stable key to make provider retries idempotent.
     *
     * The host should retain and reuse the same key when resolving an ambiguous timeout or retrying
     * the submission. A new key represents a distinct submission and can create a duplicate document.
     * The connector does not automatically replay this operation because the provider may have
     * accepted the document even when the response was lost.
     *
     * @param  SendDocumentRequest          $request Input and participant context for the provider request.
     * @return DocumentSubmissionResult
     * @throws ProviderApiException         when the provider request fails
     * @throws RequestEncodingException     when the API request body cannot be encoded
     * @throws RequestCoordinationException when local request
     *                                      coordination cannot be established
     */
    public function send(SendDocumentRequest $request): DocumentSubmissionResult;
}
