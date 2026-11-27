<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Pagination\PageToken;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\Timestamp;

/**
 * Strict reader of one JSON object of a documented API schema.
 *
 * Every accessor checks the JSON type declared in the OpenAPI schema and reports the schema
 * and field name when the provider returns a value of another shape.
 * Required accessors reject missing fields; optional accessors accept omission but reject explicit
 * null; nullable accessors accept both omission and explicit null.
 */
final class ProviderResponseReader
{
    /** @var array<string, mixed> Decoded provider response object. */
    private array $data;
    private string $schema;
    private ProviderResponseCollectionReader $collections;

    /**
     * @param array<string, mixed> $data   Decoded provider response object to validate against the schema.
     * @param string               $schema Response schema name used to identify validation errors.
     */
    public function __construct(array $data, string $schema)
    {
        $this->data        = $data;
        $this->schema      = $schema;
        $this->collections = new ProviderResponseCollectionReader(
            $data,
            function (string $field): InvalidProviderResponseException {
                return $this->invalid($field);
            }
        );
    }

    /**
     * Returns the decoded response object exactly as received.
     * @return array<string, mixed> The object exactly as received.
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Returns a required non-blank string field.
     *
     * @param  string                           $field Response field name to read or validate.
     * @return string
     * @throws InvalidProviderResponseException when the field is absent, blank or not a string
     */
    public function requiredString(string $field): string
    {
        $value = $this->data[$field] ?? null;
        return $this->validatedNonBlankString($value, $field);
    }

    /**
     * An absent field returns null; a present non-string value, including null, is invalid.
     *
     * @param  string      $field Response field name to read or validate.
     * @return string|null
     */
    public function optionalString(string $field): ?string
    {
        return $this->optionalField($field, false, [$this, 'requiredString']);
    }

    /**
     * Reads a continuation token: an absent, null, empty or whitespace-only value means there is no next page.
     *
     * The contract says the token is absent on the last page; treating an empty value the same way keeps a last page
     * usable if a provider sends it that way instead of failing the whole response.
     *
     * @param  string                           $field Response field name holding the token.
     * @return PageToken|null
     * @throws InvalidProviderResponseException when a present value is neither null nor a string
     */
    public function optionalPageToken(string $field): ?PageToken
    {
        $value = $this->data[$field] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw $this->invalid($field);
        }

