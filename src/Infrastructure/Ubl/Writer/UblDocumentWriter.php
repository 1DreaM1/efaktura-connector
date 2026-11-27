<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Writer;

use DOMDocument;
use DOMElement;
use DOMException;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblDocumentWriterInterface;
use DreaM1\EfakturaConnector\Core\Ubl\UblElementName;
use DreaM1\EfakturaConnector\Core\Ubl\UblNamespaces;
use DreaM1\EfakturaConnector\Core\Xml\XmlEncoding;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentDraft;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\UblDocumentType;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Exceptions\InvariantViolationException;

/** Orchestrates UBL aggregate writers in the document schema sequence. */
final class UblDocumentWriter implements UblDocumentWriterInterface
{
    /**
     * Serializes the draft in the UBL schema sequence. Validation remains a separate operation.
     *
     * @param  UblDocumentDraft      $draft Document values to serialize.
     * @return string                Serialized UBL XML document.
     * @throws DOMException          when the XML document cannot be created
     * @throws InvalidInputException when a credit note due date cannot be represented in payment means
     */
    public function write(UblDocumentDraft $draft): string
    {
        $dueDate = $draft->dates()->dueDate();

        // CreditNote has no root DueDate element, so its due date needs a PaymentMeans entry.
        if ($draft->isCreditNote() && $dueDate !== null && $draft->payment()->paymentMeansList() === []) {
            throw new InvalidInputException('A credit note due date is written in its payment means.');
        }

        $document = new DOMDocument('1.0', XmlEncoding::UTF_8);

        $document->formatOutput = true;

        $type = UblDocumentType::fromKind($draft->kind()->value());
        $root = self::createRoot($document, $type);

        // Append the root's header, accounting parties, optional payee/representative, and Delivery aggregates first.
        UblDocumentHeaderWriter::appendHeader($root, $draft, $type);
        self::appendParties($root, $draft);
        UblPartyWriter::appendDelivery($root, $draft);

        self::appendPaymentDetails($root, $draft, $type);

        // Totals and invoice/credit note line aggregates follow payment and allowance details in schema order.
        UblTotalsWriter::appendTotals($root, $draft);

        foreach ($draft->lines() as $line) {
            UblLineWriter::appendLine($root, $draft, $line, $type);
        }

        $xml = $document->saveXML();

        if ($xml === false) {
            throw new InvariantViolationException('The UBL document could not be serialized.');
        }

        return $xml;
    }

    /**
     * Creates an Invoice or CreditNote root in its UBL document namespace and declares the shared cac/cbc namespaces.
     *
     * @throws DOMException when the root element cannot be created
     */
    private static function createRoot(DOMDocument $document, UblDocumentType $type): DOMElement
    {
        $root = $document->createElementNS($type->documentNamespace(), $type->rootElement());

        $document->appendChild($root);

        $root->setAttributeNS(
            UblNamespaces::XMLNS_URI,
            UblNamespaces::XMLNS_PREFIX . ':' . UblNamespaces::CAC_PREFIX,
            UblNamespaces::COMMON_AGGREGATE_COMPONENTS
        );

        $root->setAttributeNS(
            UblNamespaces::XMLNS_URI,
            UblNamespaces::XMLNS_PREFIX . ':' . UblNamespaces::CBC_PREFIX,
            UblNamespaces::COMMON_BASIC_COMPONENTS
        );

        return $root;
    }

    /**
     * Appends the supplier and customer parties and the optional payee and tax representative.
     *
     * @throws DOMException when a UBL element cannot be created
     */
    private static function appendParties(DOMElement $root, UblDocumentDraft $draft): void
    {
        UblPartyWriter::appendParty($root, UblElementName::ACCOUNTING_SUPPLIER_PARTY, $draft->supplier());
        UblPartyWriter::appendParty($root, UblElementName::ACCOUNTING_CUSTOMER_PARTY, $draft->customer());

        if ($draft->payment()->payee() !== null) {
            UblPartyWriter::appendPayee($root, $draft->payment()->payee());
        }

        if ($draft->taxRepresentative() !== null) {
            UblPartyWriter::appendTaxRepresentative($root, $draft->taxRepresentative());
        }
    }

    /**
     * Appends payment means, payment terms and document-level allowances and charges.
     *
     * @throws DOMException when a UBL element cannot be created
     */
    private static function appendPaymentDetails(DOMElement $root, UblDocumentDraft $draft, UblDocumentType $type): void
    {
        $dueDate = $draft->dates()->dueDate();

        foreach ($draft->payment()->paymentMeansList() as $index => $means) {
            // UBL CreditNote carries its due date within PaymentMeans, so it belongs to the first entry.
            $creditNoteDueDate = $type->isCreditNote() && $index === 0 && $dueDate !== null
                ? $dueDate
                : null;
            UblPaymentMeansWriter::appendPaymentMeans($root, $means, $creditNoteDueDate);
        }

        if ($draft->payment()->termsNote() !== null) {
            // Payment terms are written as cac:PaymentTerms/cbc:Note.
            $terms = UblElementWriter::appendCac($root, UblElementName::PAYMENT_TERMS);
            UblElementWriter::appendCbc($terms, UblElementName::NOTE, $draft->payment()->termsNote());
        }

        foreach ($draft->allowanceCharges() as $allowanceCharge) {
            UblAllowanceChargeWriter::append(
                $root,
                $allowanceCharge,
                $draft->currency()->value(),
                $allowanceCharge->taxCategory()
            );
        }
    }
}
