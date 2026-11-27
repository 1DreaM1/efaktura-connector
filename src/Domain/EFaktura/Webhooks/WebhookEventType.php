<?php

namespace DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\StringValue;

/** Event names recognized by webhook consumers in this package. */
final class WebhookEventType
{
    use StringValue;

    /** Webhook event type 'sent_document.status_changed' for a sent document status change. */
    public const SENT_DOCUMENT_STATUS_CHANGED = 'sent_document.status_changed';
    /** Webhook event type 'inbox_document.received' for a received inbox document. */
    public const INBOX_DOCUMENT_RECEIVED = 'inbox_document.received';

    /** Event types recognized by webhook models and the provider API contract. */
    public const SUPPORTED_EVENTS = [
        self::SENT_DOCUMENT_STATUS_CHANGED,
        self::INBOX_DOCUMENT_RECEIVED,
    ];

    /** Whether the connector recognizes this event type. */
    public function isSupported(): bool
    {
        return in_array($this->value, self::SUPPORTED_EVENTS, true);
    }

    protected static function normalize(string $value): string
    {
        if (! NonBlankString::isNotBlank($value)) {
            throw new InvalidInputException('Webhook event type must not be empty.');
        }

        return $value;
    }
}
