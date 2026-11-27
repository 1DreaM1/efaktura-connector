<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl;

/** VAT rate and category applied to a UBL line; each value is null when the document leaves it out. */
final class UblDocumentLineVat
{
    private ?string $rate;
    private ?string $category;

    /**
     * Creates the VAT details of one line.
     *
     * @param string|null $rate     VAT rate applied to the line.
     * @param string|null $category VAT category code applied to the line.
     */
    public function __construct(?string $rate = null, ?string $category = null)
    {
        $this->rate     = $rate;
        $this->category = $category;
    }

    /** Returns the VAT rate. */
    public function rate(): ?string
    {
        return $this->rate;
    }

    /** Returns the VAT category code. */
    public function category(): ?string
    {
        return $this->category;
    }
}
