<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Writer;

use DOMElement;
use DOMException;
use DreaM1\EfakturaConnector\Core\Ubl\UblElementName;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\TaxSchemeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\VatExemptionReasonCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftTaxCategory;

/** Writes the shared UBL tax category aggregate. */
final class UblTaxCategoryWriter
{
    /**
     * Appends a tax breakdown category with any exemption reason fields.
     *
     * @param  DOMElement          $parent   Tax subtotal element.
     * @param  UblDraftTaxCategory $category Tax category and rate to serialize.
     * @throws DOMException        when a UBL element cannot be created
     */
    public static function appendTaxBreakdownCategory(DOMElement $parent, UblDraftTaxCategory $category): void
    {
        // TaxSubtotal/TaxCategory writes the category ID/rate, optional exemption reasons, and VAT TaxScheme/ID.
        self::appendTaxCategory(
            $parent,
            UblElementName::TAX_CATEGORY,
            $category,
            $category->exemptionReasonCode(),
            $category->exemptionReason()
        );
    }

    /**
     * Appends a tax category aggregate and returns the created element.
     *
     * @param  DOMElement                  $parent              Parent element receiving the category.
     * @param  string                      $name                UBL tax-category element name.
     * @param  UblDraftTaxCategory         $category            Tax category and rate to serialize.
     * @param  VatExemptionReasonCode|null $exemptionReasonCode Coded reason why tax is exempt.
     * @param  string|null                 $exemptionReason     Text explanation for the tax exemption.
     * @return DOMElement                  Created category element.
     * @throws DOMException                when a UBL element cannot be created
     */
    public static function appendTaxCategory(
        DOMElement $parent,
        string $name,
        UblDraftTaxCategory $category,
        ?VatExemptionReasonCode $exemptionReasonCode = null,
        ?string $exemptionReason = null
    ): DOMElement {
        // The requested aggregate (for example ClassifiedTaxCategory on Item) contains ID, Percent, and TaxScheme/ID.
        $element = UblElementWriter::appendCac($parent, $name);
        UblElementWriter::appendCbc($element, UblElementName::ID, $category->category()->value());
        UblElementWriter::appendOptionalDecimalCbc(
            $element,
            UblElementName::PERCENT,
            $category->percent()
        );
        UblElementWriter::appendOptionalCbc(
            $element,
            UblElementName::TAX_EXEMPTION_REASON_CODE,
            $exemptionReasonCode !== null ? $exemptionReasonCode->value() : null
        );
        UblElementWriter::appendOptionalCbc($element, UblElementName::TAX_EXEMPTION_REASON, $exemptionReason);
        $taxScheme = UblElementWriter::appendCac($element, UblElementName::TAX_SCHEME);
        UblElementWriter::appendCbc($taxScheme, UblElementName::ID, TaxSchemeCode::VAT);

        return $element;
    }
}
