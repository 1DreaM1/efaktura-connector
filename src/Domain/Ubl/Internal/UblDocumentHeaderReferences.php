<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Internal;

/** @internal Buyer, order, note and preceding-document references read from a UBL header. */
final class UblDocumentHeaderReferences
{
    private ?string $buyerReference;
    private ?string $orderReference;
    /** @var string[] */
    private array $notes;
    private ?string $precedingInvoiceNumber;

    /** @param string[] $notes */
    public function __construct(
        ?string $buyerReference,
        ?string $orderReference,
        array $notes,
        ?string $precedingInvoiceNumber
    ) {
        $this->buyerReference         = $buyerReference;
        $this->orderReference         = $orderReference;
        $this->notes                  = $notes;
        $this->precedingInvoiceNumber = $precedingInvoiceNumber;
    }

    public function buyerReference(): ?string
    {
        return $this->buyerReference;
    }

    public function orderReference(): ?string
    {
        return $this->orderReference;
    }

    public function note(): ?string
    {
        return $this->notes[0] ?? null;
    }

    /** @return string[] */
    public function notes(): array
    {
        return $this->notes;
    }

    public function precedingInvoiceNumber(): ?string
    {
        return $this->precedingInvoiceNumber;
    }
}
