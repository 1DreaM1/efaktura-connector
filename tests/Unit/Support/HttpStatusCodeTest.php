<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Support;

use DreaM1\EfakturaConnector\Support\HttpStatusCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests HTTP status code representation and classification. */
class HttpStatusCodeTest extends TestCase
{
    public function testRetainsNumericValueAndComparesByValue(): void
    {
        $status = HttpStatusCode::fromInt(200);

        $this->assertSame(200, $status->value());
        $this->assertTrue($status->equals(HttpStatusCode::fromInt(200)));
        $this->assertFalse($status->equals(HttpStatusCode::fromInt(201)));
    }

    public function testClassifiesStandardStatusRangesAndSpecificCodes(): void
    {
        $this->assertTrue(HttpStatusCode::fromInt(100)->isInformational());
        $this->assertTrue(HttpStatusCode::fromInt(200)->isSuccessful());
        $this->assertTrue(HttpStatusCode::fromInt(300)->isRedirection());
        $this->assertTrue(HttpStatusCode::fromInt(400)->isClientError());
        $this->assertTrue(HttpStatusCode::fromInt(500)->isServerError());
        $this->assertFalse(HttpStatusCode::fromInt(499)->isServerError());
        $this->assertTrue(HttpStatusCode::fromInt(599)->isServerError());
        $this->assertTrue(HttpStatusCode::fromInt(471)->isClientError());
        $this->assertTrue(HttpStatusCode::fromInt(204)->isNoContent());
        $this->assertTrue(HttpStatusCode::fromInt(429)->isTooManyRequests());
        $this->assertNull(HttpStatusCode::tryFromInt(600));
    }

    /**
     * Rejects integer values outside the valid HTTP status range defined by RFC 9110.
     *
     * @param int $value Invalid status value.
     * @dataProvider invalidValues
     */
    public function testRejectsValuesOutsideTheValidStatusRange(int $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        HttpStatusCode::fromInt($value);
    }

    /** @return array<array-key, array<array-key, int>> Invalid status values. */
    public function invalidValues(): array
    {
        return [
            'below range'       => [99],
            'zero'              => [0],
            'above valid range' => [600],
            'above range'       => [1000],
        ];
    }
}
