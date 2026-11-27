<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Writer;

use DOMElement;
use DOMException;
use DreaM1\EfakturaConnector\Core\Ubl\UblElementName;
use DreaM1\EfakturaConnector\Core\Xml\XmlBooleanValue;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDecimal;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftAllowanceCharge;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPriceAllowance;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftTaxCategory;

/** Writes document- and line-level AllowanceCharge aggregates. */
final class UblAllowanceChargeWriter
{
    /**
     * Appends a document- or line-level allowance or charge aggregate.
     *
     * @param  DOMElement               $parent      XML element that owns the aggregate.
     * @param  UblDraftAllowanceCharge  $draft       Allowance or charge values to serialize.
     * @param  string                   $currency    Currency code used by its amounts.
     * @param  UblDraftTaxCategory|null $taxCategory Optional document-level tax category.
     * @throws DOMException             when a UBL element cannot be created
     */
    public static function append(
        DOMElement $parent,
        UblDraftAllowanceCharge $draft,
        string $currency,
        ?UblDraftTaxCategory $taxCategory
    ): void {
        // AllowanceCharge contains its charge flag, reason fields, multiplier, Amount/BaseAmount, and optional TaxCategory.
        $allowance = UblElementWriter::appendCac($parent, UblElementName::ALLOWANCE_CHARGE);
        // The same aggregate is used on documents and lines. Only document-level allowance charges supply a category.
        UblElementWriter::appendCbc(
            $allowance,
            UblElementName::CHARGE_INDICATOR,
            $draft->isCharge() ? XmlBooleanValue::TRUE : XmlBooleanValue::FALSE
        );
        $reasonCode = $draft->reasonCode();
        UblElementWriter::appendOptionalCbc(
            $allowance,
            UblElementName::ALLOWANCE_CHARGE_REASON_CODE,
            $reasonCode !== null ? $reasonCode->value() : null
        );
        UblElementWriter::appendOptionalCbc($allowance, UblElementName::ALLOWANCE_CHARGE_REASON, $draft->reason());
        UblElementWriter::appendOptionalDecimalCbc(
            $allowance,
            UblElementName::MULTIPLIER_FACTOR_NUMERIC,
            $draft->multiplierFactor()
        );
        self::appendAmounts($allowance, $draft->amount(), $draft->baseAmount(), $currency);

        if ($taxCategory !== null) {
            UblTaxCategoryWriter::appendTaxCategory($allowance, UblElementName::TAX_CATEGORY, $taxCategory);
        }
    }

    /**
     * Writes a price-level discount using the shared AllowanceCharge amount fields.
     *
     * @param  DOMElement             $parent   Price element receiving the allowance aggregate.
     * @param  UblDraftPriceAllowance $draft    Price discount values to serialize.
     * @param  string                 $currency Currency code applied to the monetary amount.
     * @throws DOMException           when a UBL element cannot be created
     */
    public static function appendPriceAllowance(
        DOMElement $parent,
        UblDraftPriceAllowance $draft,
        string $currency
    ): void {
        // Price-level AllowanceCharge represents a discount, so ChargeIndicator is always false.
        $allowance = UblElementWriter::appendCac($parent, UblElementName::ALLOWANCE_CHARGE);
        UblElementWriter::appendCbc($allowance, UblElementName::CHARGE_INDICATOR, XmlBooleanValue::FALSE);
        self::appendAmounts($allowance, $draft->amount(), $draft->baseAmount(), $currency);
    }

    /** @throws DOMException */
    private static function appendAmounts(
        DOMElement $allowance,
        UblDecimal $amount,
        ?UblDecimal $baseAmount,
        string $currency
    ): void {
        UblElementWriter::appendDecimalAmount($allowance, UblElementName::AMOUNT, $amount, $currency);
        UblElementWriter::appendOptionalDecimalAmount(
            $allowance,
            UblElementName::BASE_AMOUNT,
            $baseAmount,
            $currency
        );
    }
}
