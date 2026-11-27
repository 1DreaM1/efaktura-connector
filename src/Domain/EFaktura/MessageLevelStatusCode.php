<?php

namespace DreaM1\EfakturaConnector\Domain\EFaktura;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;

/** Provider message-level response code and delivery outcome classifiers. */
final class MessageLevelStatusCode
{
    /** Peppol message-level status code 'AB' for a forwarded message. */
    public const FORWARDED = 'AB';
    /** Peppol message-level status code 'AP' for a delivered message. */
    public const DELIVERED = 'AP';
    /** Peppol message-level status code 'RE' for a failed delivery. */
    public const DELIVERY_FAILED = 'RE';

    private string $value;

    private function __construct(string $value)
    {
        if (! NonBlankString::isNotBlank($value)) {
            throw new InvalidInputException('Message-level status code must not be empty.');
        }

        $this->value = $value;
    }

    /**
     * Creates a code while canonicalizing its letters to uppercase. Unknown provider codes are preserved.
     *
     * @param  string                $code Provider message-level status code.
     * @return self
     * @throws InvalidInputException when the code is blank.
     */
    public static function fromString(string $code): self
    {
        return new self(strtoupper($code));
    }

    /** Returns the canonical provider code. */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Whether both objects represent the same provider code.
     *
     * @param  self $other Code to compare with this instance.
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Whether the status confirms delivery to the recipient.
     *
     * @param  string $code Code value to validate, map, or report.
     * @return bool
     */
    public static function isSuccess(string $code): bool
    {
        return strtoupper($code) === self::DELIVERED;
    }

    /**
     * Whether the provider reports a failed delivery.
     *
     * @param  string $code Code value to validate, map, or report.
     * @return bool
     */
    public static function isFailure(string $code): bool
    {
        return strtoupper($code) === self::DELIVERY_FAILED;
    }

    /**
     * Whether the status reports forwarding without a final delivery outcome.
     *
     * @param  string $code Code value to validate, map, or report.
     * @return bool
     */
    public static function isForwarded(string $code): bool
    {
        return strtoupper($code) === self::FORWARDED;
    }
}
