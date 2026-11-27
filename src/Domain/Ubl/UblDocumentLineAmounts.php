<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl;

use DreaM1\EfakturaConnector\Domain\Ubl\Code\CurrencyCode;

/** Monetary values of a UBL line and their currency; each value is null when the document leaves it out. */
final class UblDocumentLineAmounts
{
    private ?string $unitPrice;
    private ?string $lineTotal;
    private ?string $currency;
    private ?CurrencyCode $recognizedCurrencyCode;

    /**
     * Creates the monetary values of one line.
     *
     * @param string|null       $unitPrice              Item price from cbc:PriceAmount.
     * @param string|null       $lineTotal              Line total including line allowances or charges, net of tax.
     * @param string|null       $currency               Currency associated with the line's monetary values.
     * @param CurrencyCode|null $recognizedCurrencyCode Canonical currency when recognized by the bundled list.
     */
    public function __construct(
        ?string $unitPrice = null,
        ?string $lineTotal = null,
        ?string $currency = null,
        ?CurrencyCode $recognizedCurrencyCode = null
    ) {
        $this->unitPrice              = $unitPrice;
        $this->lineTotal              = $lineTotal;
        $this->currency               = $currency;
        $this->recognizedCurrencyCode = $recognizedCurrencyCode;
    }

    /** Returns the unit price. */
    public function unitPrice(): ?string
    {
        return $this->unitPrice;
    }

    /** Returns the line total. */
    public function lineTotal(): ?string
    {
        return $this->lineTotal;
    }

    /** Returns the currency as written in the document. */
    public function currency(): ?string
    {
        return $this->currency;
    }

    /** Returns the recognized canonical currency while currency() preserves the original document text. */
    public function recognizedCurrencyCode(): ?CurrencyCode
    {
        return $this->recognizedCurrencyCode;
    }
}
