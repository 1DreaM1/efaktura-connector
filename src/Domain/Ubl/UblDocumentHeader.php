<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl;

use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\CurrencyCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Internal\UblDocumentHeaderDates;
use DreaM1\EfakturaConnector\Domain\Ubl\Internal\UblDocumentHeaderIdentity;
use DreaM1\EfakturaConnector\Domain\Ubl\Internal\UblDocumentHeaderParties;
use DreaM1\EfakturaConnector\Domain\Ubl\Internal\UblDocumentHeaderPayment;
use DreaM1\EfakturaConnector\Domain\Ubl\Internal\UblDocumentHeaderReferences;
use DreaM1\EfakturaConnector\Domain\Ubl\Internal\UblDocumentHeaderTotals;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Header values of a UBL Invoice or CreditNote. Dates are exposed as LocalDate values; their original
 * text is retained privately so tolerant hydration and toArray() can preserve invalid document input.
 */
final class UblDocumentHeader
{
    // Keys of the serialized toArray() representation.
    private const FIELD_NUMBER                   = 'number';
    private const FIELD_CUSTOMIZATION_ID         = 'customizationId';
    private const FIELD_PROCESS_ID               = 'processId';
    private const FIELD_DOCUMENT_TYPE_CODE       = 'documentTypeCode';
    private const FIELD_DOCUMENT_ROOT            = 'documentRoot';
    private const FIELD_DOCUMENT_TYPE_ID         = 'documentTypeId';
    private const FIELD_SUPPLIER_ENDPOINT        = 'supplierEndpoint';
    private const FIELD_CUSTOMER_ENDPOINT        = 'customerEndpoint';
    private const FIELD_SUPPLIER_ENDPOINT_SCHEME = 'supplierEndpointScheme';
    private const FIELD_SUPPLIER_ENDPOINT_VALUE  = 'supplierEndpointValue';
    private const FIELD_CUSTOMER_ENDPOINT_SCHEME = 'customerEndpointScheme';
    private const FIELD_CUSTOMER_ENDPOINT_VALUE  = 'customerEndpointValue';
    private const FIELD_ISSUE_DATE               = 'issueDate';
    private const FIELD_DUE_DATE                 = 'dueDate';
    private const FIELD_DELIVERY_DATE            = 'deliveryDate';
    private const FIELD_TAX_POINT_DATE           = 'taxPointDate';
    private const FIELD_BUYER_REFERENCE          = 'buyerReference';
    private const FIELD_ORDER_REFERENCE          = 'orderReference';
    private const FIELD_NOTES                    = 'notes';
    private const FIELD_PAYMENT_MEANS_CODE       = 'paymentMeansCode';
    private const FIELD_PAYMENT_ID               = 'paymentId';
    private const FIELD_HAS_PAYMENT_TERMS        = 'hasPaymentTerms';
    private const FIELD_IBAN                     = 'iban';
    private const FIELD_PRECEDING_INVOICE_NUMBER = 'precedingInvoiceNumber';
    private const FIELD_CURRENCY                 = 'currency';
    private const FIELD_PAYABLE_AMOUNT           = 'payableAmount';
    private const FIELD_TAX_EXCLUSIVE_AMOUNT     = 'taxExclusiveAmount';
    private const FIELD_TAX_INCLUSIVE_AMOUNT     = 'taxInclusiveAmount';
    private const FIELD_LINE_EXTENSION_AMOUNT    = 'lineExtensionAmount';
    private const FIELD_TAX_AMOUNT               = 'taxAmount';
    private const FIELD_ROUNDING_AMOUNT          = 'roundingAmount';
    private const FIELD_SCHEME                   = 'scheme';
    private const FIELD_VALUE                    = 'value';

    private UblDocumentHeaderIdentity $identity;
    private UblDocumentHeaderParties $parties;
    private UblDocumentHeaderDates $dates;
    private UblDocumentHeaderReferences $references;
    private UblDocumentHeaderPayment $payment;
    private UblDocumentHeaderTotals $totals;

