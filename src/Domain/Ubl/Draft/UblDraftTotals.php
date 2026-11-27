<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** cac:TaxTotal and cac:LegalMonetaryTotal with typed UBL decimal amounts. */
final class UblDraftTotals
{
    /** Sum of line net amounts, written to LegalMonetaryTotal/LineExtensionAmount. */
    private UblDecimal $lineExtensionAmount;
    /** Document total before tax, written to LegalMonetaryTotal/TaxExclusiveAmount. */
    private UblDecimal $taxExclusiveAmount;
    /** Document total including tax before prepaid deductions, written to LegalMonetaryTotal/TaxInclusiveAmount. */
    private UblDecimal $taxInclusiveAmount;
    /** Optional payable adjustment, written to LegalMonetaryTotal/PayableRoundingAmount. */
    private ?UblDecimal $roundingAmount;
    /** Remaining amount due, written to LegalMonetaryTotal/PayableAmount. */
    private UblDecimal $payableAmount;
    /** Document tax total, written to the first TaxTotal/TaxAmount in document currency. */
    private UblDecimal $taxAmount;
    /** @var UblDraftTaxSubtotal[] VAT category tax bases and amounts in the first TaxTotal. */
    private array $taxSubtotals;
    /** Optional sum of document-level discounts, written to LegalMonetaryTotal/AllowanceTotalAmount. */
    private ?UblDecimal $allowanceTotalAmount = null;
    /** Optional sum of document-level charges, written to LegalMonetaryTotal/ChargeTotalAmount. */
    private ?UblDecimal $chargeTotalAmount = null;
    /** Optional amount already paid, written to LegalMonetaryTotal/PrepaidAmount. */
    private ?UblDecimal $prepaidAmount = null;
    /** Optional tax total in TaxCurrencyCode, written to a second TaxTotal/TaxAmount. */
    private ?UblDecimal $taxAmountInTaxCurrency = null;

    /**
     * Creates document-level tax and payable amounts with their VAT subtotals.
     * @param  UblDraftTaxSubtotal[] $taxSubtotals        Taxable bases and tax amounts grouped by VAT category.
     * @param  UblDecimal|null       $roundingAmount      Positive or negative payable-rounding adjustment; null omits
     *                                                    cbc:PayableRoundingAmount.
     * @param  UblDecimal            $lineExtensionAmount Total of the invoice or credit note lines, net of taxes.
     * @param  UblDecimal            $taxExclusiveAmount  Document total before taxes.
     * @param  UblDecimal            $taxInclusiveAmount  Document total including taxes, before subtracting any prepaid amount.
     * @param  UblDecimal            $payableAmount       Amount remaining for the customer to pay.
     * @param  UblDecimal            $taxAmount           Total tax amount for the document.
     * @throws InvalidInputException when an item in $taxSubtotals is not a UblDraftTaxSubtotal.
     */
    public function __construct(
        UblDecimal $lineExtensionAmount,
        UblDecimal $taxExclusiveAmount,
        UblDecimal $taxInclusiveAmount,
        ?UblDecimal $roundingAmount,
        UblDecimal $payableAmount,
        UblDecimal $taxAmount,
        array $taxSubtotals
    ) {
        $this->lineExtensionAmount = $lineExtensionAmount;
        $this->taxExclusiveAmount  = $taxExclusiveAmount;
        $this->taxInclusiveAmount  = $taxInclusiveAmount;
        $this->roundingAmount      = $roundingAmount;
        $this->payableAmount       = $payableAmount;
        $this->taxAmount           = $taxAmount;
        $this->taxSubtotals        = TypedArray::valuesOfType(
            $taxSubtotals,
            UblDraftTaxSubtotal::class,
            'Every tax subtotal must be a UblDraftTaxSubtotal.'
        );
    }

    /**
     * Returns the line extension amount.
     *
     * @return UblDecimal
     */
    public function lineExtensionAmount(): UblDecimal
    {
        return $this->lineExtensionAmount;
    }

    /**
     * Returns the tax exclusive amount.
     *
     * @return UblDecimal
     */
    public function taxExclusiveAmount(): UblDecimal
    {
        return $this->taxExclusiveAmount;
    }

    /**
     * Returns the tax inclusive amount.
     *
     * @return UblDecimal
     */
    public function taxInclusiveAmount(): UblDecimal
    {
        return $this->taxInclusiveAmount;
    }

    /**
     * Returns the rounding amount.
     *
     * @return UblDecimal|null
     */
    public function roundingAmount(): ?UblDecimal
    {
        return $this->roundingAmount;
    }

    /**
     * Returns the payable amount.
     *
     * @return UblDecimal
     */
    public function payableAmount(): UblDecimal
    {
        return $this->payableAmount;
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
     * Returns the VAT subtotals for the document totals.
     * @return UblDraftTaxSubtotal[]
     */
    public function taxSubtotals(): array
    {
        return $this->taxSubtotals;
    }

    /**
     * Returns a copy with optional document-level and accounting-currency totals.
     *
     * @param  UblDecimal|null $allowanceTotalAmount   Total allowance amount.
     * @param  UblDecimal|null $chargeTotalAmount      Total charge amount.
     * @param  UblDecimal|null $prepaidAmount          Amount already paid by the customer.
     * @param  UblDecimal|null $taxAmountInTaxCurrency Tax amount expressed in the accounting currency.
     * @return self
     */
    public function withAdditionalAmounts(
        ?UblDecimal $allowanceTotalAmount = null,
        ?UblDecimal $chargeTotalAmount = null,
        ?UblDecimal $prepaidAmount = null,
        ?UblDecimal $taxAmountInTaxCurrency = null
    ): self {
        $totals                         = clone $this;
        $totals->allowanceTotalAmount   = $allowanceTotalAmount;
        $totals->chargeTotalAmount      = $chargeTotalAmount;
        $totals->prepaidAmount          = $prepaidAmount;
        $totals->taxAmountInTaxCurrency = $taxAmountInTaxCurrency;

        return $totals;
    }

    /** Returns the total document-level allowance amount, or null when it is omitted. */
    public function allowanceTotalAmount(): ?UblDecimal
    {
        return $this->allowanceTotalAmount;
    }

    /** Returns the total document-level charge amount, or null when it is omitted. */
    public function chargeTotalAmount(): ?UblDecimal
    {
        return $this->chargeTotalAmount;
    }

    /** Returns the amount already prepaid, or null when it is omitted. */
    public function prepaidAmount(): ?UblDecimal
    {
        return $this->prepaidAmount;
    }

    /** Returns the tax amount in the accounting currency, or null when it is omitted. */
    public function taxAmountInTaxCurrency(): ?UblDecimal
    {
        return $this->taxAmountInTaxCurrency;
    }
}
