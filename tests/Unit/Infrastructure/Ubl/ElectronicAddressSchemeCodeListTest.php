<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\Ubl;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationResources;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CodeListResources;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\ElectronicAddressSchemeCodeList;
use PHPUnit\Framework\TestCase;

/** Tests Electronic Address Scheme validation against the bundled list. */
class ElectronicAddressSchemeCodeListTest extends TestCase
{
    public function testRecognizesExactEasSchemeCodes(): void
    {
        $this->assertTrue(ElectronicAddressSchemeCodeList::isCanonical('0002'));
        $this->assertTrue(ElectronicAddressSchemeCodeList::isCanonical('0088'));
        $this->assertTrue(ElectronicAddressSchemeCodeList::isCanonical('9959'));
        $this->assertFalse(ElectronicAddressSchemeCodeList::isCanonical('0037'));
        $this->assertFalse(ElectronicAddressSchemeCodeList::isCanonical('9999'));
        $this->assertFalse(ElectronicAddressSchemeCodeList::isCanonical(' 0088'));
        $this->assertFalse(ElectronicAddressSchemeCodeList::isCanonical('0088 '));
    }

    public function testBundledEasResourceMatchesThePinnedPeppolSchematron(): void
    {
        $resourcePath   = CodeListResources::path(CodeListResources::ELECTRONIC_ADDRESS_SCHEMES_FILE);
        $resource       = json_decode((string) file_get_contents($resourcePath), true);
        $schematronPath = ValidationResources::path(
            ValidationResources::PEPPOL_RULES_DIRECTORY . '/'
            . ValidationResources::PEPPOL_RULESET . '.sch'
        );
        $schematron = (string) file_get_contents($schematronPath);

        $this->assertIsArray($resource);
        $this->assertSame(CodeListResources::ELECTRONIC_ADDRESS_SCHEMES_RELEASE, $resource['version'] ?? null);
        $this->assertSame(ElectronicAddressSchemeCodeList::all(), $resource['schemes'] ?? null);
        $this->assertSame(
            ElectronicAddressSchemeCodeList::all(),
            $this->schematronEasSchemes($schematron)
        );
    }

    public function testPeppolEasIsANarrowerSetThanTheCenBrCl25Baseline(): void
    {
        $schematronPath = ValidationResources::path(
            ValidationResources::CEN_RULES_DIRECTORY . '/'
            . ValidationResources::CEN_RULESET . '.sch'
        );
        $schematron           = (string) file_get_contents($schematronPath);
        $cenSchemes           = $this->cenBrCl25Schemes($schematron);
        $removedFromPeppolEas = [
            '0037', '0147', '0154', '0170', '0177', '0193', '0194',
            '0202', '0203', '0205', '0212', '0213', '0215', '0217',
        ];

        $this->assertSame([], array_values(array_diff(ElectronicAddressSchemeCodeList::all(), $cenSchemes)));
        $this->assertSame([], array_values(array_diff($removedFromPeppolEas, $cenSchemes)));
        $this->assertSame([], array_values(array_intersect($removedFromPeppolEas, ElectronicAddressSchemeCodeList::all())));
    }

    /** @return string[] */
    private function schematronEasSchemes(string $schematron): array
    {
        $matched = preg_match('/<let\s+name="eaid"\s+value="tokenize\(\'([^\']+)\'/s', $schematron, $matches);
        $this->assertSame(1, $matched, 'The pinned Peppol Schematron must declare the eaid code list.');

        $schemes = preg_split('/\s+/', trim($matches[1]));

        return is_array($schemes) ? $schemes : [];
    }

    /** @return string[] */
    private function cenBrCl25Schemes(string $schematron): array
    {
        $matched = preg_match(
            '/<assert\b(?=[^>]*\bid="BR-CL-25")(?=[^>]*\btest="([^"]+)")[^>]*>/s',
            $schematron,
            $assertMatches
        );
        $this->assertSame(1, $matched, 'The pinned CEN Schematron must declare BR-CL-25.');

        $matched = preg_match("/contains\\(' ([^']+) ',\\s*concat\\(/s", $assertMatches[1], $matches);
        $this->assertSame(1, $matched, 'The CEN BR-CL-25 rule must declare its EAS code list.');

        $schemes = preg_split('/\\s+/', trim($matches[1]));

        return is_array($schemes) ? $schemes : [];
    }
}
