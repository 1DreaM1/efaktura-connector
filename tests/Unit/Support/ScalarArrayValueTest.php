<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Support;

use DreaM1\EfakturaConnector\Support\ScalarArrayValue;
use PHPUnit\Framework\TestCase;
use stdClass;

/** Tests safe extraction of scalar values from arrays. */
class ScalarArrayValueTest extends TestCase
{
    public function testCastsScalarValuesToStrings(): void
    {
        $this->assertSame('text', ScalarArrayValue::asString(['value' => 'text'], 'value'));
        $this->assertSame('42', ScalarArrayValue::asString(['value' => 42], 'value'));
        $this->assertSame('1.5', ScalarArrayValue::asString(['value' => 1.5], 'value'));
        $this->assertSame('1', ScalarArrayValue::asString(['value' => true], 'value'));
        $this->assertSame('', ScalarArrayValue::asString(['value' => false], 'value'));
    }

    public function testReturnsNullForMissingNullAndNonScalarValues(): void
    {
        $this->assertNull(ScalarArrayValue::asString([], 'value'));
        $this->assertNull(ScalarArrayValue::asString(['value' => null], 'value'));
        $this->assertNull(ScalarArrayValue::asString(['value' => []], 'value'));
        $this->assertNull(ScalarArrayValue::asString(['value' => new stdClass()], 'value'));
    }
}
