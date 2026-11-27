<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Support\JsonArrayDecoder;

/** @internal Reads JSON lists and nested objects for ProviderResponseReader. */
final class ProviderResponseCollectionReader
{
    /** @var array<string, mixed> */
    private array $data;
    /** @var callable(string): InvalidProviderResponseException */
    private $invalidField;

    /**
     * @param array<string, mixed>                               $data         Response fields to read.
     * @param callable(string): InvalidProviderResponseException $invalidField Builds the schema-aware field exception.
     */
    public function __construct(array $data, callable $invalidField)
    {
        $this->data         = $data;
        $this->invalidField = $invalidField;
    }

    /** Returns a required nested JSON object as a schema-aware reader. */
    public function requiredObject(string $field, string $schema): ProviderResponseReader
    {
        $value = JsonArrayDecoder::objectAsArray($this->data[$field] ?? null);

        if ($value === null) {
            throw ($this->invalidField)($field);
        }

        return new ProviderResponseReader($value, $schema);
    }

    /** @return ProviderResponseReader[] */
    public function requiredObjectList(string $field, string $itemSchema): array
    {
        $items = [];

        foreach ($this->requiredList($field) as $item) {
            $itemData = JsonArrayDecoder::objectAsArray($item);

            if ($itemData === null) {
                throw ($this->invalidField)($field);
            }

            $items[] = new ProviderResponseReader($itemData, $itemSchema);
        }

        return $items;
    }

    /**
     * Returns a required JSON list, leaving item validation to its caller.
     *
     * @return list<mixed>
     */
    public function requiredList(string $field): array
    {
        $value = $this->data[$field] ?? null;

        if (! JsonArrayDecoder::isList($value)) {
            throw ($this->invalidField)($field);
        }

        return $value;
    }
}
