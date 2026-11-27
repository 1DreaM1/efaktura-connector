<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

/** Item price discount in cac:Price/cac:AllowanceCharge. */
final class UblDraftPriceAllowance
{
    /** Discount applied to the unit price, written to cac:Price/cac:AllowanceCharge/cbc:Amount. */
    private UblDecimal $amount;
    /** Optional pre-discount price, written to cac:AllowanceCharge/cbc:BaseAmount. */
    private ?UblDecimal $baseAmount;

    /**
     * Creates an item price allowance.
     *
     * @param UblDecimal      $amount     Discount amount.
     * @param UblDecimal|null $baseAmount Amount before the discount, when supplied.
     */
    public function __construct(UblDecimal $amount, ?UblDecimal $baseAmount = null)
    {
        $this->amount     = $amount;
        $this->baseAmount = $baseAmount;
    }

    /** Returns the item price discount amount. */
    public function amount(): UblDecimal
    {
        return $this->amount;
    }

    /** Returns the item price before discount, or null when it is omitted. */
    public function baseAmount(): ?UblDecimal
    {
        return $this->baseAmount;
    }
}