    /**
     * @param array{
     *     number: string|null,
     *     customizationId: string|null,
     *     processId: string|null,
     *     documentTypeCode: string|null,
     *     documentRoot: string|null,
     *     documentTypeId: string|null,
     *     supplierEndpoint: UblEndpointIdentifier|null,
     *     customerEndpoint: UblEndpointIdentifier|null,
     *     issueDate: string|null,
     *     dueDate: string|null,
     *     deliveryDate: string|null,
     *     taxPointDate: string|null,
     *     buyerReference: string|null,
     *     orderReference: string|null,
     *     notes: string[],
     *     paymentMeansCode: string|null,
     *     paymentId: string|null,
     *     hasPaymentTerms: bool,
     *     iban: string|null,
     *     precedingInvoiceNumber: string|null,
     *     currency: string|null,
     *     payableAmount: string|null,
     *     taxExclusiveAmount: string|null,
     *     taxInclusiveAmount: string|null,
     *     lineExtensionAmount: string|null,
     *     taxAmount: string|null,
     *     roundingAmount: string|null
     * } $values
     * @throws InvalidInputException when an identifier value is invalid
     */
    private function __construct(array $values, ?CurrencyCode $recognizedCurrencyCode = null)
    {
        $this->identity = new UblDocumentHeaderIdentity(
            $values[self::FIELD_NUMBER],
            $values[self::FIELD_CUSTOMIZATION_ID],
            $values[self::FIELD_PROCESS_ID],
            $values[self::FIELD_DOCUMENT_TYPE_CODE],
            $values[self::FIELD_DOCUMENT_ROOT],
            $values[self::FIELD_DOCUMENT_TYPE_ID]
        );
        $this->parties = new UblDocumentHeaderParties(
            $values[self::FIELD_SUPPLIER_ENDPOINT],
            $values[self::FIELD_CUSTOMER_ENDPOINT]
        );
        $this->dates = new UblDocumentHeaderDates(
            $values[self::FIELD_ISSUE_DATE],
            $values[self::FIELD_DUE_DATE],
            $values[self::FIELD_DELIVERY_DATE],
            $values[self::FIELD_TAX_POINT_DATE]
        );
        $this->references = new UblDocumentHeaderReferences(
            $values[self::FIELD_BUYER_REFERENCE],
            $values[self::FIELD_ORDER_REFERENCE],
            $values[self::FIELD_NOTES],
            $values[self::FIELD_PRECEDING_INVOICE_NUMBER]
        );
        $this->payment = new UblDocumentHeaderPayment(
            $values[self::FIELD_PAYMENT_MEANS_CODE],
            $values[self::FIELD_PAYMENT_ID],
            $values[self::FIELD_HAS_PAYMENT_TERMS],
            $values[self::FIELD_IBAN]
        );
        $this->totals = new UblDocumentHeaderTotals(
            $values[self::FIELD_CURRENCY],
            $recognizedCurrencyCode,
            $values[self::FIELD_PAYABLE_AMOUNT],
            $values[self::FIELD_TAX_EXCLUSIVE_AMOUNT],
            $values[self::FIELD_TAX_INCLUSIVE_AMOUNT],
            $values[self::FIELD_LINE_EXTENSION_AMOUNT],
            $values[self::FIELD_TAX_AMOUNT],
            $values[self::FIELD_ROUNDING_AMOUNT]
        );
    }