        return NonBlankString::isNotBlank($value) ? PageToken::fromString($value) : null;
    }

    /**
     * Returns an optional string field while preserving a blank string.
     *
     * @param  string                           $field Response field name to read or validate.
     * @return string|null
     * @throws InvalidProviderResponseException when a present value is not a string
     */
    public function optionalStringAllowBlank(string $field): ?string
    {
        if (! array_key_exists($field, $this->data)) {
            return null;
        }

        $value = $this->data[$field];

        if (! is_string($value)) {
            throw $this->invalid($field);
        }

        return $value;
    }

    /**
     * Returns an optional non-blank string field.
     *
     * @param  string                           $field Response field name to read or validate.
     * @return string|null
     * @throws InvalidProviderResponseException when a present field is blank or not a string
     */
    public function optionalNonBlankString(string $field): ?string
    {
        $value = $this->optionalString($field);

        if ($value !== null && ! NonBlankString::isNotBlank($value)) {
            throw $this->invalid($field);
        }

        return $value;
    }

    /**
     * Missing or null fields return null; any other present value must be a string.
     *
     * @param  string                           $field Response object key to read.
     * @return string|null                      Field value, or null when absent or explicitly null.
     * @throws InvalidProviderResponseException when a non-null value is not a string
     */
    public function nullableString(string $field): ?string
    {
        return $this->optionalField($field, true, [$this, 'requiredString']);
    }

    /**
     * Returns a required string field after checking its allowed values.
     * @param  string                           $field   Response field name to read or validate.
     * @param  string[]                         $allowed Set of values accepted for the field.
     * @return string
     * @throws InvalidProviderResponseException when the field is missing, blank or not in the allowed set
     */
    public function requiredEnum(string $field, array $allowed): string
    {
        $value = $this->requiredString($field);

        if (! in_array($value, $allowed, true)) {
            throw $this->invalid($field);
        }

        return $value;
    }

    /**
     * Returns an optional string field after checking its allowed values.
     * @param  string                           $field   Response field name to read or validate.
     * @param  string[]                         $allowed Set of values accepted for the field.
     * @return string|null
     * @throws InvalidProviderResponseException when a present value is not a string or is not in the allowed set
     */
    public function optionalEnum(string $field, array $allowed): ?string
    {
        $value = $this->optionalString($field);

        if ($value !== null && ! in_array($value, $allowed, true)) {
            throw $this->invalid($field);
        }

        return $value;
    }

    /**
     * Returns a required ISO 8601 timestamp with its parsed instant.
     *
     * @param  string                           $field Response field name to read or validate.
     * @return Timestamp
     * @throws InvalidProviderResponseException when the field is missing or is not a valid timestamp
     */
    public function requiredTimestamp(string $field): Timestamp
    {
        return $this->parseTimestamp($this->requiredString($field), $field);
    }

    /**
     * Returns null for an absent optional timestamp, or a parsed value when present.
     *
     * @param  string                           $field Response field name to read or validate.
     * @return Timestamp|null
     * @throws InvalidProviderResponseException when a present value is not a string or valid timestamp
     */
    public function optionalTimestamp(string $field): ?Timestamp
    {
        $value = $this->optionalString($field);

        return $value !== null ? $this->parseTimestamp($value, $field) : null;
    }

    /**
     * Returns a required JSON boolean field.
     *
     * @param  string                           $field Response field name to read or validate.
     * @return bool
     * @throws InvalidProviderResponseException when the field is missing or is not a JSON boolean
     */
    public function requiredBool(string $field): bool
    {
        $value = $this->data[$field] ?? null;

        if (! is_bool($value)) {
            throw $this->invalid($field);
        }

        return $value;
    }

    /**
     * An absent field returns null; a present value, including null, must be a JSON boolean.
     *
     * @param  string    $field Response field name to read or validate.
     * @return bool|null
     */
    public function optionalBool(string $field): ?bool
    {
        return $this->optionalField($field, false, [$this, 'requiredBool']);
    }

    /**
     * Returns a required integer field.
     *
     * @param  string                           $field Response field name to read or validate.
     * @return int
     * @throws InvalidProviderResponseException when the field is missing or is not an integer
     */
    public function requiredInt(string $field): int
    {
        $value = $this->data[$field] ?? null;

        if (! is_int($value)) {
            throw $this->invalid($field);
        }

        return $value;
    }

    /**
     * An absent field returns null; a present value, including null, must be an integer.
     *
     * @param  string   $field Response field name to read or validate.
     * @return int|null
     */
    public function optionalInt(string $field): ?int
    {
        return $this->optionalField($field, false, [$this, 'requiredInt']);
    }

    /**
     * Returns a required list of non-empty string values.
     *
     * @param  string                           $field Response field name to read or validate.
     * @return string[]
     * @throws InvalidProviderResponseException when the field is not a list of non-empty strings
     */
    public function requiredStringList(string $field): array
    {
        $strings = [];

        foreach ($this->requiredList($field) as $item) {
            $strings[] = $this->validatedNonBlankString($item, $field);
        }

        return $strings;
    }

    /**
     * Returns a required list of strings whose values are in the allowed set.
     *
     * @param  string                           $field   Response field name to read or validate.
     * @param  string[]                         $allowed Set of values accepted for the field.
     * @return string[]
     * @throws InvalidProviderResponseException when the field is not a list of allowed strings
     */
    public function requiredEnumList(string $field, array $allowed): array
    {
        $values = $this->requiredStringList($field);

        foreach ($values as $value) {
            if (! in_array($value, $allowed, true)) {
                throw $this->invalid($field);
            }
        }

        return $values;
    }

    /**
     * Returns a required nested JSON object as a schema-aware reader.
     *
     * @param  string                           $field  Response field name to read or validate.
     * @param  string                           $schema Response schema name used to identify validation errors.
     * @return self
     * @throws InvalidProviderResponseException when the field is missing or is not a JSON object
     */
    public function requiredObject(string $field, string $schema): self
    {
        return $this->collections->requiredObject($field, $schema);
    }

    /**
     * An absent field returns null; a present value, including null, must be a JSON object.
     *
     * @param  string    $field  Response field name to read or validate.
     * @param  string    $schema Response schema name used to identify validation errors.
     * @return self|null
     */
    public function optionalObject(string $field, string $schema): ?self
    {
        return $this->optionalField(
            $field,
            false,
            function (string $field) use ($schema): self {
                return $this->requiredObject($field, $schema);
            }
        );
    }

    /**
     * Missing or null fields return null; any other present value must be a JSON object.
     *
     * @param  string                           $field  Response object key to read.
     * @param  string                           $schema Schema name used to identify validation errors.
     * @return self|null                        Schema-aware reader, or null when absent or explicitly null.
     * @throws InvalidProviderResponseException when a non-null value is not a JSON object
     */
    public function nullableObject(string $field, string $schema): ?self
    {
        return $this->optionalField(
            $field,
            true,
            function (string $field) use ($schema): self {
                return $this->requiredObject($field, $schema);
            }
        );
    }

    /**
     * Returns a required list of nested schema-aware objects.
     *
     * @param  string                           $field      Response field name to read or validate.
     * @param  string                           $itemSchema Schema name used to validate each object in the list.
     * @return self[]
     * @throws InvalidProviderResponseException when the field is not a list of JSON objects
     */
    public function requiredObjectList(string $field, string $itemSchema): array
    {
        return $this->collections->requiredObjectList($field, $itemSchema);
    }

    /**
     * Returns an optional non-null list of nested schema-aware objects.
     *
     * @param  string                           $field      Response field name to read or validate.
     * @param  string                           $itemSchema Schema name used to validate each object in the list.
     * @return self[]|null                      Missing fields return null; explicit null is invalid.
     * @throws InvalidProviderResponseException when a present value is not a list of JSON objects
     */
    public function optionalObjectList(string $field, string $itemSchema): ?array
    {
        return $this->optionalField(
            $field,
            false,
            function (string $field) use ($itemSchema): array {
                return $this->requiredObjectList($field, $itemSchema);
            }
        );
    }

    /**
     * Raw JSON array for callers that validate list items one by one.
     *
     * @param  string                           $field Response field name to read or validate.
     * @return array<int, mixed>|null
     * @throws InvalidProviderResponseException when a present value, including null, is not a JSON list
     */
    public function optionalList(string $field): ?array
    {
        return $this->optionalField($field, false, [$this, 'requiredList']);
    }

    /** Whether the response object does not contain the field. */
    private function isMissing(string $field): bool
    {
        // array_key_exists distinguishes an absent field from a present field whose value is null.
        return ! array_key_exists($field, $this->data);
    }

    /** Whether a field is absent or explicitly null. */
    private function isMissingOrNull(string $field): bool
    {
        return $this->isMissing($field) || $this->data[$field] === null;
    }

    /**
     * Reads an optional field through its required reader after applying the missing/null policy.
     *
     * @template T
     * @param  string              $field             Response field name to read.
     * @param  bool                $allowExplicitNull Whether an explicit null counts as absent.
     * @param  callable(string): T $reader            Required reader for the field type.
     * @return T|null
     */
    private function optionalField(string $field, bool $allowExplicitNull, callable $reader)
    {
        if ($allowExplicitNull ? $this->isMissingOrNull($field) : $this->isMissing($field)) {
            return null;
        }

        return $reader($field);
    }

    /**
     * Returns a required JSON list, leaving item validation to the caller.
     * @return array<int, mixed>
     */
    private function requiredList(string $field): array
    {
        return $this->collections->requiredList($field);
    }

    /** @param mixed $value */
    private function validatedNonBlankString($value, string $field): string
    {
        if (! is_string($value) || ! NonBlankString::isNotBlank($value)) {
            throw $this->invalid($field);
        }

        return $value;
    }

    /** Creates a schema-aware exception for a response field that failed validation. */
    private function invalid(string $field): InvalidProviderResponseException
    {
        return new InvalidProviderResponseException(
            sprintf('Provider response %s has an invalid field "%s".', $this->schema, $field),
            $this->schema,
            $field
        );
    }

    /** Parses one non-null response timestamp or raises the schema-aware field error. */
    private function parseTimestamp(string $value, string $field): Timestamp
    {
        $timestamp = Timestamp::tryParseRfc3339($value);

        if ($timestamp === null) {
            throw $this->invalid($field);
        }

        return $timestamp;
    }
}
