<?php

namespace DreaM1\EfakturaConnector\Core\ProviderErrors;

/** Opaque machine-readable code supplied in a provider error envelope. */
final class ProviderErrorCode
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /** Preserves the provider's code verbatim, including values unknown to this package. */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /** Returns the exact provider code for diagnostics or a serialization boundary. */
    public function value(): string
    {
        return $this->value;
    }
}
