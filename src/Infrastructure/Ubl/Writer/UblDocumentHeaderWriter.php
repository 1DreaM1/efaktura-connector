<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Writer;

use DOMElement;
use DOMException;
use DreaM1\EfakturaConnector\Core\Ubl\UblAttributeName;
use DreaM1\EfakturaConnector\Core\Ubl\UblElementName;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\AdditionalDocumentTypeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentDraft;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentReferenceType;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftDocumentReference;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\UblDocumentType;
use DreaM1\EfakturaConnector\Support\Exceptions\InvariantViolationException;

/** Writes the document header, notes, and document references. */
final class UblDocumentHeaderWriter
{
    /** Fallback identifier serialized for an empty order reference. */
    private const EMPTY_ORDER_REFERENCE_ID = 'NA';

    /** @var string[] UBL document-reference elements in the required Invoice order. */
    private const INVOICE_DOCUMENT_REFERENCE_ORDER = [
        UblDocumentReferenceType::BILLING,
        UblDocumentReferenceType::DESPATCH,
        UblDocumentReferenceType::RECEIPT,
        UblDocumentReferenceType::ORIGINATOR,
        UblDocumentReferenceType::CONTRACT,
        UblDocumentReferenceType::ADDITIONAL,
        UblDocumentReferenceType::PROJECT,
    ];

    /** @var string[] UBL document-reference elements in the required CreditNote order. */
    private const CREDIT_NOTE_DOCUMENT_REFERENCE_ORDER = [
        UblDocumentReferenceType::BILLING,
        UblDocumentReferenceType::DESPATCH,
        UblDocumentReferenceType::RECEIPT,
        UblDocumentReferenceType::CONTRACT,
        UblDocumentReferenceType::ADDITIONAL,
        UblDocumentReferenceType::PROJECT,
        UblDocumentReferenceType::ORIGINATOR,
    ];

    /**
     * Appends the ordered UBL header fields and references to an Invoice or CreditNote root.
     *
     * @param  DOMElement       $root  UBL Invoice or CreditNote root element.
     * @param  UblDocumentDraft $draft Values to serialize into the document header.
     * @param  UblDocumentType  $type  Document kind and element names for the root type.
     * @throws DOMException     when a UBL element cannot be created
     */
    public static function appendHeader(DOMElement $root, UblDocumentDraft $draft, UblDocumentType $type): void
    {
        // Write the root identifiers and dates: CustomizationID, ProfileID, ID, and IssueDate.
        UblElementWriter::appendCbc($root, UblElementName::CUSTOMIZATION_ID, $draft->customizationId()->value());
        UblElementWriter::appendCbc($root, UblElementName::PROFILE_ID, $draft->profileId()->value());
        UblElementWriter::appendCbc($root, UblElementName::ID, $draft->number()->value());
        UblElementWriter::appendCbc($root, UblElementName::ISSUE_DATE, $draft->issueDate()->value());

        self::appendDatesTypeCodeAndNotes($root, $draft, $type);
        self::appendCurrenciesAndBuyerReferences($root, $draft);

        if ($draft->dates()->invoicePeriod() !== null) {
            // InvoicePeriod contains StartDate, EndDate, and an optional DescriptionCode.
            UblPeriodWriter::append($root, UblElementName::INVOICE_PERIOD, $draft->dates()->invoicePeriod());
        }

        self::appendOrderReference($root, $draft);
        self::appendDocumentReferences($root, $draft, $type);
    }
    /**
     * Appends each document note as a cbc:Note element in supplied order.
     *
     * @param  DOMElement       $root  UBL Invoice or CreditNote root element.
     * @param  UblDocumentDraft $draft Draft containing the notes.
     * @throws DOMException     when a UBL element cannot be created
     */
    private static function appendNotes(DOMElement $root, UblDocumentDraft $draft): void
    {
        foreach ($draft->notes() as $note) {
            UblElementWriter::appendCbc($root, UblElementName::NOTE, $note);
        }
    }

