<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents;

use DreaM1\EfakturaConnector\Core\Contracts\ReceivedDocumentProviderInterface;
use DreaM1\EfakturaConnector\Core\Documents\ListReceivedDocumentsRequest;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentAcknowledgement;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentPage;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentPayload;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentReference;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\AcknowledgeReceivedDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\GetReceivedDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\ListReceivedDocumentsEndpoint;

/** Lists, downloads and acknowledges received documents through SAPI endpoints. */
class SapiReceivedDocumentProvider implements ReceivedDocumentProviderInterface
{
    private ListReceivedDocumentsEndpoint $receivedDocuments;
    private GetReceivedDocumentEndpoint $receivedDocument;
    private AcknowledgeReceivedDocumentEndpoint $acknowledgeDocument;

    /**
     * @param ListReceivedDocumentsEndpoint       $receivedDocuments   Received document values to include in the page.
     * @param GetReceivedDocumentEndpoint         $receivedDocument    Received document value to parse or acknowledge.
     * @param AcknowledgeReceivedDocumentEndpoint $acknowledgeDocument Endpoint used to acknowledge receipt of a document.
     */
    public function __construct(
        ListReceivedDocumentsEndpoint $receivedDocuments,
        GetReceivedDocumentEndpoint $receivedDocument,
        AcknowledgeReceivedDocumentEndpoint $acknowledgeDocument
    ) {
        $this->receivedDocuments   = $receivedDocuments;
        $this->receivedDocument    = $receivedDocument;
        $this->acknowledgeDocument = $acknowledgeDocument;
    }

    /**
     * Returns one page of received-document metadata.
     *
     * @param  ListReceivedDocumentsRequest $request Participant and filters for the requested page.
     * @return ReceivedDocumentPage
     *                                      {@inheritdoc}
     */
    public function getInboxPage(ListReceivedDocumentsRequest $request): ReceivedDocumentPage
    {
        // Fetch exactly one participant-scoped page; the provider's continuation token is returned unchanged.
        return $this->receivedDocuments->send($request);
    }

    /**
     * Downloads the XML payload for a received document.
     *
     * @param  ReceivedDocumentReference $document Participant and provider document ID used to download the payload.
     * @return ReceivedDocumentPayload
     *                                   {@inheritdoc}
     */
    public function getDocumentPayload(ReceivedDocumentReference $document): ReceivedDocumentPayload
    {
        // Preserve the provider payload and format; parsing, validation, and persistence remain separate operations.
        return $this->receivedDocument->send($document);
    }

    /**
     * Acknowledges receipt of a document to the provider.
     *
     * @param  ReceivedDocumentReference       $document Participant and provider document ID used to acknowledge receipt.
     * @return ReceivedDocumentAcknowledgement
     *                                         {@inheritdoc}
     */
    public function acknowledge(ReceivedDocumentReference $document): ReceivedDocumentAcknowledgement
    {
        // Acknowledgement is an explicit follow-up call; downloading the payload does not mark it received.
        return $this->acknowledgeDocument->send($document);
    }
}
