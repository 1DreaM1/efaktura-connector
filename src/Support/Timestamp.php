<?php

namespace DreaM1\EfakturaConnector\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use Exception;

/**
 * A validated timestamp retaining both its original text and parsed immutable instant.
 *
 * @api
 */
final class Timestamp extends DateTimeImmutable
{
    /** Number of fractional-second digits retained when normalizing a timestamp. */
    private const FRACTIONAL_SECOND_PRECISION = 6;
    /** Output format used for normalized ISO 8601 timestamps. */
    private const ISO_8601_FORMAT = 'Y-m-d\\TH:i:s.uP';
    /** Parsing format paired with the normalized ISO 8601 output format. */
    private const ISO_8601_PARSE_FORMAT = '!Y-m-d\\TH:i:s.uP';

    /** Pattern accepting the package's supported ISO 8601 timestamp form. */
    private const ISO_8601_PATTERN = '/\A(\d{4}-\d{2}-\d{2})T((?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d)'
        . '(?:\.(\d+))?(Z|[+-](?:(?:0\d|1[0-3]):[0-5]\d|14:00))\z/';
    /** Pattern accepting RFC 3339 timestamps, including lowercase separators. */
    private const RFC_3339_PATTERN = '/\A(\d{4}-\d{2}-\d{2})[Tt]((?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d)'
        . '(?:\.(\d+))?([Zz]|[+-](?:[01]\d|2[0-3]):[0-5]\d)\z/';

    private ?string $value           = null;
    private ?string $normalizedValue = null;

    /**
     * Parses and stores an ISO 8601 or RFC 3339 timestamp with its original text.
     *
     * @param  string                $datetime Timestamp text to parse.
     * @param  DateTimeZone|null     $timezone Timezone forwarded to the immutable parent value.
     * @throws InvalidInputException when the value is not a supported timestamp
     * @throws Exception             when PHP cannot construct the normalized immutable date-time value
     */
    public function __construct(string $datetime = 'now', ?DateTimeZone $timezone = null)
    {
        if ($datetime === 'now') {
            $datetime = (new DateTimeImmutable('now', $timezone))->format(self::ISO_8601_FORMAT);
        }

        $dateTime = self::parseDateTime($datetime, self::ISO_8601_PATTERN);

        if ($dateTime === null) {
            $dateTime = self::parseDateTime($datetime, self::RFC_3339_PATTERN);
        }

        if ($dateTime === null) {
            throw new InvalidInputException('Timestamp must use a supported ISO 8601 or RFC 3339 format.');
        }

        $this->value           = $datetime;
        $this->normalizedValue = $dateTime->format(self::ISO_8601_FORMAT);
        parent::__construct($this->normalizedValue, $timezone);
    }

    /**
     * Parses a provider-profile ISO 8601 timestamp, or returns null when absent or invalid.
     *
     * @param  string|null $value Timestamp text from the provider.
     * @return self|null
     * @throws Exception   when PHP cannot construct the parsed immutable date-time value
     */
    public static function tryParseIso8601(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::parse($value, self::ISO_8601_PATTERN);
    }

    /**
     * Creates a provider ISO 8601 timestamp or throws when its value is invalid.
     *
     * @param  string                $value Timestamp text in the supported provider profile.
     * @return self
     * @throws InvalidInputException when the value does not match the supported profile
     * @throws Exception             when PHP cannot construct the parsed immutable date-time value
     */
    public static function fromIso8601(string $value): self
    {
        $timestamp = self::tryParseIso8601($value);

        if ($timestamp === null) {
            throw new InvalidInputException('Timestamp must use the supported ISO 8601 format.');
        }

        return $timestamp;
    }

    /**
     * Parses an RFC 3339 webhook timestamp, or returns null when absent or invalid.
     *
     * @param  string|null $value RFC 3339 timestamp text from the webhook.
     * @return self|null
     * @throws Exception   when PHP cannot construct the parsed immutable date
     */
    public static function tryParseRfc3339(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::parse($value, self::RFC_3339_PATTERN);
    }

