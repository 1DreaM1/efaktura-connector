<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** A severity value supported by local UBL validation findings. */
final class UblValidationSeverity
{
    /** Finding severity that makes the local validation result invalid. */
    public const ERROR = 'error';
    /** Finding severity that reports a concern without invalidating the result. */
    public const WARNING = 'warning';
    /** Finding severity for informational validation output. */
    public const INFO = 'info';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates one of the supported validation severities.
     *
     * @throws InvalidInputException when the severity is not supported.
     */
    public static function fromString(string $severity): self
    {
        if (! self::isSupported($severity)) {
            throw new InvalidInputException('Validation severity is not supported.');
        }

        return new self($severity);
    }

    /** Creates the error severity. */
    public static function error(): self
    {
        return new self(self::ERROR);
    }

    /** Creates the warning severity. */
    public static function warning(): self
    {
        return new self(self::WARNING);
    }

    /** Creates the informational severity. */
    public static function info(): self
    {
        return new self(self::INFO);
    }

    /** Returns the stable serialization value. */
    public function value(): string
    {
        return $this->value;
    }

    /** Whether this severity represents an error. */
    public function isError(): bool
    {
        return $this->value === self::ERROR;
    }

    /** Whether both objects represent the same validation severity. */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Whether the supplied value is a supported finding severity.
     *
     * @param  string $severity Severity assigned to the validation finding.
     * @return bool
     */
    public static function isSupported(string $severity): bool
    {
        return in_array($severity, [self::ERROR, self::WARNING, self::INFO], true);
    }
}
