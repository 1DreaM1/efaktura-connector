<?php

namespace DreaM1\EfakturaConnector\Core\Webhooks;

use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookEventType;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** Event type identifiers supported by the connector's webhook models. */
final class WebhookEvents
{
    /**
     * Checks event type values and returns them as a zero-based list.
     *
     * @param  WebhookEventType[]    $events Event values to validate.
     * @return WebhookEventType[]
     * @throws InvalidInputException when an item is not a supported webhook event type.
     */
    public static function normalize(array $events): array
    {
        $normalizedEvents = TypedArray::valuesOfType(
            $events,
            WebhookEventType::class,
            'Webhook event is not supported.'
        );

        foreach ($normalizedEvents as $event) {
            if (! $event->isSupported()) {
                throw new InvalidInputException('Webhook event is not supported.');
            }
        }

        return $normalizedEvents;
    }

    /**
     * Converts event value objects to provider string fields at the HTTP boundary.
     * @param  WebhookEventType[] $events Values to serialize.
     * @return string[]
     */
    public static function values(array $events): array
    {
        return array_map(
            static function (WebhookEventType $event): string {
                return $event->value();
            },
            $events
        );
    }
}