    /**
     * Creates an RFC 3339 timestamp or throws when its value is invalid.
     *
     * @param  string                $value Timestamp text in RFC 3339 form.
     * @return self
     * @throws InvalidInputException when the value is not a valid RFC 3339 timestamp
     * @throws Exception             when PHP cannot construct the parsed immutable date-time value
     */
    public static function fromRfc3339(string $value): self
    {
        $timestamp = self::tryParseRfc3339($value);

        if ($timestamp === null) {
            throw new InvalidInputException('Timestamp must use the RFC 3339 format.');
        }

        return $timestamp;
    }

    /**
     * Checks the provider ISO 8601 timestamp profile without creating a DateTimeImmutable.
     *
     * @param  string $value Timestamp text to validate.
     * @return bool
     */
    public static function isValidIso8601(string $value): bool
    {
        return self::matches($value, self::ISO_8601_PATTERN) !== null;
    }

    /**
     * Creates a timestamp from an already parsed instant using a stable ISO 8601 representation.
     *
     * @param  DateTimeInterface     $dateTime Parsed date and time to preserve.
     * @return self
     * @throws InvalidInputException when the instant is outside the supported timestamp profile
     * @throws Exception             when PHP cannot construct the parsed immutable date-time value
     */
    public static function fromDateTimeImmutable(DateTimeInterface $dateTime): self
    {
        $value = $dateTime->format(self::ISO_8601_FORMAT);

        if (! self::isValidIso8601($value)) {
            throw new InvalidInputException('DateTimeInterface value is outside the supported timestamp profile.');
        }

        return new self($value);
    }

    /** Returns the original text while unchanged, or the current ISO 8601 text after modification. */
    public function value(): string
    {
        $currentValue = $this->format(self::ISO_8601_FORMAT);

        return $this->value !== null
            && $this->normalizedValue !== null
            && $currentValue === $this->normalizedValue
            ? $this->value
            : $currentValue;
    }

    /** Returns the parsed immutable instant. */
    public function dateTimeImmutable(): DateTimeImmutable
    {
        return $this;
    }

    /** Parses a valid timestamp into a value object.
     * @throws Exception when PHP cannot construct the parsed immutable date-time value.
     */
    private static function parse(string $value, string $pattern): ?self
    {
        if (self::matches($value, $pattern) === null) {
            return null;
        }

        return new self($value);
    }

    /** Parses a timestamp after checking its grammar and calendar date. */
    private static function parseDateTime(string $value, string $pattern): ?DateTimeImmutable
    {
        $matches = self::matches($value, $pattern);

        if ($matches === null) {
            return null;
        }

        // DateTime stores microseconds; retain the original input separately while normalizing to that precision.
        $fraction   = substr($matches[3] ?? '', 0, self::FRACTIONAL_SECOND_PRECISION);
        $fraction   = str_pad($fraction, self::FRACTIONAL_SECOND_PRECISION, '0');
        $timeZone   = strtoupper($matches[4]) === 'Z' ? '+00:00' : $matches[4];
        $normalized = $matches[1] . 'T' . $matches[2] . '.' . $fraction . $timeZone;
        $dateTime   = DateTimeImmutable::createFromFormat(self::ISO_8601_PARSE_FORMAT, $normalized);
        $errors     = DateTimeImmutable::getLastErrors();

        if (
            $dateTime === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            return null;
        }

        return $dateTime;
    }

    /** @return string[]|null Regex captures after checking the calendar date. */
    private static function matches(string $value, string $pattern): ?array
    {
        if (preg_match($pattern, $value, $matches) !== 1) {
            return null;
        }

        // Prevent impossible dates from being normalized into a different calendar day by the date parser.
        $dateParts = explode('-', $matches[1]);

        if (! checkdate((int) $dateParts[1], (int) $dateParts[2], (int) $dateParts[0])) {
            return null;
        }

        return $matches;
    }
}