    /**
     * Appends references in the sequence required by the Invoice or CreditNote UBL schema.
     * A CreditNote project reference is represented as an AdditionalDocumentReference with code 50.
     *
     * @param  DOMElement       $root  UBL Invoice or CreditNote root element.
     * @param  UblDocumentDraft $draft Draft containing the document references.
     * @param  UblDocumentType  $type  Document kind determining the reference sequence.
     * @throws DOMException     when a UBL element cannot be created
     */
    private static function appendDocumentReferences(
        DOMElement $root,
        UblDocumentDraft $draft,
        UblDocumentType $type
    ): void {
        $isCreditNote   = $type->isCreditNote();
        $referenceOrder = $isCreditNote
            ? self::CREDIT_NOTE_DOCUMENT_REFERENCE_ORDER
            : self::INVOICE_DOCUMENT_REFERENCE_ORDER;

        /** @var array<string, UblDraftDocumentReference[]> $referencesByType */
        $referencesByType = [];

        foreach ($draft->references()->documentReferences() as $reference) {
            $referencesByType[$reference->type()->value()][] = $reference;
        }

        foreach ($referenceOrder as $referenceType) {
            // The indexed groups retain the caller's order within each schema reference type.
            foreach ($referencesByType[$referenceType] ?? [] as $reference) {
                if ($isCreditNote && $referenceType === UblDocumentReferenceType::PROJECT) {
                    self::appendCreditNoteProjectReference($root, $reference);
                } else {
                    self::appendDocumentReference($root, $reference);
                }
            }
        }
    }

    /**
     * Writes the due or tax point date, the kind-specific type code and the notes.
     *
     * Invoice writes DueDate before InvoiceTypeCode and Note; CreditNote writes TaxPointDate, CreditNoteTypeCode,
     * then Note.
     *
     * @throws DOMException when a UBL element cannot be created
     */
    private static function appendDatesTypeCodeAndNotes(
        DOMElement $root,
        UblDocumentDraft $draft,
        UblDocumentType $type
    ): void {
        if ($type->isCreditNote()) {
            UblElementWriter::appendOptionalDate($root, UblElementName::TAX_POINT_DATE, $draft->dates()->taxPointDate());
            UblElementWriter::appendCbc($root, $type->typeCodeElement(), $draft->typeCode()->value());
            self::appendNotes($root, $draft);

            return;
        }

        UblElementWriter::appendOptionalDate($root, UblElementName::DUE_DATE, $draft->dates()->dueDate());
        UblElementWriter::appendCbc($root, $type->typeCodeElement(), $draft->typeCode()->value());
        self::appendNotes($root, $draft);
        UblElementWriter::appendOptionalDate($root, UblElementName::TAX_POINT_DATE, $draft->dates()->taxPointDate());
    }

    /**
     * Writes DocumentCurrencyCode followed by the optional TaxCurrencyCode, AccountingCost and BuyerReference.
     *
     * @throws DOMException when a UBL element cannot be created
     */
    private static function appendCurrenciesAndBuyerReferences(DOMElement $root, UblDocumentDraft $draft): void
    {
        UblElementWriter::appendCbc($root, UblElementName::DOCUMENT_CURRENCY_CODE, $draft->currency()->value());
        UblElementWriter::appendOptionalCbc(
            $root,
            UblElementName::TAX_CURRENCY_CODE,
            $draft->taxCurrencyCode() !== null ? $draft->taxCurrencyCode()->value() : null
        );
        UblElementWriter::appendOptionalCbc($root, UblElementName::ACCOUNTING_COST, $draft->references()->accountingCost());
        UblElementWriter::appendOptionalCbc($root, UblElementName::BUYER_REFERENCE, $draft->references()->buyerReference());
    }

    /**
     * Writes OrderReference with its order ID and, when supplied, SalesOrderID; nothing when neither is set.
     *
     * An absent order ID is written as the fallback identifier because the schema requires the element.
     *
     * @throws DOMException when a UBL element cannot be created
     */
    private static function appendOrderReference(DOMElement $root, UblDocumentDraft $draft): void
    {
        $orderReferenceValue = $draft->references()->orderReference();
        $salesOrderReference = $draft->references()->salesOrderReference();

        if ($orderReferenceValue === null && $salesOrderReference === null) {
            return;
        }

        $orderReference = UblElementWriter::appendCac($root, UblElementName::ORDER_REFERENCE);

        if ($orderReferenceValue !== null) {
            UblElementWriter::appendDraftIdentifier($orderReference, UblElementName::ID, $orderReferenceValue);
        } else {
            UblElementWriter::appendCbc($orderReference, UblElementName::ID, self::EMPTY_ORDER_REFERENCE_ID);
        }

        if ($salesOrderReference !== null) {
            UblElementWriter::appendDraftIdentifier(
                $orderReference,
                UblElementName::SALES_ORDER_ID,
                $salesOrderReference
            );
        }
    }

