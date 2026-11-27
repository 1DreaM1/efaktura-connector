<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\Ubl;

use DOMDocument;
use DreaM1\EfakturaConnector\Core\Ubl\UblNamespaces;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\CurrencyCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\UnitCode;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CodeListResources;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CountryCodeList;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CurrencyCodeList;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\UnitCodeList;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Writer\UblElementWriter;
use DreaM1\EfakturaConnector\Tests\Support\ReflectionAccess;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/** Tests UBL code list helpers and writer behavior. */
class UblCodeListAndWriterTest extends TestCase
{
    public function testUneceRec20ResourceNameResolvesToTheBundledCodeList(): void
    {
        $path = CodeListResources::path(CodeListResources::UNECE_REC20_NAMES_FILE);

        $this->assertSame(
            CodeListResources::directory() . DIRECTORY_SEPARATOR . CodeListResources::UNECE_REC20_NAMES_FILE,
            $path
        );
        $this->assertFileExists($path);
    }

    public function testIso4217ResourceNameResolvesToTheBundledCodeList(): void
    {
        $path = CodeListResources::path(CodeListResources::ISO_4217_CURRENCIES_FILE);

        $this->assertSame(
            CodeListResources::directory() . DIRECTORY_SEPARATOR . CodeListResources::ISO_4217_CURRENCIES_FILE,
            $path
        );
        $this->assertFileExists($path);
    }

    public function testMissingCodeListResourceReturnsAnEmptyArray(): void
    {
        $this->assertSame([], CodeListResources::readJsonArray('missing-code-list-for-test.json'));
    }

    public function testPathTraversalCannotReadOutsideCodeListResources(): void
    {
        $this->assertSame([], CodeListResources::readJsonArray('../../composer.json'));
    }

    public function testDamagedCodeListJsonReturnsAnEmptyArrayAndUnitCodeListRemainsUsable(): void
    {
        $relativePath = 'corrupt-code-list-' . str_replace('.', '-', uniqid('', true)) . '.json';
        $path         = CodeListResources::path($relativePath);

        $resourceProperty = new ReflectionProperty(UnitCodeList::class, 'resource');
        ReflectionAccess::makeAccessible($resourceProperty);
        $previousResource = $resourceProperty->getValue();

        try {
            $this->assertNotFalse(file_put_contents($path, '{"units":'));

            $resource = CodeListResources::readJsonArray($relativePath);
            $this->assertSame([], $resource);

            // UnitCodeList uses a fixed packaged resource, so seed its cache with the decoded result.
            $resourceProperty->setValue(null, $resource);
            $this->assertNull(UnitCodeList::resolve('H87'));
            $this->assertFalse(UnitCodeList::isCanonical('H87'));
            $this->assertNull(UnitCodeList::name('H87'));
        } finally {
            $resourceProperty->setValue(null, $previousResource);
            @unlink($path);
        }
    }

    public function testUneceRec20ResourceVersionMatchesItsBundledMetadata(): void
    {
        $path = CodeListResources::path(CodeListResources::UNECE_REC20_NAMES_FILE);
        $data = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($data);
        $this->assertSame(CodeListResources::UNECE_REC20_VERSION, $data['version'] ?? null);
        $this->assertSame(CodeListResources::UNECE_REC20_RELEASE, $data['peppol_release'] ?? null);
        $this->assertArrayHasKey('X1', $data['units'] ?? []);
        $this->assertArrayNotHasKey('aliases', $data);
    }

    public function testIso4217ResourceVersionMatchesItsBundledMetadata(): void
    {
        $path = CodeListResources::path(CodeListResources::ISO_4217_CURRENCIES_FILE);
        $data = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($data);
        $this->assertSame(CodeListResources::ISO_4217_CURRENCIES_VERSION, $data['version'] ?? null);
        $this->assertSame(CodeListResources::ISO_4217_CURRENCIES_RELEASE, $data['peppol_release'] ?? null);
        $this->assertArrayHasKey('EUR', $data['currencies'] ?? []);
    }

    public function testEveryBundledCurrencyHasAnIso4217CodeAndName(): void
    {
        $path       = CodeListResources::path(CodeListResources::ISO_4217_CURRENCIES_FILE);
        $data       = json_decode((string) file_get_contents($path), true);
        $currencies = $data['currencies'] ?? [];

        $this->assertIsArray($currencies);
        $this->assertNotEmpty($currencies);

        foreach ($currencies as $code => $name) {
            $this->assertMatchesRegularExpression('/^[A-Z]{3}$/D', $code);
            $this->assertIsString($name);
            $this->assertNotSame('', trim($name));
            $resolved = CurrencyCodeList::resolve($code);
            $this->assertNotNull($resolved);
            $this->assertSame($code, $resolved->value());
        }
    }

