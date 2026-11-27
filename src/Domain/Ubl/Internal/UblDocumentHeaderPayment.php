<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Internal;

/** @internal Payment details read from a UBL header. */
final class UblDocumentHeaderPayment
{
    private ?string $paymentMeansCode;
    private ?string $paymentId;
    private bool $hasPaymentTerms;
    private ?string $iban;

    public function __construct(
        ?string $paymentMeansCode,
        ?string $paymentId,
        bool $hasPaymentTerms,
        ?string $iban
    ) {
        $this->paymentMeansCode = $paymentMeansCode;
        $this->paymentId        = $paymentId;
        $this->hasPaymentTerms  = $hasPaymentTerms;
        $this->iban             = $iban;
    }

    public function paymentMeansCode(): ?string
    {
        return $this->paymentMeansCode;
    }

    public function paymentId(): ?string
    {
        return $this->paymentId;
    }

    public function hasPaymentTerms(): bool
    {
        return $this->hasPaymentTerms;
    }

    public function iban(): ?string
    {
        return $this->iban;
    }
}
