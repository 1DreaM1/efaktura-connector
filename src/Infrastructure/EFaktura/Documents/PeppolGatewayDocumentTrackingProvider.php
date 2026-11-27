<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents;

use DreaM1\EfakturaConnector\Core\Contracts\DocumentTrackingProviderInterface;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentReference;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentStatus;
use DreaM1\EfakturaConnector\Core\MessageStatuses\MessageLevelStatusContent;
use DreaM1\EfakturaConnector\Core\MessageStatuses\ProviderMessageLevelStatusResponse;
use DreaM1\EfakturaConnector\Domain\EFaktura\MessageLevelStatusIdentifier;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetMessageLevelStatusContentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetSentDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\ListMessageLevelStatusesEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\SentDocumentStatusMapper;

/** Adapts Peppol Gateway status endpoints to document tracking contracts. */
class PeppolGatewayDocumentTrackingProvider implements DocumentTrackingProviderInterface
{
    private GetSentDocumentEndpoint $sentDocument;
    private ListMessageLevelStatusesEndpoint $messageLevelStatuses;
    private GetMessageLevelStatusContentEndpoint $messageLevelStatusContent;

    /**
     * @param GetSentDocumentEndpoint              $sentDocument              Submitted document value to track or process.
     * @param ListMessageLevelStatusesEndpoint     $messageLevelStatuses      Message-level status records reported by the provider.
     * @param GetMessageLevelStatusContentEndpoint $messageLevelStatusContent Downloaded content for the message-level status.
     */
    public function __construct(
        GetSentDocumentEndpoint $sentDocument,
        ListMessageLevelStatusesEndpoint $messageLevelStatuses,
        GetMessageLevelStatusContentEndpoint $messageLevelStatusContent
    ) {
        $this->sentDocument              = $sentDocument;
        $this->messageLevelStatuses      = $messageLevelStatuses;
        $this->messageLevelStatusContent = $messageLevelStatusContent;
    }

    /**
     * Returns the current provider status for a sent document.
     *
     * @param  SentDocumentReference $document Participant and sent document identifier used to retrieve provider status.
     * @return SentDocumentStatus
     *                               {@inheritdoc}
     */
    public function getSentDocumentStatus(SentDocumentReference $document): SentDocumentStatus
    {
        return $this->sentDocument->send($document);
    }

    /**
     * Returns message-level delivery statuses for a sent document.
     *
     * @param  SentDocumentReference              $document Sent document reference used to retrieve message-level statuses.
     * @return ProviderMessageLevelStatusResponse
     *                                            {@inheritdoc}
     */
    public function getMessageLevelStatuses(SentDocumentReference $document): ProviderMessageLevelStatusResponse
    {
        return $this->messageLevelStatuses->send($document);
    }

    /**
     * Downloads the XML detail for one message-level status.
     *
     * @param  SentDocumentReference        $document         Sent document reference used to retrieve the specified
     *                                                        status's XML detail.
     * @param  MessageLevelStatusIdentifier $statusIdentifier Identifier of the message-level status record.
     * @return MessageLevelStatusContent
     *                                      {@inheritdoc}
     */
    public function downloadMessageLevelStatus(
        SentDocumentReference $document,
        MessageLevelStatusIdentifier $statusIdentifier
    ): MessageLevelStatusContent {
        // The XML transport checks well-formedness; this step does not run UBL or Peppol business validation.
        return $this->messageLevelStatusContent->send($document, $statusIdentifier);
    }
}
