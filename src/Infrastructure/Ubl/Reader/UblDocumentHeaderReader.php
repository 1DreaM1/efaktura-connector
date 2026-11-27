<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader;

use DreaM1\EfakturaConnector\Core\Ubl\PeppolDocumentTypeIdentifierBuilder;
use DreaM1\EfakturaConnector\Core\Ubl\UblElementName;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentHeader;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CurrencyCodeList;

/**
 * Reads document identifiers, parties, dates and payment details into a UBL header value.
 *
 * @internal used by UblDocumentReader
 */
final class UblDocumentHeaderReader
{
    use BuildsUblXPaths;

    /**
     * Reads UBL XML into a document value.
     *
     * @param  UblXPath          $xpath XPath helper or expression used to query the XML document.
     * @param  string            $kind  UBL root local name: Invoice or CreditNote.
     * @return UblDocumentHeader
     */
    public function read(UblXPath $xpath, string $kind): UblDocumentHeader
    {
        $currency = $xpath->value(self::cbc(UblElementName::DOCUMENT_CURRENCY_CODE));

        return UblDocumentHeader::fromArray(
            array_merge(
                $this->readIdentity($xpath, $kind),
                $this->readParties($xpath),
                $this->readDates($xpath),
                $this->readReferences($xpath),
                $this->readPayment($xpath),
                $this->readTotals($xpath, $currency)
            ),
            CurrencyCodeList::resolve($currency)
        );
    }

    /**
     * Reads the root-level identifiers: ID, CustomizationID, ProfileID and the type code.
     *
     * @return array<string, mixed>
     */
    private function readIdentity(UblXPath $xpath, string $kind): array
    {
        $customizationId = $xpath->value(self::cbc(UblElementName::CUSTOMIZATION_ID));

        return [
            // Root cbc:ID, cbc:CustomizationID, cbc:ProfileID, and cbc:InvoiceTypeCode or cbc:CreditNoteTypeCode.
            'number'           => $xpath->value(self::cbc(UblElementName::ID)),
            'customizationId'  => $customizationId,
            'processId'        => $xpath->value(self::cbc(UblElementName::PROFILE_ID)),
            'documentTypeCode' => $xpath->firstValue(
                self::cbc(UblElementName::INVOICE_TYPE_CODE),
                self::cbc(UblElementName::CREDIT_NOTE_TYPE_CODE)
            ),
            'documentRoot' => $kind,
            // A Peppol document type identifier depends on both the root kind and CustomizationID.
            'documentTypeId' => $customizationId === null
                ? null
                : PeppolDocumentTypeIdentifierBuilder::forDocument($kind, $customizationId)->value(),
        ];
    }

    /**
     * Reads Party/EndpointID for each accounting party; EndpointID's schemeID is kept with its value.
     *
     * @return array<string, mixed>
     */
    private function readParties(UblXPath $xpath): array
    {
        $supplier = $xpath->endpoint($this->partyEndpointPath(UblElementName::ACCOUNTING_SUPPLIER_PARTY));
        $customer = $xpath->endpoint($this->partyEndpointPath(UblElementName::ACCOUNTING_CUSTOMER_PARTY));

        return [
            'supplierEndpointScheme' => $supplier !== null ? $supplier->scheme() : null,
            'supplierEndpointValue'  => $supplier !== null ? $supplier->value() : null,
            'customerEndpointScheme' => $customer !== null ? $customer->scheme() : null,
            'customerEndpointValue'  => $customer !== null ? $customer->value() : null,
        ];
    }

