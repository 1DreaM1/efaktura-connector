<?php

namespace DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\StringValue;

/** Opaque identifier assigned to one provider webhook event. */
final class WebhookEventId
{
    use StringValue;

    protected static function normalize(string $value): string
    {
        if (! NonBlankString::isNotBlank($value)) {
            throw new InvalidInputException('Webhook event ID must not be empty.');
        }

        return $value;
    }
}
