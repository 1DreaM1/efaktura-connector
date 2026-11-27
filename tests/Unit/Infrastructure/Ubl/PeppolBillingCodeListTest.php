<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\Ubl;

use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CodeListResources;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\PeppolBillingCodeList;
use PHPUnit\Framework\TestCase;

/** Tests supplementary Peppol BIS Billing code lists bundled with the package. */
class PeppolBillingCodeListTest extends TestCase
{
    public function testBundledResourceMatchesItsPinnedPeppolReleaseAndEntryCounts(): void
    {
        $path     = CodeListResources::path(CodeListResources::PEPPOL_BILLING_CODE_LISTS_FILE);
        $resource = json_decode((string) file_get_contents($path), true);

        $this->assertFileExists($path);
        $this->assertIsArray($resource);
        $this->assertSame(CodeListResources::PEPPOL_BILLING_CODE_LISTS_RELEASE, $resource['peppol_release'] ?? null);
        $this->assertSame(PeppolBillingCodeList::release(), $resource['peppol_release'] ?? null);
        $this->assertSame(array_keys($this->expectedCodeCounts()), array_keys($resource['lists'] ?? []));

        foreach ($this->expectedCodeCounts() as $identifier => $expectedCount) {
            $list = $resource['lists'][$identifier] ?? null;
            $this->assertIsArray($list, sprintf('Missing list "%s".', $identifier));
            $this->assertSame($expectedCount, count($list['codes'] ?? []), $identifier);
            $this->assertSame($list['source'] ?? null, PeppolBillingCodeList::source($identifier));
            $this->assertSame(
                is_string($list['version'] ?? null) && trim($list['version']) !== '' ? $list['version'] : null,
                PeppolBillingCodeList::version($identifier)
            );
        }
    }

    public function testEveryBundledEntryIsAvailableThroughTheLookupMethods(): void
    {
        $resource = CodeListResources::readJsonArray(CodeListResources::PEPPOL_BILLING_CODE_LISTS_FILE);
        $lists    = $resource['lists'] ?? [];

        $this->assertIsArray($lists);

        foreach ($lists as $identifier => $list) {
            $codes         = $list['codes'] ?? [];
            $expectedCodes = array_map('strval', array_keys($codes));
            $this->assertSame($expectedCodes, PeppolBillingCodeList::all($identifier));

            foreach ($codes as $code => $name) {
                $code = (string) $code;
                $this->assertIsString($name);
                $this->assertNotSame('', trim($name));
                $this->assertTrue(PeppolBillingCodeList::contains($identifier, $code));
                $this->assertTrue(PeppolBillingCodeList::isCanonical($identifier, $code));
                $this->assertSame($code, PeppolBillingCodeList::resolve($identifier, $code));
                $this->assertSame($name, PeppolBillingCodeList::name($identifier, $code));
                $this->assertSame($code . ' – ' . $name, PeppolBillingCodeList::options($identifier)[$code]);
            }
        }
    }

    public function testResolvesRepresentativeCodesFromEachPeppolBillingList(): void
    {
        $codes = [
            PeppolBillingCodeList::ICD                    => '0245',
            PeppolBillingCodeList::VAT_CATEGORY           => 'AE',
            PeppolBillingCodeList::ITEM_TYPE              => 'AA',
            PeppolBillingCodeList::INVOICE_TYPE           => '384',
            PeppolBillingCodeList::CREDIT_NOTE_TYPE       => '381',
            PeppolBillingCodeList::INVOICED_OBJECT_SCHEME => 'AAA',
            PeppolBillingCodeList::VAT_DATE_CODE          => '3',
            PeppolBillingCodeList::PAYMENT_MEANS          => '58',
            PeppolBillingCodeList::ALLOWANCE_REASON       => '95',
            PeppolBillingCodeList::CHARGE_REASON          => 'ZZZ',
            PeppolBillingCodeList::MIME                   => 'application/pdf',
            PeppolBillingCodeList::SEPA                   => 'SEPA',
            PeppolBillingCodeList::VATEX                  => 'VATEX-EU-O',
        ];

        foreach ($codes as $identifier => $code) {
            $this->assertSame($code, PeppolBillingCodeList::resolve($identifier, $code), $identifier);
            $this->assertNotNull(PeppolBillingCodeList::name($identifier, $code), $identifier);
        }
    }

    public function testCodeLookupRequiresExactCanonicalValuesAndKnownListIdentifiers(): void
    {
        $this->assertTrue(PeppolBillingCodeList::contains(PeppolBillingCodeList::VATEX, 'VATEX-EU-G'));
        $this->assertFalse(PeppolBillingCodeList::contains(PeppolBillingCodeList::VATEX, 'vatex-eu-g'));
        $this->assertFalse(PeppolBillingCodeList::contains(PeppolBillingCodeList::MIME, ' application/pdf'));
        $this->assertFalse(PeppolBillingCodeList::contains(PeppolBillingCodeList::PAYMENT_MEANS, '999'));
        $this->assertFalse(PeppolBillingCodeList::contains(PeppolBillingCodeList::PAYMENT_MEANS, null));
        $this->assertNull(PeppolBillingCodeList::resolve(PeppolBillingCodeList::VATEX, 'VATEX-EU-UNKNOWN'));
        $this->assertNull(PeppolBillingCodeList::name(PeppolBillingCodeList::VATEX, 'VATEX-EU-UNKNOWN'));
        $this->assertSame([], PeppolBillingCodeList::all('unknown-list'));
        $this->assertNull(PeppolBillingCodeList::source('unknown-list'));
        $this->assertNull(PeppolBillingCodeList::version('unknown-list'));
    }
    /**
     * Returns the number of entries published for each list in the pinned release.
     *
     * @return array<string, int>
     */
    private function expectedCodeCounts(): array
    {
        return [
            PeppolBillingCodeList::ICD                    => 243,
            PeppolBillingCodeList::VAT_CATEGORY           => 10,
            PeppolBillingCodeList::ITEM_TYPE              => 185,
            PeppolBillingCodeList::INVOICE_TYPE           => 27,
            PeppolBillingCodeList::CREDIT_NOTE_TYPE       => 5,
            PeppolBillingCodeList::INVOICED_OBJECT_SCHEME => 818,
            PeppolBillingCodeList::VAT_DATE_CODE          => 3,
            PeppolBillingCodeList::PAYMENT_MEANS          => 84,
            PeppolBillingCodeList::ALLOWANCE_REASON       => 19,
            PeppolBillingCodeList::CHARGE_REASON          => 178,
            PeppolBillingCodeList::MIME                   => 6,
            PeppolBillingCodeList::SEPA                   => 1,
            PeppolBillingCodeList::VATEX                  => 88,
        ];
    }
}
