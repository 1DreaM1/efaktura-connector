<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents;

use DreaM1\EfakturaConnector\Core\Contracts\PeppolGatewayDocumentSubmissionInterface;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentsRequest;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentsResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\SendDocumentsEndpoint;

/** Adapts Peppol Gateway batch submission to its Core contract. */
final class PeppolGatewayDocumentSubmission implements PeppolGatewayDocumentSubmissionInterface
{
    private SendDocumentsEndpoint $sendDocuments;

    /**
     * @param SendDocumentsEndpoint $sendDocuments Endpoint used to submit a batch.
     */
    public function __construct(SendDocumentsEndpoint $sendDocuments)
    {
        $this->sendDocuments = $sendDocuments;
    }

    /** {@inheritdoc} */
    public function send(SendDocumentsRequest $request): SendDocumentsResult
    {
        return $this->sendDocuments->send($request);
    }
}