    /**
     * Builds a header from named values. The supplierEndpointScheme/supplierEndpointValue and
     * customerEndpointScheme/customerEndpointValue fields represent the UBL EndpointID values.
     * "notes" takes a list of strings.
     * Unknown keys are ignored, empty strings become null (empty notes are left out).
     *
     * Business distinctions include: "buyerReference" is the buyer's processing reference, while
     * "orderReference" identifies the related order; "dueDate" is the payment deadline,
     * "deliveryDate" is when goods or services were delivered, and "taxPointDate" is when tax
     * becomes applicable. "taxExclusiveAmount" excludes taxes, "taxInclusiveAmount" includes them,
     * "taxAmount" is the tax total, and "payableAmount" is the amount still due.
     *
     * @param  array<string, mixed>  $values                 Values to include or validate.
     * @param  CurrencyCode|null     $recognizedCurrencyCode Resolved supported document currency, when available.
     * @return self
     * @throws InvalidInputException when an identifier value is invalid
     */
    public static function fromArray(array $values, ?CurrencyCode $recognizedCurrencyCode = null): self
    {
        $normalizedValues = [
            self::FIELD_NUMBER => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_NUMBER
            ),
            self::FIELD_CUSTOMIZATION_ID => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_CUSTOMIZATION_ID
            ),
            self::FIELD_PROCESS_ID => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_PROCESS_ID
            ),
            self::FIELD_DOCUMENT_TYPE_CODE => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_DOCUMENT_TYPE_CODE
            ),
            self::FIELD_DOCUMENT_ROOT    => self::normalizedDocumentRoot($values),
            self::FIELD_DOCUMENT_TYPE_ID => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_DOCUMENT_TYPE_ID
            ),
            self::FIELD_SUPPLIER_ENDPOINT => UblArrayValue::endpointIdentifier(
                $values,
                self::FIELD_SUPPLIER_ENDPOINT_SCHEME,
                self::FIELD_SUPPLIER_ENDPOINT_VALUE
            ),
            self::FIELD_CUSTOMER_ENDPOINT => UblArrayValue::endpointIdentifier(
                $values,
                self::FIELD_CUSTOMER_ENDPOINT_SCHEME,
                self::FIELD_CUSTOMER_ENDPOINT_VALUE
            ),
            self::FIELD_ISSUE_DATE => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_ISSUE_DATE
            ),
            self::FIELD_DUE_DATE => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_DUE_DATE
            ),
            self::FIELD_DELIVERY_DATE => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_DELIVERY_DATE
            ),
            self::FIELD_TAX_POINT_DATE => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_TAX_POINT_DATE
            ),
            self::FIELD_BUYER_REFERENCE => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_BUYER_REFERENCE
            ),
            self::FIELD_ORDER_REFERENCE => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_ORDER_REFERENCE
            ),
            self::FIELD_NOTES => self::normalizeNotes(
                $values[self::FIELD_NOTES] ?? []
            ),
            self::FIELD_PAYMENT_MEANS_CODE => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_PAYMENT_MEANS_CODE
            ),
            self::FIELD_PAYMENT_ID => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_PAYMENT_ID
            ),
            self::FIELD_HAS_PAYMENT_TERMS => UblArrayValue::boolean($values, self::FIELD_HAS_PAYMENT_TERMS),
            self::FIELD_IBAN              => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_IBAN
            ),
            self::FIELD_PRECEDING_INVOICE_NUMBER => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_PRECEDING_INVOICE_NUMBER
            ),
            self::FIELD_CURRENCY => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_CURRENCY
            ),
            self::FIELD_PAYABLE_AMOUNT => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_PAYABLE_AMOUNT
            ),
            self::FIELD_TAX_EXCLUSIVE_AMOUNT => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_TAX_EXCLUSIVE_AMOUNT
            ),
            self::FIELD_TAX_INCLUSIVE_AMOUNT => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_TAX_INCLUSIVE_AMOUNT
            ),
            self::FIELD_LINE_EXTENSION_AMOUNT => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_LINE_EXTENSION_AMOUNT
            ),
            self::FIELD_TAX_AMOUNT => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_TAX_AMOUNT
            ),
            self::FIELD_ROUNDING_AMOUNT => UblArrayValue::nonEmptyString(
                $values,
                self::FIELD_ROUNDING_AMOUNT
            )
        ];

        return new self($normalizedValues, $recognizedCurrencyCode);
    }

    /**
     * Returns the invoice or credit note number from cbc:ID.
     *
     * @return string|null
     */
    public function number(): ?string
    {
        return $this->identity->number();
    }

    /**
     * cbc:CustomizationID (specification identifier)
     *
     * @return string|null
     */
    public function customizationId(): ?string
    {
        return $this->identity->customizationId();
    }

    /**
     * cbc:ProfileID (Peppol business process)
     *
     * @return PeppolProcessIdentifier|null
     */
    public function processId(): ?PeppolProcessIdentifier
    {
        return $this->identity->processId();
    }

    /**
     * cbc:InvoiceTypeCode or cbc:CreditNoteTypeCode, see CodeList\DocumentTypeCode.
     *
     * @return string|null
     */
    public function documentTypeCode(): ?string
    {
        return $this->identity->documentTypeCode();
    }

    /** Returns the UBL root element that identifies the document syntax. */
    public function documentRoot(): ?UblDocumentRoot
    {
        return $this->identity->documentRoot();
    }

    /**
     * Peppol document type identifier derived from the root element and CustomizationID;
     * null without CustomizationID.
     *
     * @return PeppolDocumentTypeIdentifier|null
     */
    public function documentTypeId(): ?PeppolDocumentTypeIdentifier
    {
        return $this->identity->documentTypeId();
    }

    /** Returns the UBL supplier party's cbc:EndpointID, not the transport sender Participant. */
    public function supplierEndpoint(): ?UblEndpointIdentifier
    {
        return $this->parties->supplierEndpoint();
    }

    /** Returns the UBL customer party's cbc:EndpointID, not the transport receiver Participant. */
    public function customerEndpoint(): ?UblEndpointIdentifier
    {
        return $this->parties->customerEndpoint();
    }

    /**
     * cbc:IssueDate
     *
     * @return LocalDate|null
     */
    public function issueDate(): ?LocalDate
    {
        return $this->dates->issueDate();
    }

    /**
     * cbc:DueDate (Invoice), else cac:PaymentMeans/cbc:PaymentDueDate (CreditNote)
     *
     * @return LocalDate|null
     */
    public function dueDate(): ?LocalDate
    {
        return $this->dates->dueDate();
    }

    /** Whether the source document supplied a due date, even if its text was invalid. */
    public function hasDueDate(): bool
    {
        return $this->dates->hasDueDate();
    }

    /**
     * cac:Delivery/cbc:ActualDeliveryDate
     *
     * @return LocalDate|null
     */
    public function deliveryDate(): ?LocalDate
    {
        return $this->dates->deliveryDate();
    }

    /**
     * cbc:TaxPointDate
     *
     * @return LocalDate|null
     */
    public function taxPointDate(): ?LocalDate
    {
        return $this->dates->taxPointDate();
    }

    /** Whether the source document supplied a tax-point date, even if its text was invalid. */
    public function hasTaxPointDate(): bool
    {
        return $this->dates->hasTaxPointDate();
    }

    /**
     * cbc:BuyerReference
     *
     * @return string|null
     */
    public function buyerReference(): ?string
    {
        return $this->references->buyerReference();
    }

    /**
     * cac:OrderReference/cbc:ID
     *
     * @return string|null
     */
    public function orderReference(): ?string
    {
        return $this->references->orderReference();
    }

    /**
     * First cbc:Note
     *
     * @return string|null
     */
    public function note(): ?string
    {
        return $this->references->note();
    }

    /**
     * Returns every non-empty note in document order.
     * @return string[] every non-empty cbc:Note, in document order
     */
    public function notes(): array
    {
        return $this->references->notes();
    }

    /**
     * First cac:PaymentMeans/cbc:PaymentMeansCode, see CodeList\PaymentMeansCode.
     *
     * @return string|null
     */
    public function paymentMeansCode(): ?string
    {
        return $this->payment->paymentMeansCode();
    }

    /**
     * First cac:PaymentMeans/cbc:PaymentID
     *
     * @return string|null
     */
    public function paymentId(): ?string
    {
        return $this->payment->paymentId();
    }

    /**
     * Whether the document has cac:PaymentTerms.
     *
     * @return bool
     */
    public function hasPaymentTerms(): bool
    {
        return $this->payment->hasPaymentTerms();
    }

    /**
     * First cac:PaymentMeans/cac:PayeeFinancialAccount/cbc:ID
     *
     * @return string|null
     */
    public function iban(): ?string
    {
        return $this->payment->iban();
    }

    /**
     * cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID
     *
     * @return string|null
     */
    public function precedingInvoiceNumber(): ?string
    {
        return $this->references->precedingInvoiceNumber();
    }

    /**
     * cbc:DocumentCurrencyCode
     *
     * @return string|null
     */
    public function currency(): ?string
    {
        return $this->totals->currency();
    }

    /** Returns the recognized canonical currency code while currency() preserves the original document text. */
    public function recognizedCurrencyCode(): ?CurrencyCode
    {
        return $this->totals->recognizedCurrencyCode();
    }

    /**
     * cac:LegalMonetaryTotal/cbc:PayableAmount
     *
     * @return string|null
     */
    public function payableAmount(): ?string
    {
        return $this->totals->payableAmount();
    }

    /**
     * cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount
     *
     * @return string|null
     */
    public function taxExclusiveAmount(): ?string
    {
        return $this->totals->taxExclusiveAmount();
    }

    /**
     * cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount
     *
     * @return string|null
     */
    public function taxInclusiveAmount(): ?string
    {
        return $this->totals->taxInclusiveAmount();
    }

    /**
     * cac:LegalMonetaryTotal/cbc:LineExtensionAmount
     *
     * @return string|null
     */
    public function lineExtensionAmount(): ?string
    {
        return $this->totals->lineExtensionAmount();
    }

    /**
     * First cac:TaxTotal/cbc:TaxAmount
     *
     * @return string|null
     */
    public function taxAmount(): ?string
    {
        return $this->totals->taxAmount();
    }

    /**
     * cac:LegalMonetaryTotal/cbc:PayableRoundingAmount
     *
     * @return string|null
     */
    public function roundingAmount(): ?string
    {
        return $this->totals->roundingAmount();
    }

    /**
     * The values accepted by fromArray(). Separate endpoint scheme/value fields preserve unqualified IDs
     * containing a colon.
     *
     * @return array{
     *     number: string|null,
     *     customizationId: string|null,
     *     processId: string|null,
     *     documentTypeCode: string|null,
     *     documentRoot: string|null,
     *     documentTypeId: string|null,
     *     supplierEndpointScheme: string|null,
     *     supplierEndpointValue: string|null,
     *     customerEndpointScheme: string|null,
     *     customerEndpointValue: string|null,
     *     issueDate: string|null,
     *     dueDate: string|null,
     *     deliveryDate: string|null,
     *     taxPointDate: string|null,
     *     buyerReference: string|null,
     *     orderReference: string|null,
     *     notes: string[],
     *     paymentMeansCode: string|null,
     *     paymentId: string|null,
     *     hasPaymentTerms: bool,
     *     iban: string|null,
     *     precedingInvoiceNumber: string|null,
     *     currency: string|null,
     *     payableAmount: string|null,
     *     taxExclusiveAmount: string|null,
     *     taxInclusiveAmount: string|null,
     *     lineExtensionAmount: string|null,
     *     taxAmount: string|null,
     *     roundingAmount: string|null
     * }
     */
    public function toArray(): array
    {
        $supplierEndpoint = self::endpointArray($this->parties->supplierEndpoint());
        $customerEndpoint = self::endpointArray($this->parties->customerEndpoint());
        $dates            = $this->dates->toArray();

        return [
            self::FIELD_NUMBER                   => $this->identity->number(),
            self::FIELD_CUSTOMIZATION_ID         => $this->identity->customizationId(),
            self::FIELD_PROCESS_ID               => $this->identity->processId() !== null ? $this->identity->processId()->value() : null,
            self::FIELD_DOCUMENT_TYPE_CODE       => $this->identity->documentTypeCode(),
            self::FIELD_DOCUMENT_ROOT            => $this->identity->documentRoot() !== null ? $this->identity->documentRoot()->value() : null,
            self::FIELD_DOCUMENT_TYPE_ID         => $this->identity->documentTypeId() !== null ? $this->identity->documentTypeId()->value() : null,
            self::FIELD_SUPPLIER_ENDPOINT_SCHEME => $supplierEndpoint[self::FIELD_SCHEME],
            self::FIELD_SUPPLIER_ENDPOINT_VALUE  => $supplierEndpoint[self::FIELD_VALUE],
            self::FIELD_CUSTOMER_ENDPOINT_SCHEME => $customerEndpoint[self::FIELD_SCHEME],
            self::FIELD_CUSTOMER_ENDPOINT_VALUE  => $customerEndpoint[self::FIELD_VALUE],
            self::FIELD_ISSUE_DATE               => $dates[self::FIELD_ISSUE_DATE],
            self::FIELD_DUE_DATE                 => $dates[self::FIELD_DUE_DATE],
            self::FIELD_DELIVERY_DATE            => $dates[self::FIELD_DELIVERY_DATE],
            self::FIELD_TAX_POINT_DATE           => $dates[self::FIELD_TAX_POINT_DATE],
            self::FIELD_BUYER_REFERENCE          => $this->references->buyerReference(),
            self::FIELD_ORDER_REFERENCE          => $this->references->orderReference(),
            self::FIELD_NOTES                    => $this->references->notes(),
            self::FIELD_PAYMENT_MEANS_CODE       => $this->payment->paymentMeansCode(),
            self::FIELD_PAYMENT_ID               => $this->payment->paymentId(),
            self::FIELD_HAS_PAYMENT_TERMS        => $this->payment->hasPaymentTerms(),
            self::FIELD_IBAN                     => $this->payment->iban(),
            self::FIELD_PRECEDING_INVOICE_NUMBER => $this->references->precedingInvoiceNumber(),
            self::FIELD_CURRENCY                 => $this->totals->currency(),
            self::FIELD_PAYABLE_AMOUNT           => $this->totals->payableAmount(),
            self::FIELD_TAX_EXCLUSIVE_AMOUNT     => $this->totals->taxExclusiveAmount(),
            self::FIELD_TAX_INCLUSIVE_AMOUNT     => $this->totals->taxInclusiveAmount(),
            self::FIELD_LINE_EXTENSION_AMOUNT    => $this->totals->lineExtensionAmount(),
            self::FIELD_TAX_AMOUNT               => $this->totals->taxAmount(),
            self::FIELD_ROUNDING_AMOUNT          => $this->totals->roundingAmount(),
        ];
    }

    /**
     * @param  mixed    $notes Invoice notes to include in the document.
     * @return string[]
     */
    private static function normalizeNotes($notes): array
    {
        if (! is_array($notes)) {
            return [];
        }

        $result = [];

        foreach ($notes as $note) {
            $note = is_scalar($note) ? trim((string) $note) : '';

            if ($note !== '') {
                $result[] = $note;
            }
        }

        return $result;
    }

    /**
     * Returns a supported UBL document root from an array representation.
     *
     * @param  array<string, mixed> $values Values accepted by fromArray().
     * @return string|null
     */
    private static function normalizedDocumentRoot(array $values): ?string
    {
        $documentRoot = UblArrayValue::nonEmptyString($values, self::FIELD_DOCUMENT_ROOT);

        return $documentRoot !== null && UblDocumentRoot::isUblDocument($documentRoot)
            ? $documentRoot
            : null;
    }

    /**
     * @param  UblEndpointIdentifier|null                     $endpoint UBL endpoint identity from the source document.
     * @return array{scheme: string|null, value: string|null}
     */
    private static function endpointArray(?UblEndpointIdentifier $endpoint): array
    {
        return $endpoint !== null
            ? $endpoint->toArray()
            : [self::FIELD_SCHEME => null, self::FIELD_VALUE => null];
    }
}
