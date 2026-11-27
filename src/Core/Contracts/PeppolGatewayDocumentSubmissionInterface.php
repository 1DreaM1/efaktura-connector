<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\Documents\SendDocumentsRequest;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentsResult;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestBodyTooLargeException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;

/** Submits a batch of outbound documents through the Peppol Gateway API. */
interface PeppolGatewayDocumentSubmissionInterface
{
    /**
     * Sends a batch using the supplied stable key to make repeated provider submissions idempotent.
     * The connector does not automatically replay this operation because an ambiguous network result
     * can mean that the Gateway accepted the batch.
     *
     * @param  SendDocumentsRequest         $request Batch and participant context for the provider request.
     * @return SendDocumentsResult
     * @throws ProviderApiException         when the provider request fails
     * @throws RequestBodyTooLargeException when the encoded batch exceeds the local request size limit
     * @throws RequestEncodingException     when the API request body cannot be encoded
     * @throws RequestCoordinationException when local request coordination cannot be established
     */
    public function send(SendDocumentsRequest $request): SendDocumentsResult;
}
