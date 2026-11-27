<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl;

use DOMElement;
use DreaM1\EfakturaConnector\Core\Ubl\UblElementName;
use DreaM1\EfakturaConnector\Core\Ubl\UblNamespaces;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentRoot;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Shared structural definition of the UBL Invoice and CreditNote document types. */
final class UblDocumentType
{
    private string $kind;
    private string $typeCodeElement;
    private string $lineElement;
    private string $quantityElement;

    /** Creates a descriptor from the supported UBL document kind. */
    private function __construct(string $kind, string $typeCodeElement, string $lineElement, string $quantityElement)
    {
        $this->kind            = $kind;
        $this->typeCodeElement = $typeCodeElement;
        $this->lineElement     = $lineElement;
        $this->quantityElement = $quantityElement;
    }

    /**
     * Returns the structural definition for a supported document kind.
     *
     * @param  string                $kind UBL root local name, either Invoice or CreditNote.
     * @return self
     * @throws InvalidInputException when the kind is not Invoice or CreditNote.
     */
    public static function fromKind(string $kind): self
    {
        if ($kind === UblDocumentRoot::INVOICE) {
            return new self(
                UblDocumentRoot::INVOICE,
                UblElementName::INVOICE_TYPE_CODE,
                UblElementName::INVOICE_LINE,
                UblElementName::INVOICED_QUANTITY
            );
        }

        if ($kind === UblDocumentRoot::CREDIT_NOTE) {
            return new self(
                UblDocumentRoot::CREDIT_NOTE,
                UblElementName::CREDIT_NOTE_TYPE_CODE,
                UblElementName::CREDIT_NOTE_LINE,
                UblElementName::CREDITED_QUANTITY
            );
        }

        throw new InvalidInputException('A UBL Invoice or CreditNote variant is required.');
    }

    /**
     * Returns the supported type matching a root element and its UBL namespace, or null.
     *
     * @param  DOMElement|null $root UBL Invoice or CreditNote root element to classify.
     * @return self|null
     */
    public static function fromRoot(?DOMElement $root): ?self
    {
        if ($root === null || $root->localName === null || ! UblNamespaces::isSupportedRoot($root)) {
            return null;
        }

        return self::fromKind($root->localName);
    }

    /** Returns the root element name. */
    public function rootElement(): string
    {
        return $this->kind;
    }

    /** Returns the UBL document namespace for this type. */
    public function documentNamespace(): string
    {
        return UblNamespaces::documentNamespaceFor($this->kind);
    }

    /** Returns the type-code element name. */
    public function typeCodeElement(): string
    {
        return $this->typeCodeElement;
    }

    /** Returns the line element name. */
    public function lineElement(): string
    {
        return $this->lineElement;
    }

    /** Returns the line quantity element name. */
    public function quantityElement(): string
    {
        return $this->quantityElement;
    }

    /** Whether this type is a CreditNote. */
    public function isCreditNote(): bool
    {
        return $this->kind === UblDocumentRoot::CREDIT_NOTE;
    }
}
