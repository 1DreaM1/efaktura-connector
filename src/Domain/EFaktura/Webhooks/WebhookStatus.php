<?php

namespace DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;

/** Supported lifecycle values for provider webhook registrations. */
final class WebhookStatus
{
    /** Webhook registration status value 'ACTIVE'. */
    public const ACTIVE = 'ACTIVE';
    /** Webhook registration status value 'DISABLED'. */
    public const DISABLED = 'DISABLED';

    /** Lifecycle statuses recognized by webhook models and the provider API contract. */
    public const SUPPORTED_STATUSES = [self::ACTIVE, self::DISABLED];

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates a status value from a provider or application string.
     *
     * @param  string                $status Supported webhook lifecycle status.
     * @return self
     * @throws InvalidInputException when the status is unsupported
     */
    public static function fromString(string $status): self
    {
        if (! self::isSupported($status)) {
            throw new InvalidInputException('Unsupported webhook status.');
        }

        return new self($status);
    }

    /**
     * Preserves a non-blank status returned by the provider, including values not known by this version.
     *
     * @throws InvalidInputException when the provider status is blank
     */
    public static function fromProvider(string $status): self
    {
        if (! NonBlankString::isNotBlank($status)) {
            throw new InvalidInputException('Webhook status must not be empty.');
        }

        return new self($status);
    }

    /** Returns the provider status string. */
    public function value(): string
    {
        return $this->value;
    }

    /** Whether this webhook status is part of the vocabulary known by this version. */
    public function isKnown(): bool
    {
        return self::isSupported($this->value);
    }

    /**
     * Whether the value is one of the lifecycle statuses understood by the connector.
     *
     * @param  string $status Provider-reported or domain status value.
     * @return bool
     */
    public static function isSupported(string $status): bool
    {
        return in_array($status, self::SUPPORTED_STATUSES, true);
    }
}
