<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Internal;

use DreaM1\EfakturaConnector\Domain\Ubl\Code\CurrencyCode;

/** @internal Currency and monetary totals read from a UBL header. */
final class UblDocumentHeaderTotals
{
    private ?string $currency;
    private ?CurrencyCode $recognizedCurrencyCode;
    private ?string $payableAmount;
    private ?string $taxExclusiveAmount;
    private ?string $taxInclusiveAmount;
    private ?string $lineExtensionAmount;
    private ?string $taxAmount;
    private ?string $roundingAmount;

    public function __construct(
        ?string $currency,
        ?CurrencyCode $recognizedCurrencyCode,
        ?string $payableAmount,
        ?string $taxExclusiveAmount,
        ?string $taxInclusiveAmount,
        ?string $lineExtensionAmount,
        ?string $taxAmount,
        ?string $roundingAmount
    ) {
        $this->currency               = $currency;
        $this->recognizedCurrencyCode = $recognizedCurrencyCode;
        $this->payableAmount          = $payableAmount;
        $this->taxExclusiveAmount     = $taxExclusiveAmount;
        $this->taxInclusiveAmount     = $taxInclusiveAmount;
        $this->lineExtensionAmount    = $lineExtensionAmount;
        $this->taxAmount              = $taxAmount;
        $this->roundingAmount         = $roundingAmount;
    }

    public function currency(): ?string
    {
        return $this->currency;
    }

    public function recognizedCurrencyCode(): ?CurrencyCode
    {
        return $this->recognizedCurrencyCode;
    }

    public function payableAmount(): ?string
    {
        return $this->payableAmount;
    }

    public function taxExclusiveAmount(): ?string
    {
        return $this->taxExclusiveAmount;
    }

    public function taxInclusiveAmount(): ?string
    {
        return $this->taxInclusiveAmount;
    }

    public function lineExtensionAmount(): ?string
    {
        return $this->lineExtensionAmount;
    }

    public function taxAmount(): ?string
    {
        return $this->taxAmount;
    }

    public function roundingAmount(): ?string
    {
        return $this->roundingAmount;
    }
}
