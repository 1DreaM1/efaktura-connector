<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

/** A documented normalization applied while interpreting a Schematron finding. */
final class PeppolValidationAdjustment
{
    private string $amount;
    private bool $charge;

    /**
     * @param string $amount Monetary amount used by the document or calculation.
     * @param bool   $charge Document charge included in the monetary totals.
     */
    public function __construct(string $amount, bool $charge)
    {
        $this->amount = $amount;
        $this->charge = $charge;
    }

    /**
     * Returns the adjustment as a stable serialization shape.
     *
     * @return array{amount: string, charge: bool}
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'charge' => $this->charge,
        ];
    }
}
