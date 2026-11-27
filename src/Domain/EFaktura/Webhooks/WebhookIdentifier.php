<?php

namespace DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;

/** Immutable, validated identifier for a provider webhook registration. */
final class WebhookIdentifier
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }
    /**
     * Creates an identifier, rejecting blank values while preserving the supplied text.
     *
     * @param  string                $identifier Provider webhook identifier.
     * @return self
     * @throws InvalidInputException when the identifier is blank
     */
    public static function fromString(string $identifier): self
    {
        NonBlankString::assertNotBlank($identifier, 'Webhook id must not be empty.');

        return new self($identifier);
    }

    /** Returns the original identifier string. */
    public function value(): string
    {
        return $this->value;
    }
}
