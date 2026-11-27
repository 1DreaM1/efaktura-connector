<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway;

/** Route templates declared by the Peppol Gateway API contract. */
final class PeppolGatewayApiPaths
{
    /** Path for remote document validation. */
    public const VALIDATE_DOCUMENT = '/v1/validate-document';
    /** Path for submitting one or more documents through the Peppol Gateway. */
    public const SEND_DOCUMENTS = '/v1/send-documents';
    /** Path for retrieving a participant profile. */
    public const PARTICIPANT = '/v1/participants/{participantId}';
    /** Path for checking whether a participant receives a document type and process. */
    public const DISCOVERY_RECEIVES = '/v1/discovery/receives';
    /** Collection path for webhook registration operations. */
    public const WEBHOOKS = '/v1/webhooks';
    /** Path for reading, updating or deleting one webhook registration. */
    public const WEBHOOK = '/v1/webhooks/{webhookId}';
    /** Path for retrieving a sent document's tracking status. */
    public const SENT_DOCUMENT = '/v1/sent-documents/{identifier}';
    /** Path for listing message-level statuses for a sent document. */
    public const MESSAGE_LEVEL_STATUSES = self::SENT_DOCUMENT . '/message-level-statuses';
    /** Path for downloading one message-level status content document. */
    public const MESSAGE_LEVEL_STATUS_CONTENT = self::MESSAGE_LEVEL_STATUSES . '/{statusIdentifier}/content';
}
