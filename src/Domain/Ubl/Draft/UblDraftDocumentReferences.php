<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Domain\Ubl\LocalDate;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** @internal Immutable document-reference collection with billing-reference replacement semantics. */
final class UblDraftDocumentReferences
{
    /** @var UblDraftDocumentReference[] References kept in insertion order; billing reference can be replaced. */
    private array $items;

    /** @param UblDraftDocumentReference[] $items */
    private function __construct(array $items)
    {
        $this->items = TypedArray::valuesOfType(
            $items,
            UblDraftDocumentReference::class,
            'Every document reference must be a UblDraftDocumentReference.'
        );
    }

    /** Returns an empty reference collection. */
    public static function none(): self
    {
        return new self([]);
    }

    /** Returns a copy with one additional reference. */
    public function withReference(UblDraftDocumentReference $reference): self
    {
        $items   = $this->items;
        $items[] = $reference;

        return new self($items);
    }

    /** Replaces the billing reference and leaves all other references in their original order. */
    public function withBillingReference(UblDocumentNumber $invoiceNumber, ?LocalDate $issueDate): self
    {
        $reference = (new UblDraftDocumentReference(
            UblDocumentReferenceType::fromString(UblDocumentReferenceType::BILLING),
            new UblDraftIdentifier($invoiceNumber->value())
        ))->withIssueDate($issueDate);

        $items = array_values(array_filter(
            $this->items,
            static function (UblDraftDocumentReference $existing): bool {
                return $existing->type()->value() !== UblDocumentReferenceType::BILLING;
            }
        ));

        $items[] = $reference;

        return new self($items);
    }

    /**
     * Returns references in insertion order.
     *
     * @return UblDraftDocumentReference[]
     */
    public function items(): array
    {
        return $this->items;
    }

    /** Returns the first billing reference, or null when none is set. */
    public function firstBillingReference(): ?UblDraftDocumentReference
    {
        foreach ($this->items as $reference) {
            if ($reference->type()->value() === UblDocumentReferenceType::BILLING) {
                return $reference;
            }
        }

        return null;
    }
}
