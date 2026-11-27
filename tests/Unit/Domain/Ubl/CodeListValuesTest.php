<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain\Ubl;

use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\CreditNoteTypeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\DocumentTypeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\TaxCategoryCode;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CodeListResources;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CountryCodeList;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\PeppolBillingCodeList;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/** Tests UBL code values against the pinned Peppol Billing code lists. */
class CodeListValuesTest extends TestCase
{
    public function testCountryAndUnitCodeListsRecordThePinnedPeppolRelease(): void
    {
        $this->assertSame('2026-Q4-MR', CountryCodeList::PEPPOL_RELEASE);
        $this->assertSame('2026-Q4-MR', CodeListResources::UNECE_REC20_RELEASE);
    }

    public function testTaxCategoryConstantsMatchTheCompletePeppolList(): void
    {
        $codes         = array_values((new ReflectionClass(TaxCategoryCode::class))->getConstants());
        $expectedCodes = PeppolBillingCodeList::all(PeppolBillingCodeList::VAT_CATEGORY);
        sort($codes);
        sort($expectedCodes);

        $this->assertSame($expectedCodes, $codes);
        $this->assertSame('AE', TaxCategoryCode::REVERSE_CHARGE);
        $this->assertSame('G', TaxCategoryCode::FREE_EXPORT);
        $this->assertSame('K', TaxCategoryCode::INTRA_COMMUNITY_SUPPLY);
    }

    public function testInvoiceTypeConstantsCoverThePinnedPeppolInvoiceSubset(): void
    {
        $codes = (new ReflectionClass(DocumentTypeCode::class))->getConstants();
        unset($codes['CREDIT_NOTE']);
        $codes         = array_values($codes);
        $expectedCodes = PeppolBillingCodeList::all(PeppolBillingCodeList::INVOICE_TYPE);
        sort($codes);
        sort($expectedCodes);

        $this->assertSame($expectedCodes, $codes);
        $this->assertSame('384', DocumentTypeCode::CORRECTED_INVOICE);
    }

    public function testCreditNoteTypeConstantsCoverThePinnedPeppolCreditNoteSubset(): void
    {
        $codes         = array_values((new ReflectionClass(CreditNoteTypeCode::class))->getConstants());
        $expectedCodes = PeppolBillingCodeList::all(PeppolBillingCodeList::CREDIT_NOTE_TYPE);
        sort($codes);
        sort($expectedCodes);

        $this->assertSame($expectedCodes, $codes);
    }
}
