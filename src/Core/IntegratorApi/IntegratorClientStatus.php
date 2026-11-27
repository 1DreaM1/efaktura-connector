<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;

/** OAuth client lifecycle value declared by the Integrator API. */
final class IntegratorClientStatus
{
    /** Client lifecycle value indicating provider approval. */
    public const APPROVED = 'APPROVED';
    /** Client lifecycle value indicating provider deletion. */
    public const DELETED = 'DELETED';

    /** @var string[] */
    public const SUPPORTED_STATUSES = [self::APPROVED, self::DELETED];

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates a status value from one of the lifecycle states declared by the API.
     *
     * @throws InvalidInputException when the status is not supported.
     */
    public static function fromString(string $status): self
    {
        if (! self::isSupported($status)) {
            throw new InvalidInputException('Integrator client status is not supported.');
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
            throw new InvalidInputException('Integrator client status must not be empty.');
        }

        return new self($status);
    }

    /** Returns the provider status string. */
    public function value(): string
    {
        return $this->value;
    }

    /** Whether this client status is part of the vocabulary known by this version. */
    public function isKnown(): bool
    {
        return self::isSupported($this->value);
    }

    /** Returns whether the status is declared by the API contract. */
    public static function isSupported(string $status): bool
    {
        return in_array($status, self::SUPPORTED_STATUSES, true);
    }

    /** @return string[] Client lifecycle statuses declared by the API. */
    public static function values(): array
    {
        return self::SUPPORTED_STATUSES;
    }
}
