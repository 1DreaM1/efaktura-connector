<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\AllowanceChargeReasonCode;

/** Document-level or InvoiceLine/CreditNoteLine cac:AllowanceCharge. */
final class UblDraftAllowanceCharge
{
    /** Whether ChargeIndicator is true (charge) or false (allowance). */
    private bool $charge;
    /** Monetary adjustment written to cbc:Amount. */
    private UblDecimal $amount;
    /** Tax category for taxable document-level adjustments, written to cac:TaxCategory. */
    private ?UblDraftTaxCategory $taxCategory;
    /** Coded reason written to cbc:AllowanceChargeReasonCode. */
    private ?AllowanceChargeReasonCode $reasonCode;
    /** Free-text explanation written to cbc:AllowanceChargeReason. */
    private ?string $reason;
    /** Optional percentage factor written to cbc:MultiplierFactorNumeric. */
    private ?UblDecimal $multiplierFactor;
    /** Optional amount to which the multiplier applies, written to cbc:BaseAmount. */
    private ?UblDecimal $baseAmount;

    /**
     * Creates a document-level or line-level allowance or charge.
     *
     * @param bool                           $charge           True for a charge and false for an allowance.
     * @param UblDecimal                     $amount           Amount of the allowance or charge.
     * @param UblDraftTaxCategory|null       $taxCategory      VAT category required when the adjustment is taxable.
     * @param AllowanceChargeReasonCode|null $reasonCode       Coded reason for the adjustment, when supplied.
     * @param string|null                    $reason           Text reason for the adjustment, when supplied.
     * @param UblDecimal|null                $multiplierFactor Percentage factor used to calculate the adjustment, when applicable.
     * @param UblDecimal|null                $baseAmount       Amount to which the multiplier factor applies, when applicable.
     */
    public function __construct(
        bool $charge,
        UblDecimal $amount,
        ?UblDraftTaxCategory $taxCategory = null,
        ?AllowanceChargeReasonCode $reasonCode = null,
        ?string $reason = null,
        ?UblDecimal $multiplierFactor = null,
        ?UblDecimal $baseAmount = null
    ) {
        $this->charge           = $charge;
        $this->amount           = $amount;
        $this->taxCategory      = $taxCategory;
        $this->reasonCode       = $reasonCode;
        $this->reason           = UblDraftText::emptyToNull($reason);
        $this->multiplierFactor = $multiplierFactor;
        $this->baseAmount       = $baseAmount;
    }

    /** Whether this adjustment is a charge; false means it is an allowance. */
    public function isCharge(): bool
    {
        return $this->charge;
    }

    /** Returns the adjustment amount. */
    public function amount(): UblDecimal
    {
        return $this->amount;
    }

    /** Returns the VAT category, or null when the adjustment has no tax category. */
    public function taxCategory(): ?UblDraftTaxCategory
    {
        return $this->taxCategory;
    }

    /** Returns the coded adjustment reason, or null when it is omitted. */
    public function reasonCode(): ?AllowanceChargeReasonCode
    {
        return $this->reasonCode;
    }

    /** Returns the adjustment reason text, or null when it is omitted. */
    public function reason(): ?string
    {
        return $this->reason;
    }

    /** Returns the percentage factor, or null when the adjustment is not factor-based. */
    public function multiplierFactor(): ?UblDecimal
    {
        return $this->multiplierFactor;
    }

    /** Returns the factor base amount, or null when it is omitted. */
    public function baseAmount(): ?UblDecimal
    {
        return $this->baseAmount;
    }
}
