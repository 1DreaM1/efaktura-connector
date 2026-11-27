<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\Ubl;

use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\TaxCategoryCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDecimal;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftIdentifier;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftLine;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftTaxCategory;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CodeListResources;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\UnitCodeList;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests the opt-in unit alias lookup that sits beside the strict UN/ECE unit code list. */
class UnitCodeListAliasesTest extends TestCase
{
    /**
     * @dataProvider commonAliases
     */
    public function testCommonLocalAbbreviationsResolveToTheirUneceCode(string $input, string $expected): void
    {
        $this->assertSame($expected, UnitCodeList::fromLabel($input)->value());
        $this->assertSame($expected, UnitCodeList::unit($input)->value());
    }

    /** @return array<string, array{string, string}> */
    public function commonAliases(): array
    {
        return [
            'piece'             => ['ks', 'H87'],
            'kilogram'          => ['kg', 'KGM'],
            'litre'             => ['l', 'LTR'],
            'hour'              => ['hod', 'HUR'],
            'square metre'      => ['m2', 'MTK'],
            'superscript'       => ['m²', 'MTK'],
            'cubic metre'       => ['m³', 'MTQ'],
            'day with diacrit.' => ['Deň', 'DAY'],
            'package'           => ['balenie', 'XPK'],
            'lump sum'          => ['paušál', 'LS'],
            'percent'           => ['%', 'P1'],
        ];
    }

    /**
     * @dataProvider aliasesWrittenWithDiacritics
     */
    public function testInputWithDiacriticsResolvesLikeItsPlainSpelling(string $input, string $expected): void
    {
        $this->assertSame($expected, UnitCodeList::unit($input)->value());
        $this->assertSame($expected, UnitCodeList::unit(mb_strtoupper($input, 'UTF-8'))->value());
    }

    /** @return array<string, array{string, string}> */
    public function aliasesWrittenWithDiacritics(): array
    {
        return [
            'paušál'      => ['paušál', 'LS'],
            'služba'      => ['služba', 'E48'],
            'škatuľa'     => ['škatuľa', 'XBX'],
            'fľaša'       => ['fľaša', 'XBO'],
            'plechovka'   => ['plechovka', 'XCX'],
            'balíček'     => ['balíček', 'XPA'],
            'zväzok'      => ['zväzok', 'XBE'],
            'týždeň'      => ['týždeň', 'WEE'],
            'týždne'      => ['týždne', 'WEE'],
            'deň'         => ['deň', 'DAY'],
            'dní'         => ['dní', 'DAY'],
            'hodín'       => ['hodín', 'HUR'],
            'minút'       => ['minút', 'MIN'],
            'mesiac'      => ['mesiac', 'MON'],
            'štvrťrok'    => ['štvrťrok', 'QAN'],
            'bežný meter' => ['bežný meter', 'LM'],
            'tisíc'       => ['tisíc', 'MIL'],
            'páry'        => ['páry', 'PR'],
            'kôš'         => ['kôš', 'XBK'],
            'tyč'         => ['tyč', 'XRD'],
            'kaď'         => ['kaď', 'XVA'],
        ];
    }

    public function testInputIsNormalizedBeforeTheAliasLookup(): void
    {
        foreach (['ks', 'KS', ' Ks ', 'ks.', "ks\t", 'Ks.'] as $input) {
            $this->assertSame('H87', UnitCodeList::fromLabel($input)->value(), $input);
        }
    }

    public function testExactCanonicalCodesAreReturnedAndAreNeverMatchedCaseInsensitively(): void
    {
        $this->assertSame('H87', UnitCodeList::fromLabel('H87')->value());
        $this->assertNull(UnitCodeList::fromLabel('h87'));
        $this->assertNull(UnitCodeList::fromLabel(' H87'));
    }

    public function testAnExactCodeWinsOverAnAliasThatLooksLikeIt(): void
    {
        // `MIN` is both a canonical code and (lower case) an alias; the exact code is returned directly.
        $this->assertSame('MIN', UnitCodeList::fromLabel('MIN')->value());
        $this->assertSame('MIN', UnitCodeList::fromLabel('min')->value());
    }

