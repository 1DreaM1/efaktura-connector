<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Documents\SentDocumentReference;
use DreaM1\EfakturaConnector\Core\MessageStatuses\MessageLevelStatusContent;
use DreaM1\EfakturaConnector\Domain\EFaktura\MessageLevelStatusIdentifier;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;

/**
 * GET /v1/sent-documents/{identifier}/message-level-statuses/{statusIdentifier}/content → 200 application/xml, mapped to MessageLevelStatusContent
 */
class GetMessageLevelStatusContentEndpoint extends AbstractEndpoint
{
    /**
     * Sends the request to the provider endpoint and returns its mapped response.
     *
     * @param  SentDocumentReference        $document         Sent document and participant being queried.
     * @param  MessageLevelStatusIdentifier $statusIdentifier Message-level status to download.
     * @return MessageLevelStatusContent
     */
    public function send(
        SentDocumentReference $document,
        MessageLevelStatusIdentifier $statusIdentifier
    ): MessageLevelStatusContent {
        return new MessageLevelStatusContent($this->api->xml(
            ApiRequest::get(PeppolGatewayApiPaths::MESSAGE_LEVEL_STATUS_CONTENT, [
                'identifier'       => $document->identifier()->value(),
                'statusIdentifier' => $statusIdentifier->value(),
            ])
                ->acceptXml()
                ->forParticipant($document->participant())
                ->withContext($document->context())
        ));
    }
}