    public function testRecognizesIso4217CurrenciesAndNormalizesCodes(): void
    {
        $this->assertTrue(CurrencyCodeList::contains(' eur '));
        $this->assertFalse(CurrencyCodeList::contains('ZZZ'));
        $this->assertFalse(CurrencyCodeList::contains(null));
        $this->assertSame('EUR', CurrencyCodeList::resolve('EUR')->value());
        $this->assertSame('EUR', CurrencyCodeList::resolve(' eur ')->value());
        $this->assertSame('Euro', CurrencyCodeList::name('eur'));
        $this->assertTrue(CurrencyCodeList::isCanonical('EUR'));
        $this->assertFalse(CurrencyCodeList::isCanonical('eur'));
        $this->assertFalse(CurrencyCodeList::isCanonical(' EUR '));
        $this->assertFalse(CurrencyCodeList::isCanonical('ZZZ'));
        $this->assertFalse(CurrencyCodeList::isCanonical(null));
        $this->assertNull(CurrencyCodeList::resolve(null));
        $this->assertNull(CurrencyCodeList::resolve('ZZZ'));
        $this->assertNull(CurrencyCodeList::name('ZZZ'));
    }

    public function testCurrencyValueObjectRejectsNonCanonicalSpelling(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CurrencyCode::fromCanonical('eur');
    }

    public function testCurrencyOptionsCoverEveryBundledCurrency(): void
    {
        $codes   = CurrencyCodeList::all();
        $options = CurrencyCodeList::options();

        $this->assertSame($codes, array_keys($options));
        $this->assertSame('EUR – Euro', $options['EUR'] ?? null);
        $this->assertArrayHasKey('XXX', $options);
    }

    public function testEveryBundledUneceUnitHasAnExactCanonicalCodeAndName(): void
    {
        $path  = CodeListResources::path(CodeListResources::UNECE_REC20_NAMES_FILE);
        $data  = json_decode((string) file_get_contents($path), true);
        $units = $data['units'] ?? [];

        $this->assertIsArray($units);
        $this->assertNotEmpty($units);

        foreach ($units as $code => $name) {
            $canonicalCode = (string) $code;
            $resolved      = UnitCodeList::resolve($canonicalCode);

            $this->assertIsString($name);
            $this->assertTrue(UnitCodeList::isCanonical($canonicalCode));
            $this->assertNotNull($resolved);
            $this->assertSame($canonicalCode, $resolved->value());
            $this->assertSame($name, UnitCodeList::name($canonicalCode));
        }
    }

    public function testRecognizesOnlyExactUneceUnitCodes(): void
    {
        $this->assertTrue(UnitCodeList::isCanonical('C62'));
        $this->assertTrue(UnitCodeList::isCanonical('KGM'));
        $this->assertFalse(UnitCodeList::isCanonical(' kgm '));
        $this->assertFalse(UnitCodeList::isCanonical('kgm'));
        $this->assertNotNull(UnitCodeList::name('HUR'));
        $this->assertFalse(UnitCodeList::isCanonical('KS'));
        $this->assertFalse(UnitCodeList::isCanonical('ks'));
        $this->assertFalse(UnitCodeList::isCanonical('NOT-A-UNIT'));
        $this->assertNull(UnitCodeList::resolve('ks'));
        $this->assertNull(UnitCodeList::resolve(' kgm '));
        $this->assertNull(UnitCodeList::name('kgm'));
        $this->assertNull(UnitCodeList::name('NOT-A-UNIT'));
    }

    public function testDoesNotInventAStandardCodeForUnknownUnitLabels(): void
    {
        $this->assertNull(UnitCodeList::resolve('neznáma jednotka'));
    }

    public function testCountryCurrencyAndUnitResolversReturnTypedCodes(): void
    {
        $this->assertSame('SK', CountryCodeList::resolve('sk')->value());
        $this->assertInstanceOf(CurrencyCode::class, CurrencyCodeList::resolve('EUR'));
        $this->assertInstanceOf(UnitCode::class, UnitCodeList::resolve('C62'));
    }

    public function testAppendsElementsInTheirUblNamespaces(): void
    {
        $document = new DOMDocument();
        $root     = $document->appendChild($document->createElement('Invoice'));

        $party = UblElementWriter::appendCac($root, 'Party');
        $name  = UblElementWriter::appendCbc($party, 'Name', 'Synthetic & Company');
        $empty = UblElementWriter::appendCbc($party, 'Note');

        $this->assertSame(UblNamespaces::COMMON_AGGREGATE_COMPONENTS, $party->namespaceURI);
        $this->assertSame(UblNamespaces::COMMON_BASIC_COMPONENTS, $name->namespaceURI);
        $this->assertSame('Synthetic & Company', $name->textContent);
        $this->assertFalse($empty->hasChildNodes());
    }

    public function testRejectsElementsOutsideCbcAndCac(): void
    {
        $document = new DOMDocument();
        $root     = $document->appendChild($document->createElement('Invoice'));

        $this->expectException(InvalidArgumentException::class);
        UblElementWriter::appendCbc($root, 'ext:UBLExtensions');
    }

    public function testRejectsNamesThatAreNotLocalElementNames(): void
    {
        $document = new DOMDocument();
        $root     = $document->appendChild($document->createElement('Invoice'));

        try {
            UblElementWriter::appendCbc($root, '');
            $this->fail('An empty local name must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        UblElementWriter::appendCac($root, 'cac:Party');
    }
}