    /** @throws DOMException */
    private static function appendDocumentReference(DOMElement $root, UblDraftDocumentReference $draft): void
    {
        $referenceType = $draft->type()->value();

        if ($referenceType === UblDocumentReferenceType::BILLING) {
            self::appendBillingReference($root, $draft);
            return;
        }

        if ($referenceType === UblDocumentReferenceType::PROJECT) {
            $reference = UblElementWriter::appendCac($root, UblDocumentReferenceType::PROJECT);
            self::appendReferenceIdentifier($reference, $draft);
            return;
        }

        if ($referenceType === UblDocumentReferenceType::ADDITIONAL) {
            self::appendAdditionalDocumentReference($root, $draft);
            return;
        }

        $reference = UblElementWriter::appendCac($root, $referenceType);
        self::appendReferenceIdentifier($reference, $draft);
    }

    /** @throws DOMException */
    private static function appendBillingReference(DOMElement $root, UblDraftDocumentReference $draft): void
    {
        // BillingReference contains InvoiceDocumentReference/ID and its optional IssueDate.
        $billing = UblElementWriter::appendCac($root, UblDocumentReferenceType::BILLING);
        $invoice = UblElementWriter::appendCac($billing, UblElementName::INVOICE_DOCUMENT_REFERENCE);
        self::appendReferenceIdentifier($invoice, $draft);
        UblElementWriter::appendOptionalDate($invoice, UblElementName::ISSUE_DATE, $draft->issueDate());
    }

    /** @throws DOMException */
    private static function appendCreditNoteProjectReference(DOMElement $root, UblDraftDocumentReference $draft): void
    {
        // UBL encodes a CreditNote project reference as AdditionalDocumentReference with project type code 50.
        $reference = UblElementWriter::appendCac($root, UblDocumentReferenceType::ADDITIONAL);
        self::appendReferenceIdentifier($reference, $draft);
        UblElementWriter::appendCbc(
            $reference,
            UblElementName::DOCUMENT_TYPE_CODE,
            AdditionalDocumentTypeCode::PROJECT_REFERENCE
        );
    }

    /** @throws DOMException */
    private static function appendAdditionalDocumentReference(DOMElement $root, UblDraftDocumentReference $draft): void
    {
        // AdditionalDocumentReference contains ID, optional type code/description, and an optional Attachment.
        $reference = UblElementWriter::appendCac($root, UblDocumentReferenceType::ADDITIONAL);
        self::appendReferenceIdentifier($reference, $draft);
        UblElementWriter::appendOptionalCbc(
            $reference,
            UblElementName::DOCUMENT_TYPE_CODE,
            $draft->documentTypeCode() !== null ? $draft->documentTypeCode()->value() : null
        );
        UblElementWriter::appendOptionalCbc($reference, UblElementName::DOCUMENT_DESCRIPTION, $draft->description());
        self::appendAttachment($reference, $draft);
    }

    /** @throws DOMException */
    private static function appendReferenceIdentifier(DOMElement $parent, UblDraftDocumentReference $draft): void
    {
        UblElementWriter::appendIdentifier(
            $parent,
            UblElementName::ID,
            $draft->id()->value(),
            $draft->id()->scheme()
        );
    }

    /** @throws DOMException */
    private static function appendAttachment(DOMElement $reference, UblDraftDocumentReference $draft): void
    {
        $embeddedDocument = $draft->embeddedDocument();

        // An attachment is omitted unless at least one representation was supplied.
        if ($embeddedDocument === null && $draft->externalUri() === null) {
            return;
        }

        $attachment = UblElementWriter::appendCac($reference, UblElementName::ATTACHMENT);

        if ($embeddedDocument !== null) {
            $mimeCode = $draft->mimeCode();

            if ($mimeCode === null) {
                throw new InvariantViolationException('An embedded document always carries a MIME code.');
            }

            // EmbeddedDocumentBinaryObject carries the MIME code and optional filename as attributes.
            $embedded = UblElementWriter::appendCbc(
                $attachment,
                UblElementName::EMBEDDED_DOCUMENT_BINARY_OBJECT,
                $embeddedDocument->value()
            );
            $embedded->setAttribute(UblAttributeName::MIME_CODE, $mimeCode->value());
            UblElementWriter::setAttributeIfPresent($embedded, UblAttributeName::FILE_NAME, $draft->fileName());
        }

        if ($draft->externalUri() !== null) {
            // ExternalReference/URI points to an externally hosted attachment.
            $external = UblElementWriter::appendCac($attachment, UblElementName::EXTERNAL_REFERENCE);
            UblElementWriter::appendCbc($external, UblElementName::URI, $draft->externalUri()->value());
        }
    }
}
