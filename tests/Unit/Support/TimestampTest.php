<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DreaM1\EfakturaConnector\Support\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests timestamp parsing and normalization helpers. */
class TimestampTest extends TestCase
{
    public function testParsesIso8601TimestampsWithZuluAndOffsets(): void
    {
        $zulu         = Timestamp::tryParseIso8601('2024-02-29T23:59:59Z');
        $offset       = Timestamp::tryParseIso8601('2024-02-29T23:59:59.123456+14:00');
        $longFraction = Timestamp::tryParseIso8601(
            '2024-02-29T23:59:59.' . str_repeat('1', 100) . 'Z'
        );

        $this->assertNotNull($zulu);
        $this->assertSame('2024-02-29T23:59:59Z', $zulu->value());
        $this->assertNotNull($offset);
        $this->assertNotNull($longFraction);
        $this->assertSame(str_repeat('1', 100), substr($longFraction->value(), 20, 100));
        $this->assertSame('111111', $longFraction->dateTimeImmutable()->format('u'));

        $this->assertSame('2024-02-29T23:59:59Z', Timestamp::fromIso8601('2024-02-29T23:59:59Z')->value());
        $this->assertSame(
            '2024-02-29T23:59:59.123456789Z',
            Timestamp::fromRfc3339('2024-02-29T23:59:59.123456789Z')->value()
        );
    }

    public function testCanBeUsedAsDateTimeInterface(): void
    {
        $timestamp = Timestamp::fromIso8601('2024-02-29T23:59:59.123456+02:00');

        $this->assertInstanceOf(DateTimeInterface::class, $timestamp);
        $this->assertSame('2024-02-29T23:59:59.123456+02:00', $this->formatDateTime($timestamp));
    }

    public function testRejectsMalformedIso8601DatesTimesAndOffsets(): void
    {
        foreach (
            [
            'tomorrow',
            '2023-02-29T10:30:00Z',
            '2024-01-01T25:30:00Z',
            '2024-01-01T10:30:00+14:01',
            '2024-01-01t10:30:00z',
            '2024-02-29T23:59:59.123456Zx',
            ] as $value
        ) {
            $this->assertNull(Timestamp::tryParseIso8601($value), $value);
        }
    }

    public function testStrictFactoriesRejectInvalidValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Timestamp::fromIso8601('not-a-timestamp');
    }

    public function testStrictRfc3339FactoryRejectsInvalidValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Timestamp::fromRfc3339('2024-02-30T23:59:59Z');
    }

    public function testRfc3339ParserUsesItsOwnGrammar(): void
    {
        $value     = '2024-02-29t23:59:59.123456789+14:01';
        $timestamp = Timestamp::tryParseRfc3339($value);

        $this->assertNotNull($timestamp);
        $this->assertSame($value, $timestamp->value());
        $this->assertSame('123456', $timestamp->format('u'));
        $this->assertNull(Timestamp::tryParseIso8601($value));
    }

    public function testCreatesATimestampFromDateTimeImmutable(): void
    {
        $timestamp = Timestamp::fromDateTimeImmutable(
            new DateTimeImmutable('2024-02-29T23:59:59.123456+02:00')
        );

        $this->assertSame('2024-02-29T23:59:59.123456+02:00', $timestamp->value());
        $this->assertSame(
            '2024-02-29T23:59:59.123456+02:00',
            $timestamp->dateTimeImmutable()->format('Y-m-d\\TH:i:s.uP')
        );
    }

    public function testInheritedDateTimeOperationsHandleUninitializedTimestampMetadata(): void
    {
        $utc        = new DateTimeZone('UTC');
        $fromFormat = Timestamp::createFromFormat('!Y-m-d H:i:s', '2024-02-29 23:59:59', $utc);

        $this->assertNotFalse($fromFormat);
        $this->assertSame('2024-02-29T23:59:59.000000+00:00', $this->timestampValue($fromFormat));

        $timestamp = Timestamp::fromIso8601('2024-02-29T23:59:59Z');
        $modified  = $timestamp->modify('+1 day');

        $this->assertNotFalse($modified);
        $this->assertSame('2024-03-01T23:59:59.000000+00:00', $this->timestampValue($modified));

        $localized = $modified->setTimezone(new DateTimeZone('Europe/Bratislava'));

        $this->assertSame('2024-03-02T00:59:59.000000+01:00', $this->timestampValue($localized));

        $restored = unserialize(serialize($localized));

        $this->assertInstanceOf(DateTimeImmutable::class, $restored);
        $this->assertSame($this->timestampValue($localized), $this->timestampValue($restored));
    }

    public function testRejectsDateTimeValuesOutsideTheSupportedProfile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Timestamp::fromDateTimeImmutable(new DateTimeImmutable('0000-01-01T00:00:00+00:00'));
    }

    private function formatDateTime(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d\\TH:i:s.uP');
    }

    private function timestampValue(DateTimeImmutable $dateTime): string
    {
        return $dateTime instanceof Timestamp
            ? $dateTime->value()
            : $dateTime->format('Y-m-d\\TH:i:s.uP');
    }
}
