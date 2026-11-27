<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

/** cac:TaxSubtotal of the VAT breakdown with typed UBL decimal amounts. */
final class UblDraftTaxSubtotal
{
    /** Tax base written to cac:TaxSubtotal/cbc:TaxableAmount. */
    private UblDecimal $taxableAmount;
    /** Tax calculated for the base, written to cac:TaxSubtotal/cbc:TaxAmount. */
    private UblDecimal $taxAmount;
    /** VAT code/rate written to cac:TaxSubtotal/cac:TaxCategory. */
    private UblDraftTaxCategory $category;

    /**
     * @param UblDecimal          $taxableAmount Net amount to which the category's tax rate is applied.
     * @param UblDecimal          $taxAmount     Tax calculated for this category's taxable amount.
     * @param UblDraftTaxCategory $category      VAT category and rate represented by this subtotal.
     */
    public function __construct(UblDecimal $taxableAmount, UblDecimal $taxAmount, UblDraftTaxCategory $category)
    {
        $this->taxableAmount = $taxableAmount;
        $this->taxAmount     = $taxAmount;
        $this->category      = $category;
    }

    /**
     * Returns the taxable amount.
     *
     * @return UblDecimal
     */
    public function taxableAmount(): UblDecimal
    {
        return $this->taxableAmount;
    }

    /**
     * Returns the tax amount.
     *
     * @return UblDecimal
     */
    public function taxAmount(): UblDecimal
    {
        return $this->taxAmount;
    }

    /**
     * Returns the category.
     *
     * @return UblDraftTaxCategory
     */
    public function category(): UblDraftTaxCategory
    {
        return $this->category;
    }
}
