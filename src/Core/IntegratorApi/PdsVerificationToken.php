<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Support\RefusesSerialization;

/** Sensitive PDS verification token submitted during participant onboarding. */
final class PdsVerificationToken
{
    use RefusesSerialization;

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Redacts the token from var_dump() output.
     *
     * @return array{value: string} Debug representation containing the redaction marker.
     */
    public function __debugInfo(): array
    {
        return ['value' => '[redacted]'];
    }

    /** Creates a token from its provider-supplied string representation. */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /** Returns the token for explicit use in PDS subject submission. */
    public function reveal(): string
    {
        return $this->value;
    }
}
