<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core;

use DreaM1\EfakturaConnector\Core\CodeLists\CodeListOption;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests the selectable code-list option value object. */
class CodeListOptionTest extends TestCase
{
    public function testPreservesTheExactValueAndLabel(): void
    {
        $option = new CodeListOption('0002', '0002 – Example scheme');

        $this->assertSame('0002', $option->value());
        $this->assertSame('0002 – Example scheme', $option->label());
    }

    /**
     * @param string $value Code option value.
     * @param string $label Code option label.
     * @dataProvider blankOptionParts
     */
    public function testRejectsBlankValuesOrLabels(string $value, string $label): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CodeListOption($value, $label);
    }

    /** @return array<string, array{string, string}> */
    public function blankOptionParts(): array
    {
        return [
            'blank value'      => ['', 'Label'],
            'whitespace value' => ['  ', 'Label'],
            'blank label'      => ['ABC', ''],
            'whitespace label' => ['ABC', "\t\n"],
        ];
    }
}
