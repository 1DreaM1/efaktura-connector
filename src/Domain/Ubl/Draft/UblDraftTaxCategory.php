<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\TaxCategoryCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\VatExemptionReasonCode;

/**
 * A VAT category (cac:ClassifiedTaxCategory of a line, cac:TaxCategory of a VAT breakdown).
 * The exemption reason is written only in the VAT breakdown, as EN 16931 requires.
 */
final class UblDraftTaxCategory
{
    /** VAT category code written to the selected tax-category element's cbc:ID. */
    private TaxCategoryCode $category;
    /** Optional VAT percentage written to cbc:Percent. */
    private ?UblDecimal $percent;
    /** Optional exemption code written to cbc:TaxExemptionReasonCode in the VAT breakdown. */
    private ?VatExemptionReasonCode $exemptionReasonCode;
    /** Optional exemption explanation written to cbc:TaxExemptionReason in the VAT breakdown. */
    private ?string $exemptionReason;

    /**
     * Creates a VAT category and optional rate or exemption details.
     * @param TaxCategoryCode             $category            VAT category code from CodeList\TaxCategoryCode.
     * @param UblDecimal|null             $percent             VAT rate for this category; null omits cbc:Percent when the category has no rate.
     * @param VatExemptionReasonCode|null $exemptionReasonCode Coded reason why tax is exempt, when applicable.
     * @param string|null                 $exemptionReason     Text explanation for the tax exemption, when applicable.
     */
    public function __construct(
        TaxCategoryCode $category,
        ?UblDecimal $percent,
        ?VatExemptionReasonCode $exemptionReasonCode = null,
        ?string $exemptionReason = null
    ) {
        $this->category            = $category;
        $this->percent             = $percent;
        $this->exemptionReasonCode = $exemptionReasonCode;
        $this->exemptionReason     = UblDraftText::emptyToNull($exemptionReason);
    }

    /**
     * Returns the category.
     *
     * @return TaxCategoryCode
     */
    public function category(): TaxCategoryCode
    {
        return $this->category;
    }

    /**
     * Returns the percent.
     *
     * @return UblDecimal|null
     */
    public function percent(): ?UblDecimal
    {
        return $this->percent;
    }

    /** Returns the exemption reason code from CodeList\VatExemptionReasonCode, or null when omitted. */
    public function exemptionReasonCode(): ?VatExemptionReasonCode
    {
        return $this->exemptionReasonCode;
    }

    /**
     * Returns the exemption reason.
     *
     * @return string|null
     */
    public function exemptionReason(): ?string
    {
        return $this->exemptionReason;
    }
}