    public function testUnknownInputIsNullAndUnitRejectsIt(): void
    {
        $this->assertNull(UnitCodeList::fromLabel('xyz'));
        $this->assertNull(UnitCodeList::fromLabel(''));
        $this->assertNull(UnitCodeList::fromLabel('   '));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown unit "xyz"');

        UnitCodeList::unit('xyz');
    }

    public function testStrictResolveAndCanonicalCheckAreUnchanged(): void
    {
        $this->assertNull(UnitCodeList::resolve('ks'));
        $this->assertFalse(UnitCodeList::isCanonical('ks'));
        $this->assertSame('H87', UnitCodeList::resolve('H87')->value());
    }

    public function testExtraAliasesAddAndOverrideTheBundledOnes(): void
    {
        $extra = ['ks' => 'C62', 'Služba' => 'E48', 'pal.' => 'XPX'];

        $this->assertSame('C62', UnitCodeList::fromLabel('ks', $extra)->value());
        $this->assertSame('E48', UnitCodeList::fromLabel('sluzba', $extra)->value());
        $this->assertSame('XPX', UnitCodeList::fromLabel('Pal.', $extra)->value());
        // The bundled alias is unaffected for callers that do not pass extras.
        $this->assertSame('H87', UnitCodeList::fromLabel('ks')->value());
    }

    /**
     * @dataProvider invalidExtraAliases
     * @param array<array-key, mixed> $extra
     */
    public function testInvalidExtraAliasesAreRejected(array $extra): void
    {
        $this->expectException(InvalidArgumentException::class);

        UnitCodeList::fromLabel('ks', $extra);
    }

    /** @return array<string, array{array<array-key, mixed>}> */
    public function invalidExtraAliases(): array
    {
        return [
            'non-canonical target'    => [['x' => 'piece']],
            'unknown code'            => [['x' => 'ZZZZ']],
            'lower-case target'       => [['x' => 'h87']],
            'empty label'             => [['  ' => 'H87']],
            'non-string target'       => [['x' => 5]],
            'ambiguous after folding' => [['Ks' => 'H87', 'ks.' => 'C62']],
        ];
    }

    public function testInvalidExtraAliasesAreRejectedEvenForAnExactCodeLookup(): void
    {
        // An exact code does not need the aliases, but a broken table is only reported once it is used.
        $this->assertSame('H87', UnitCodeList::fromLabel('H87', ['x' => 'nonsense'])->value());

        $this->expectException(InvalidArgumentException::class);

        UnitCodeList::fromLabel('unknown', ['x' => 'nonsense']);
    }

    public function testEveryBundledAliasPointsAtACanonicalCodeAndIsStoredNormalized(): void
    {
        $this->assertFileExists(CodeListResources::path(CodeListResources::UNIT_ALIASES_FILE));

        $resource = CodeListResources::readJsonArray(CodeListResources::UNIT_ALIASES_FILE);

        $this->assertIsString($resource['note'] ?? null);
        $this->assertIsArray($resource['aliases'] ?? null);
        $this->assertNotEmpty($resource['aliases']);

        foreach ($resource['aliases'] as $alias => $code) {
            $alias = (string) $alias;

            $this->assertTrue(UnitCodeList::isCanonical((string) $code), $alias . ' targets a non-canonical code');
            // The lookup normalizes its input, so a key that is not stored normalized could never be found.
            $this->assertSame($code, UnitCodeList::fromLabel($alias)->value(), $alias . ' is not stored normalized');
            // An alias must not hide a different canonical code that is spelled the same in upper case.
            $upper = strtoupper($alias);

            if (UnitCodeList::isCanonical($upper)) {
                $this->assertSame($code, $upper, $alias . ' collides with the canonical code ' . $upper);
            }
        }
    }

    public function testAnAliasResolvedUnitCanBeUsedDirectlyInADraftLine(): void
    {
        $line = new UblDraftLine(
            new UblDraftIdentifier('1'),
            UblDecimal::fromString('2'),
            UnitCodeList::unit('ks'),
            UblDecimal::fromString('100.00'),
            'Ukážková služba',
            new UblDraftTaxCategory(TaxCategoryCode::fromString(TaxCategoryCode::STANDARD), UblDecimal::fromString('23')),
            UblDecimal::fromString('50.00')
        );

        $this->assertSame('H87', $line->unitCode()->value());
    }
}
