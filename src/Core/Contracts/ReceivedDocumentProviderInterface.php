<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\Documents\ListReceivedDocumentsRequest;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentAcknowledgement;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentPage;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentPayload;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentReference;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;

/** Lists, downloads and acknowledges documents received by a participant. */
interface ReceivedDocumentProviderInterface
{
    /**
     * Returns one inbox page; pass the previous page's token to continue pagination.
     *
     * @param  ListReceivedDocumentsRequest $request Filters and participant for the requested inbox page.
     * @return ReceivedDocumentPage
     * @throws ProviderApiException         when the provider request fails or its response is invalid
     * @throws RequestCoordinationException when local request
     *                                      coordination cannot be established
     */
    public function getInboxPage(ListReceivedDocumentsRequest $request): ReceivedDocumentPage;

    /**
     * Downloads the XML payload and provider-reported format of a document.
     *
     * @param  ReceivedDocumentReference    $document Reference identifying the downloaded provider document.
     * @return ReceivedDocumentPayload      XML payload and provider-reported format.
     * @throws ProviderApiException         when the provider request fails or its response is invalid
     * @throws RequestCoordinationException when local request
     *                                      coordination cannot be established
     */
    public function getDocumentPayload(ReceivedDocumentReference $document): ReceivedDocumentPayload;

    /**
     * Confirms receipt of a downloaded document to the provider.
     *
     * @param  ReceivedDocumentReference       $document Reference identifying the received document to acknowledge.
     * @return ReceivedDocumentAcknowledgement Provider acknowledgement fields.
     * @throws ProviderApiException            when the provider request fails or its response is invalid
     * @throws RequestCoordinationException    when local request
     *                                         coordination cannot be established
     */
    public function acknowledge(ReceivedDocumentReference $document): ReceivedDocumentAcknowledgement;
}
