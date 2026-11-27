<?php

namespace DreaM1\EfakturaConnector\Core\Webhooks;

use DreaM1\EfakturaConnector\Support\TypedArray;

/** Collection of webhook registrations returned by a provider response. */
final class WebhookList
{
    /** @var Webhook[] */
    private array $items;

    /**
     * Creates a new WebhookList value.
     * @param Webhook[] $items Items to include in the returned collection.
     */
    public function __construct(array $items)
    {
        $this->items = TypedArray::valuesOfType(
            $items,
            Webhook::class,
            'Webhook lists must contain webhook values.'
        );
    }

    /**
     * Returns the items.
     * @return Webhook[]
     */
    public function items(): array
    {
        return $this->items;
    }
}
