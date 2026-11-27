<?php

namespace DreaM1\EfakturaConnector\Support;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** @internal Validates and normalizes arrays of typed values at package boundaries. */
final class TypedArray
{
    /**
     * Validates every item and returns the collection with consecutive numeric keys.
     *
     * @template T of object
     * @param  array<array-key, mixed> $items   Items to validate.
     * @param  class-string<T>         $type    Expected item class.
     * @param  string                  $message Diagnostic message for an invalid item.
     * @return list<T>
     * @throws InvalidInputException   when an item is not an instance of the expected type.
     */
    public static function valuesOfType(array $items, string $type, string $message): array
    {
        foreach ($items as $item) {
            if (! $item instanceof $type) {
                throw new InvalidInputException($message);
            }
        }

        return array_values($items);
    }
}
