<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl;

use DateTimeImmutable;
use DateTimeZone;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use Exception;

/** A calendar date represented as midnight UTC by default and stored in YYYY-MM-DD form. */
final class LocalDate extends DateTimeImmutable
{
    /** Pattern for a calendar date in the required YYYY-MM-DD representation. */
    private const PATTERN = '/\A(\d{4})-(\d{2})-(\d{2})\z/';

    /**
     * Creates a calendar date at midnight in the supplied timezone, or UTC by default.
     *
     * @param  string                          $datetime Calendar date in YYYY-MM-DD form.
     * @param  DateTimeZone|null               $timezone Timezone used to represent midnight; defaults to UTC.
     * @throws InvalidInputException|Exception when the value is not a valid YYYY-MM-DD date
     */
    public function __construct(string $datetime = 'now', ?DateTimeZone $timezone = null)
    {
        $timezone = $timezone ?: new DateTimeZone('UTC');

        if ($datetime === 'now') {
            $datetime = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
        }

        if (! self::isValid($datetime)) {
            throw new InvalidInputException('Date must use a valid ISO 8601 YYYY-MM-DD value.');
        }

        parent::__construct($datetime . ' 00:00:00', $timezone);
    }

    /**
     * Creates a date from a valid ISO 8601 calendar date.
     *
     * @param  string                          $value Calendar date in YYYY-MM-DD form.
     * @return self
     * @throws InvalidInputException|Exception when the value is not a valid YYYY-MM-DD date
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Parses a valid ISO 8601 calendar date, or returns null when absent or invalid.
     *
     * @param  string|null $value Calendar date in YYYY-MM-DD form.
     * @return self|null
     * @throws Exception   when the date string cannot be parsed by DateTimeImmutable
     */
    public static function tryParse(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        if (! self::isValid($value)) {
            return null;
        }

        return new self($value);
    }

    /** Returns the calendar date in YYYY-MM-DD form. */
    public function value(): string
    {
        return $this->format('Y-m-d');
    }

    /** Checks that a value is a valid ISO 8601 calendar date. */
    private static function isValid(string $value): bool
    {
        if (preg_match(self::PATTERN, $value, $matches) !== 1) {
            return false;
        }

        // Reject impossible calendar dates before DateTimeImmutable can normalize them.
        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }
}
