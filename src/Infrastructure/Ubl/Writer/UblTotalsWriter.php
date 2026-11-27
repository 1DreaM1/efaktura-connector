<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Writer;

use DOMElement;
use DOMException;
use DreaM1\EfakturaConnector\Core\Ubl\UblElementName;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentDraft;

/** Writes document tax totals and legal monetary totals. */
final class UblTotalsWriter
{
    /**
     * Appends document tax totals and legal monetary totals in UBL schema order.
     *
     * @param  DOMElement       $root  UBL Invoice or CreditNote root element.
     * @param  UblDocumentDraft $draft Document totals and currency values to serialize.
     * @throws DOMException     when a UBL element cannot be created
     */
    public static function appendTotals(DOMElement $root, UblDocumentDraft $draft): void
    {
        $currency = $draft->currency()->value();

        // The first TaxTotal and its subtotals use document currency; the optional second total uses tax currency.
        self::appendTaxTotal($root, $draft, $currency);
        self::appendTaxTotalInTaxCurrency($root, $draft);

        // Legal monetary totals follow tax totals and are written in the sequence defined by the UBL schema.
        self::appendLegalMonetaryTotal($root, $draft, $currency);
    }

    /**
     * Appends the TaxTotal in document currency: TaxAmount plus one TaxSubtotal per tax category, each with
     * TaxableAmount, TaxAmount and TaxCategory.
     *
     * @throws DOMException when a UBL element cannot be created
     */
    private static function appendTaxTotal(DOMElement $root, UblDocumentDraft $draft, string $currency): void
    {
        $totals = $draft->totals();
        $tax    = UblElementWriter::appendCac($root, UblElementName::TAX_TOTAL);
        UblElementWriter::appendDecimalAmount($tax, UblElementName::TAX_AMOUNT, $totals->taxAmount(), $currency);

        foreach ($totals->taxSubtotals() as $taxSubtotal) {
            $subtotal = UblElementWriter::appendCac($tax, UblElementName::TAX_SUBTOTAL);
            UblElementWriter::appendDecimalAmount(
                $subtotal,
                UblElementName::TAXABLE_AMOUNT,
                $taxSubtotal->taxableAmount(),
                $currency
            );
            UblElementWriter::appendDecimalAmount(
                $subtotal,
                UblElementName::TAX_AMOUNT,
                $taxSubtotal->taxAmount(),
                $currency
            );
            UblTaxCategoryWriter::appendTaxBreakdownCategory($subtotal, $taxSubtotal->category());
        }
    }

    /**
     * Appends the second TaxTotal, which holds only TaxAmount in the separate tax currency, when the draft has one.
     *
     * @throws DOMException when a UBL element cannot be created
     */
    private static function appendTaxTotalInTaxCurrency(DOMElement $root, UblDocumentDraft $draft): void
    {
        $taxCurrencyCode = $draft->taxCurrencyCode();
        $amount          = $draft->totals()->taxAmountInTaxCurrency();

        if ($taxCurrencyCode === null || $amount === null) {
            return;
        }

        $taxTotal = UblElementWriter::appendCac($root, UblElementName::TAX_TOTAL);
        UblElementWriter::appendDecimalAmount($taxTotal, UblElementName::TAX_AMOUNT, $amount, $taxCurrencyCode->value());
    }

    /**
     * Appends LegalMonetaryTotal in schema order: LineExtensionAmount, TaxExclusiveAmount, TaxInclusiveAmount,
     * optional AllowanceTotalAmount, ChargeTotalAmount, PrepaidAmount and PayableRoundingAmount, then PayableAmount.
     *
     * @throws DOMException when a UBL element cannot be created
     */
    private static function appendLegalMonetaryTotal(DOMElement $root, UblDocumentDraft $draft, string $currency): void
    {
        $totals = $draft->totals();
        $legal  = UblElementWriter::appendCac($root, UblElementName::LEGAL_MONETARY_TOTAL);

        // The required amounts are never null by type, so the optional appender writes each of them as well.
        $amounts = [
            [UblElementName::LINE_EXTENSION_AMOUNT, $totals->lineExtensionAmount()],
            [UblElementName::TAX_EXCLUSIVE_AMOUNT, $totals->taxExclusiveAmount()],
            [UblElementName::TAX_INCLUSIVE_AMOUNT, $totals->taxInclusiveAmount()],
            [UblElementName::ALLOWANCE_TOTAL_AMOUNT, $totals->allowanceTotalAmount()],
            [UblElementName::CHARGE_TOTAL_AMOUNT, $totals->chargeTotalAmount()],
            [UblElementName::PREPAID_AMOUNT, $totals->prepaidAmount()],
            [UblElementName::PAYABLE_ROUNDING_AMOUNT, $totals->roundingAmount()],
            [UblElementName::PAYABLE_AMOUNT, $totals->payableAmount()],
        ];

        foreach ($amounts as [$name, $amount]) {
            UblElementWriter::appendOptionalDecimalAmount($legal, $name, $amount, $currency);
        }
    }
}
