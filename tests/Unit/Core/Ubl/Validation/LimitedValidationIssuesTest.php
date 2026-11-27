<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Ubl\Validation;

use DreaM1\EfakturaConnector\Core\Ubl\Validation\LimitedValidationIssues;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks retained issue types, omitted-finding counts and validation of constructed collections. */
class LimitedValidationIssuesTest extends TestCase
{
    public function testRetainsNonnegativeOmittedCount(): void
    {
        $limited = new LimitedValidationIssues([], 2);

        $this->assertSame(2, $limited->omittedCount());
    }

    public function testRejectsANegativeOmittedCount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LimitedValidationIssues([], -1);
    }
}
