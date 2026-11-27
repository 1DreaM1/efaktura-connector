<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\Documents\SentDocumentReference;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentStatus;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\MessageStatuses\MessageLevelStatusContent;
use DreaM1\EfakturaConnector\Core\MessageStatuses\ProviderMessageLevelStatusResponse;
use DreaM1\EfakturaConnector\Domain\EFaktura\MessageLevelStatusIdentifier;

/** Retrieves delivery and message-level status for documents submitted through Peppol. */
interface DocumentTrackingProviderInterface
{
    /**
     * Gets the current provider status for a sent document.
     *
     * @param  SentDocumentReference        $document Provider reference for the submitted document.
     * @return SentDocumentStatus
     * @throws ProviderApiException         when the provider request fails or its response is invalid
     * @throws RequestCoordinationException when local HTTP request coordination cannot be established
     */
    public function getSentDocumentStatus(SentDocumentReference $document): SentDocumentStatus;

    /**
     * Lists message-level status records reported for a sent document.
     *
     * @param  SentDocumentReference              $document Provider reference for the submitted document.
     * @return ProviderMessageLevelStatusResponse
     * @throws ProviderApiException               when the provider request fails or its response is invalid
     * @throws RequestCoordinationException       when local HTTP request coordination cannot be established
     */
    public function getMessageLevelStatuses(SentDocumentReference $document): ProviderMessageLevelStatusResponse;

    /**
     * Downloads the XML payload for one message-level status record.
     *
     * @param  SentDocumentReference        $document         Provider reference for the submitted document.
     * @param  MessageLevelStatusIdentifier $statusIdentifier Identifier of the message-level status record to download.
     * @return MessageLevelStatusContent
     * @throws ProviderApiException         when the provider request fails or its response is invalid
     * @throws RequestCoordinationException when local HTTP request coordination cannot be established
     */
    public function downloadMessageLevelStatus(
        SentDocumentReference $document,
        MessageLevelStatusIdentifier $statusIdentifier
    ): MessageLevelStatusContent;
}
