<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http;

/**
 * Immutable generic metadata carried with a request for logging and diagnostics.
 *
 * @api
 */
final class HttpRequestContext
{
    /** @var array<string, mixed> */
    private array $values;

    /** @param array<string, mixed> $values Context values that are not sent to the provider. */
    private function __construct(array $values)
    {
        $this->values = $values;
    }

    /** Creates an empty request context. */
    public static function empty(): self
    {
        return new self([]);
    }

    /** @param array<string, mixed> $values Context values that are not sent to the provider. */
    public static function fromArray(array $values): self
    {
        return new self($values);
    }

    /** @return array<string, mixed> Context values for logging and diagnostics. */
    public function toArray(): array
    {
        return $this->values;
    }
}