    /**
     * Reads the issue, due, delivery and tax point dates.
     *
     * @return array<string, mixed>
     */
    private function readDates(UblXPath $xpath): array
    {
        return [
            'issueDate' => $xpath->value(self::cbc(UblElementName::ISSUE_DATE)),
            // The due date is a root element, or the PaymentMeans due date when the root omits it.
            'dueDate' => $xpath->firstValue(
                self::cbc(UblElementName::DUE_DATE),
                self::cbcChild(self::paymentMeans(), UblElementName::PAYMENT_DUE_DATE)
            ),
            'deliveryDate' => $xpath->value(self::path(
                self::cac(UblElementName::DELIVERY),
                self::cbc(UblElementName::ACTUAL_DELIVERY_DATE)
            )),
            'taxPointDate' => $xpath->value(self::cbc(UblElementName::TAX_POINT_DATE)),
        ];
    }

    /**
     * Reads the buyer reference, order reference, notes and the preceding invoice number.
     *
     * @return array<string, mixed>
     */
    private function readReferences(UblXPath $xpath): array
    {
        return [
            'buyerReference' => $xpath->value(self::cbc(UblElementName::BUYER_REFERENCE)),
            'orderReference' => $xpath->value(self::path(
                self::cac(UblElementName::ORDER_REFERENCE),
                self::cbc(UblElementName::ID)
            )),
            'notes'                  => $xpath->values(self::cbc(UblElementName::NOTE)),
            'precedingInvoiceNumber' => $xpath->value(self::path(
                self::cac(UblElementName::BILLING_REFERENCE),
                self::cac(UblElementName::INVOICE_DOCUMENT_REFERENCE),
                self::cbc(UblElementName::ID)
            )),
        ];
    }

    /**
     * Reads the payment means code and reference, the payee IBAN and whether payment terms exist.
     *
     * @return array<string, mixed>
     */
    private function readPayment(UblXPath $xpath): array
    {
        $paymentMeans = self::paymentMeans();

        return [
            'paymentMeansCode' => $xpath->value(self::cbcChild($paymentMeans, UblElementName::PAYMENT_MEANS_CODE)),
            'paymentId'        => $xpath->value(self::cbcChild($paymentMeans, UblElementName::PAYMENT_ID)),
            'hasPaymentTerms'  => $xpath->nodes(self::cac(UblElementName::PAYMENT_TERMS)) !== [],
            'iban'             => $xpath->value(self::path(
                $paymentMeans,
                self::cac(UblElementName::PAYEE_FINANCIAL_ACCOUNT),
                self::cbc(UblElementName::ID)
            )),
        ];
    }

    /**
     * Reads the document currency and the legal monetary totals and tax total.
     *
     * @return array<string, mixed>
     */
    private function readTotals(UblXPath $xpath, ?string $currency): array
    {
        $totals = self::cac(UblElementName::LEGAL_MONETARY_TOTAL);

        return [
            'currency'            => $currency,
            'payableAmount'       => $xpath->value(self::cbcChild($totals, UblElementName::PAYABLE_AMOUNT)),
            'taxExclusiveAmount'  => $xpath->value(self::cbcChild($totals, UblElementName::TAX_EXCLUSIVE_AMOUNT)),
            'taxInclusiveAmount'  => $xpath->value(self::cbcChild($totals, UblElementName::TAX_INCLUSIVE_AMOUNT)),
            'lineExtensionAmount' => $xpath->value(self::cbcChild($totals, UblElementName::LINE_EXTENSION_AMOUNT)),
            // TaxTotal/TaxAmount contains the document tax total.
            'taxAmount' => $xpath->value(self::path(
                self::cac(UblElementName::TAX_TOTAL),
                self::cbc(UblElementName::TAX_AMOUNT)
            )),
            'roundingAmount' => $xpath->value(self::cbcChild($totals, UblElementName::PAYABLE_ROUNDING_AMOUNT)),
        ];
    }

    /** XPath of Party/EndpointID under an accounting party role element. */
    private function partyEndpointPath(string $role): string
    {
        return self::path(
            self::cac($role),
            self::cac(UblElementName::PARTY),
            self::cbc(UblElementName::ENDPOINT_ID)
        );
    }

    /** XPath of a root-level cac:PaymentMeans element. */
    private static function paymentMeans(): string
    {
        return self::cac(UblElementName::PAYMENT_MEANS);
    }
}
