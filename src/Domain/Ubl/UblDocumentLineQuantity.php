<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl;

use DreaM1\EfakturaConnector\Domain\Ubl\Code\UnitCode;

/** Quantity of a UBL line with its unit of measure; each value is null when the document leaves it out. */
final class UblDocumentLineQuantity
{
    private ?string $quantity;
    private ?string $unit;
    private ?UnitCode $recognizedUnitCode;

    /**
     * Creates a line quantity.
     *
     * @param string|null   $quantity           Quantity invoiced on the line.
     * @param string|null   $unit               Unit of measure as written in the document.
     * @param UnitCode|null $recognizedUnitCode Canonical unit when recognized by the bundled list.
     */
    public function __construct(?string $quantity = null, ?string $unit = null, ?UnitCode $recognizedUnitCode = null)
    {
        $this->quantity           = $quantity;
        $this->unit               = $unit;
        $this->recognizedUnitCode = $recognizedUnitCode;
    }

    /** Returns the quantity. */
    public function quantity(): ?string
    {
        return $this->quantity;
    }

    /** UN/ECE Recommendation 20 unit code (@unitCode). */
    public function unit(): ?string
    {
        return $this->unit;
    }

    /** Returns the recognized canonical unit while unit() preserves the original document text. */
    public function recognizedUnitCode(): ?UnitCode
    {
        return $this->recognizedUnitCode;
    }
}
