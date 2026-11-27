<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain\Ubl;

use DateTimeInterface;
use DreaM1\EfakturaConnector\Domain\Ubl\LocalDate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks strict calendar-date parsing, preservation and rejection of invalid dates. */
class LocalDateTest extends TestCase
{
    public function testParsesAndPreservesIsoCalendarDate(): void
    {
        $date = LocalDate::fromString('2024-02-29');

        $this->assertSame('2024-02-29', $date->value());
        $parsed = LocalDate::tryParse('2024-02-29');

        $this->assertNotNull($parsed);
        $this->assertSame('2024-02-29', $parsed->value());
    }

    public function testCanBeUsedAsDateTimeInterface(): void
    {
        $date = LocalDate::fromString('2024-02-29');

        $this->assertInstanceOf(DateTimeInterface::class, $date);
        $this->assertSame('2024-02-29', $this->formatDate($date));
    }

    public function testTryParseReturnsNullForMissingOrInvalidDates(): void
    {
        $this->assertNull(LocalDate::tryParse(null));
        $this->assertNull(LocalDate::tryParse('2023-02-29'));
        $this->assertNull(LocalDate::tryParse(' 2024-02-29'));
    }

    public function testFromStringRejectsAnInvalidDate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LocalDate::fromString('2023-02-29');
    }

    private function formatDate(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d');
    }
}
