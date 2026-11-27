<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Internal;

use DreaM1\EfakturaConnector\Domain\Ubl\LocalDate;

/** @internal Date text and tolerant parsed dates read from a UBL header. */
final class UblDocumentHeaderDates
{
    private ?string $issueDateText;
    private ?LocalDate $issueDate;
    private ?string $dueDateText;
    private ?LocalDate $dueDate;
    private ?string $deliveryDateText;
    private ?LocalDate $deliveryDate;
    private ?string $taxPointDateText;
    private ?LocalDate $taxPointDate;

    public function __construct(
        ?string $issueDate,
        ?string $dueDate,
        ?string $deliveryDate,
        ?string $taxPointDate
    ) {
        $this->issueDateText    = $issueDate;
        $this->issueDate        = LocalDate::tryParse($this->issueDateText);
        $this->dueDateText      = $dueDate;
        $this->dueDate          = LocalDate::tryParse($this->dueDateText);
        $this->deliveryDateText = $deliveryDate;
        $this->deliveryDate     = LocalDate::tryParse($this->deliveryDateText);
        $this->taxPointDateText = $taxPointDate;
        $this->taxPointDate     = LocalDate::tryParse($this->taxPointDateText);
    }

    public function issueDate(): ?LocalDate
    {
        return $this->issueDate;
    }

    public function dueDate(): ?LocalDate
    {
        return $this->dueDate;
    }

    public function hasDueDate(): bool
    {
        return $this->dueDateText !== null;
    }

    public function deliveryDate(): ?LocalDate
    {
        return $this->deliveryDate;
    }

    public function taxPointDate(): ?LocalDate
    {
        return $this->taxPointDate;
    }

    public function hasTaxPointDate(): bool
    {
        return $this->taxPointDateText !== null;
    }

    /** @return array{issueDate: string|null, dueDate: string|null, deliveryDate: string|null, taxPointDate: string|null} */
    public function toArray(): array
    {
        return [
            'issueDate'    => $this->issueDateText,
            'dueDate'      => $this->dueDateText,
            'deliveryDate' => $this->deliveryDateText,
            'taxPointDate' => $this->taxPointDateText,
        ];
    }
}
