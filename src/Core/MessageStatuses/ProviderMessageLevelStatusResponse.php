<?php

namespace DreaM1\EfakturaConnector\Core\MessageStatuses;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** Message-level statuses returned for a sent document. */
final class ProviderMessageLevelStatusResponse
{
    private bool $validResponse;

    /** @var ProviderMessageLevelStatus[] */
    private array $items;

    private int $invalidItems;

    /**
     * @param ProviderMessageLevelStatus[] $items Items to include in the returned collection.
     */
    private function __construct(bool $validResponse, array $items, int $invalidItems)
    {
        if ($invalidItems < 0) {
            throw new InvalidInputException('Invalid message-status item count must not be negative.');
        }

        $this->validResponse = $validResponse;
        $this->items         = TypedArray::valuesOfType(
            $items,
            ProviderMessageLevelStatus::class,
            'Message-status responses must contain status values.'
        );
        $this->invalidItems = $invalidItems;
    }

    /**
     * Creates a value object from the supplied fields.
     * @param  ProviderMessageLevelStatus[] $items         Successfully parsed provider status records
     * @param  bool                         $validResponse Whether the provider response passed structural validation.
     * @param  int                          $invalidItems  Number of response items that could not be parsed.
     * @return self
     * @throws InvalidInputException        when an item has the wrong type or the invalid-item count is negative
     */
    public static function create(bool $validResponse, array $items, int $invalidItems): self
    {
        return new self($validResponse, $items, $invalidItems);
    }

    /**
     * Whether the provider status response passed validation.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->validResponse;
    }

    /**
     * Returns the items.
     * @return ProviderMessageLevelStatus[]
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Returns the invalid items.
     *
     * @return int
     */
    public function invalidItems(): int
    {
        return $this->invalidItems;
    }
}
