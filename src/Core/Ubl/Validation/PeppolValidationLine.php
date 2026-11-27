<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

/** Invoice or credit note line values and line adjustments attached to a Peppol validation finding. */
final class PeppolValidationLine
{
    private string $name;
    private string $id;
    private string $quantity;
    private string $unitPrice;
    private string $baseQuantity;
    private string $lineAmount;
    private string $currency;

    /** @var list<PeppolValidationAdjustment> */
    private array $adjustments;

    /**
     * Creates a new PeppolValidationLine value.
     * @param PeppolValidationAdjustment[] $adjustments  Line allowances or charges applied when calculating totals.
     * @param string                       $name         Name of the invoice or credit note line item.
     * @param string                       $id           Identifier of the invoice or credit note line.
     * @param string                       $quantity     Quantity invoiced or credited for the line item.
     * @param string                       $unitPrice    Price per unit used for the document line.
     * @param string                       $baseQuantity Quantity basis specified for the unit price.
     * @param string                       $lineAmount   Monetary total for the document line.
     * @param string                       $currency     Currency code used for the monetary value.
     */
    public function __construct(
        string $name,
        string $id,
        string $quantity,
        string $unitPrice,
        string $baseQuantity,
        string $lineAmount,
        string $currency,
        array $adjustments
    ) {
        $this->name         = $name;
        $this->id           = $id;
        $this->quantity     = $quantity;
        $this->unitPrice    = $unitPrice;
        $this->baseQuantity = $baseQuantity;
        $this->lineAmount   = $lineAmount;
        $this->currency     = $currency;
        $this->adjustments  = array_values($adjustments);
    }

    /**
     * Returns the billing line context as a stable serialization shape.
     *
     * @return array{name: string, id: string, quantity: string, unit_price: string, base_quantity: string, line_amount: string, currency: string, adjustments: list<array{amount: string, charge: bool}>}
     */
    public function toArray(): array
    {
        return [
            'name'          => $this->name,
            'id'            => $this->id,
            'quantity'      => $this->quantity,
            'unit_price'    => $this->unitPrice,
            'base_quantity' => $this->baseQuantity,
            'line_amount'   => $this->lineAmount,
            'currency'      => $this->currency,
            'adjustments'   => array_map(
                static fn (PeppolValidationAdjustment $adjustment): array => $adjustment->toArray(),
                $this->adjustments
            ),
        ];
    }
}
