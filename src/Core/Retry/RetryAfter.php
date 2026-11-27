<?php

namespace DreaM1\EfakturaConnector\Core\Retry;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

/** Parses the standard HTTP Retry-After header into a typed deadline. */
final class RetryAfter
{
    /** Standard HTTP header parsed by this value object. */
    public const HEADER_NAME = 'Retry-After';

    /** Maximum future year offset accepted when parsing an obsolete RFC 850 HTTP date. */
    private const RFC850_MAX_FUTURE_YEARS = 50;
    /** Pattern for a non-negative integer Retry-After delay in seconds. */
    private const SECONDS_PATTERN = '/\A\d+\z/';

    /**
     * Returns a retry deadline not earlier than now, or null when the header is absent or invalid.
     *
     * @param  array<string, mixed> $headers HTTP headers to inspect.
     * @param  int|null             $now     Current Unix timestamp used for time-dependent behavior.
     * @return RetryDeadline|null
     */
    public static function deadline(array $headers, ?int $now = null): ?RetryDeadline
    {
        $value = null;

        // HTTP field names are case-insensitive; accept the first matching header entry.
        foreach ($headers as $name => $headerValue) {
            if (strcasecmp((string) $name, self::HEADER_NAME) === 0) {
                $value = $headerValue;
                break;
            }
        }

        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);
        $now   = $now ?? time();

        if (preg_match(self::SECONDS_PATTERN, $value) === 1) {
            return self::deadlineAfterDelay($value, $now);
        }

        $retryAt = self::parseHttpDate($value, $now);

        if ($retryAt === null) {
            return null;
        }

        // A past HTTP date means the server permits a retry now, not a negative delay.
        return RetryDeadline::fromUnixTimestamp(max($now, $retryAt->getTimestamp()));
    }

    /**
     * Converts a delay in seconds to a deadline, saturating at the largest timestamp this PHP runtime can hold.
     *
     * @param string $seconds Decimal digits only, as matched by SECONDS_PATTERN.
     */
    private static function deadlineAfterDelay(string $seconds, int $now): RetryDeadline
    {
        // Remove leading zeroes before comparing decimal strings, avoiding an unsafe cast first.
        $seconds = ltrim($seconds, '0');

        if ($seconds === '') {
            return RetryDeadline::fromUnixTimestamp($now);
        }

        $maximumSeconds = (string) (PHP_INT_MAX - $now);

        if (
            strlen($seconds) > strlen($maximumSeconds)
            || (strlen($seconds) === strlen($maximumSeconds)
                && strcmp($seconds, $maximumSeconds) > 0)
        ) {
            return RetryDeadline::fromUnixTimestamp(PHP_INT_MAX);
        }

        return RetryDeadline::fromUnixTimestamp($now + (int) $seconds);
    }

    /**
     * Parses each HTTP-date form that an HTTP recipient is required to accept.
     *
     * The three forms are mutually exclusive, so the first one that parses wins.
     */
    private static function parseHttpDate(string $value, int $now): ?DateTimeImmutable
    {
        return self::createDateFromFormat(DateTimeInterface::RFC7231, $value)
            ?? self::parseRfc850Form($value, $now)
            ?? self::parseAsctimeForm($value);
    }

    /** Parses the obsolete RFC 850 form, for example "Sunday, 06-Nov-94 08:49:37 GMT". */
    private static function parseRfc850Form(string $value, int $now): ?DateTimeImmutable
    {
        if (
            preg_match(
                '/\A(?<weekday>[A-Za-z]+), (?<day>[0-9]{2})-(?<month>[A-Za-z]{3})-(?<year>[0-9]{2}) '
                . '(?<time>[0-9]{2}:[0-9]{2}:[0-9]{2}) GMT\z/iD',
                $value,
                $matches
            ) !== 1
        ) {
            return null;
        }

        $nowDate = self::dateAtTimestamp($now);

        if ($nowDate === null) {
            return null;
        }

        $currentYear = (int) $nowDate->format('Y');
        $year        = intdiv($currentYear, 100) * 100 + (int) $matches['year'];
        $retryAt     = self::parseRfc850Date($matches, $year);

        if ($retryAt === null) {
            return null;
        }

        // RFC 9110 interprets a two-digit year over 50 years ahead as the latest past matching year.
        if ($retryAt > $nowDate->modify('+' . self::RFC850_MAX_FUTURE_YEARS . ' years')) {
            $retryAt = self::parseRfc850Date($matches, $year - 100);
        }

        return $retryAt;
    }

    /** Parses the ANSI C asctime() form, for example "Sun Nov  6 08:49:37 1994". */
    private static function parseAsctimeForm(string $value): ?DateTimeImmutable
    {
        if (
            preg_match(
                '/\A(?<weekday>[A-Za-z]{3}) (?<month>[A-Za-z]{3}) (?<day>[ 0-9][0-9]) '
                . '(?<time>[0-9]{2}:[0-9]{2}:[0-9]{2}) (?<year>[0-9]{4})\z/iD',
                $value,
                $matches
            ) !== 1
        ) {
            return null;
        }

        $normalized = sprintf(
            '%s, %02d %s %s %s GMT',
            $matches['weekday'],
            (int) trim($matches['day']),
            $matches['month'],
            $matches['year'],
            $matches['time']
        );

        return self::createDateFromFormat(DateTimeInterface::RFC7231, $normalized);
    }
    /** @param array<int|string, string> $matches */
    private static function parseRfc850Date(array $matches, int $year): ?DateTimeImmutable
    {
        $value = sprintf(
            '%s, %s-%s-%04d %s GMT',
            $matches['weekday'],
            $matches['day'],
            $matches['month'],
            $year,
            $matches['time']
        );

        return self::createDateFromFormat('l, d-M-Y H:i:s \\G\\M\\T', $value);
    }

    private static function createDateFromFormat(string $format, string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat(
            '!' . $format,
            $value,
            new DateTimeZone('UTC')
        );
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            return null;
        }

        return $date;
    }

    private static function dateAtTimestamp(int $timestamp): ?DateTimeImmutable
    {
        try {
            return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            // DateTimeImmutable rejects timestamps it cannot represent; the caller treats null as "no usable date".
            return null;
        }
    }
}
