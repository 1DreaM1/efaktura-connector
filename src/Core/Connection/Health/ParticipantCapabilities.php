<?php

namespace DreaM1\EfakturaConnector\Core\Connection\Health;

use ArrayIterator;
use Countable;
use DreaM1\EfakturaConnector\Support\TypedArray;
use IteratorAggregate;
use Traversable;

/**
 * Collection of the document types and processes a participant advertises.
 *
 * @implements IteratorAggregate<int, ParticipantCapability>
 */
final class ParticipantCapabilities implements Countable, IteratorAggregate
{
    /** @var ParticipantCapability[] Capabilities in the order returned by the provider. */
    private array $items;

    /**
     * Creates a new ParticipantCapabilities value.
     * @param ParticipantCapability[] $items Items to include in the returned collection.
     */
    public function __construct(array $items)
    {
        $this->items = TypedArray::valuesOfType(
            $items,
            ParticipantCapability::class,
            'Participant capabilities must contain capability values.'
        );
    }

    /**
     * Creates an empty collection.
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Returns the number of values in the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Returns an iterator over the participant capabilities.
     * @return Traversable<int, ParticipantCapability>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
