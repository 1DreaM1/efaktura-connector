<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader;

use DOMNode;
use DreaM1\EfakturaConnector\Core\Ubl\UblAttributeName;
use DreaM1\EfakturaConnector\Core\Ubl\UblElementName;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentContent;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentLine;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentLineAmounts;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentLineQuantity;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentLineVat;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentTaxSummary;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CurrencyCodeList;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\UnitCodeList;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\UblDocumentType;

/**
 * Reads supplier, customer, invoice or credit note line, and tax data into UBL content values.
 *
 * @internal used by UblDocumentReader
 */
final class UblDocumentContentReader
{
    use BuildsUblXPaths;

    private UblDocumentPartyReader $partyReader;

    /** Creates the content reader and its helper for supplier and customer party fields. */
    public function __construct()
    {
        $this->partyReader = new UblDocumentPartyReader();
    }

    /**
     * Reads party, line and tax data using Invoice or CreditNote element names as appropriate.
     *
     * If a line amount omits its currency, the document currency is used. Tax subtotals come from
     * the first TaxTotal, which UBL uses for the document currency.
     *
     * @param  UblXPath           $xpath            XPath helper or expression used to query the XML document.
     * @param  string             $kind             UBL root local name: Invoice or CreditNote.
     * @param  string|null        $documentCurrency Currency code used by the document.
     * @return UblDocumentContent
     */
    public function read(UblXPath $xpath, string $kind, ?string $documentCurrency): UblDocumentContent
    {
        // Invoice and CreditNote share the content shape but use different line and quantity names.
        $type = UblDocumentType::fromKind($kind);

        // Read supplier from cac:AccountingSupplierParty/cac:Party and customer from cac:AccountingCustomerParty/cac:Party.
        $supplier = self::cacChild(self::cac(UblElementName::ACCOUNTING_SUPPLIER_PARTY), UblElementName::PARTY);
        $customer = self::cacChild(self::cac(UblElementName::ACCOUNTING_CUSTOMER_PARTY), UblElementName::PARTY);

        // Invoice uses cac:InvoiceLine; CreditNote uses cac:CreditNoteLine.
        $lineNodes = $xpath->nodes(self::cac($type->lineElement()));

        return UblDocumentContent::create(
            $kind,
            $this->partyReader->read($xpath, $supplier),
            $this->partyReader->read($xpath, $customer),
            $this->readLines($xpath, $lineNodes, self::cbc($type->quantityElement()), $documentCurrency),
            false,
            $this->readTaxSummary($xpath)
        );
    }

    /**
     * @param  DOMNode[]         $lineNodes UBL XML nodes representing InvoiceLine or CreditNoteLine elements.
     * @return UblDocumentLine[]
     */
    private function readLines(UblXPath $xpath, array $lineNodes, string $quantity, ?string $documentCurrency): array
    {
        $lines = [];

        foreach ($lineNodes as $line) {
            $lines[] = UblDocumentLine::fromValues(
                // cbc:ID identifies the invoice or credit note line.
                $xpath->nodeValue($line, self::cbc(UblElementName::ID)),
                $this->readLineName($xpath, $line),
                $this->readLineQuantity($xpath, $line, $quantity),
                $this->readLineAmounts($xpath, $line, $documentCurrency),
                $this->readLineVat($xpath, $line)
            );
        }

        return $lines;
    }

    /** Prefers Item/Name and falls back to Item/Description for the line label. */
    private function readLineName(UblXPath $xpath, DOMNode $line): ?string
    {
        $item = self::cac(UblElementName::ITEM);

        return $xpath->firstNodeValue(
            $line,
            self::cbcChild($item, UblElementName::NAME),
            self::cbcChild($item, UblElementName::DESCRIPTION)
        );
    }

    /**
     * Reads the quantity from InvoicedQuantity or CreditedQuantity, depending on document kind, with its unitCode.
     * Raw codes are preserved even when a bundled code list cannot resolve them.
     */
    private function readLineQuantity(UblXPath $xpath, DOMNode $line, string $quantity): UblDocumentLineQuantity
    {
        $unitCode = $xpath->nodeValue($line, self::path($quantity, '@' . UblAttributeName::UNIT_CODE));

        return new UblDocumentLineQuantity(
            $xpath->nodeValue($line, $quantity),
            $unitCode,
            $unitCode !== null ? UnitCodeList::resolve($unitCode) : null
        );
    }

    /**
     * Price/PriceAmount is the unit price; LineExtensionAmount is the line net total and carries currencyID,
     * which falls back to the document currency when it is omitted.
     */
    private function readLineAmounts(UblXPath $xpath, DOMNode $line, ?string $documentCurrency): UblDocumentLineAmounts
    {
        $currencyCode = $xpath->nodeValue($line, self::path(
            self::cbc(UblElementName::LINE_EXTENSION_AMOUNT),
            '@' . UblAttributeName::CURRENCY_ID
        )) ?? $documentCurrency;

        return new UblDocumentLineAmounts(
            $xpath->nodeValue($line, self::cbcChild(self::cac(UblElementName::PRICE), UblElementName::PRICE_AMOUNT)),
            $xpath->nodeValue($line, self::cbc(UblElementName::LINE_EXTENSION_AMOUNT)),
            $currencyCode,
            CurrencyCodeList::resolve($currencyCode)
        );
    }

    /** Item/ClassifiedTaxCategory provides the VAT percentage and category code. */
    private function readLineVat(UblXPath $xpath, DOMNode $line): UblDocumentLineVat
    {
        $category = self::cacChild(self::cac(UblElementName::ITEM), UblElementName::CLASSIFIED_TAX_CATEGORY);

        return new UblDocumentLineVat(
            $xpath->nodeValue($line, self::cbcChild($category, UblElementName::PERCENT)),
            $xpath->nodeValue($line, self::cbcChild($category, UblElementName::ID))
        );
    }
    /** @return UblDocumentTaxSummary[] subtotals of the first cac:TaxTotal (the one in document currency) */
    private function readTaxSummary(UblXPath $xpath): array
    {
        $taxSummary = [];

        // Read TaxSubtotal entries from the first TaxTotal, which is expressed in document currency.
        foreach (
            $xpath->nodes(self::path(
                self::cac(UblElementName::TAX_TOTAL) . '[1]',
                self::cac(UblElementName::TAX_SUBTOTAL)
            )) as $subtotal
        ) {
            $taxSummary[] = UblDocumentTaxSummary::fromValues(
                // TaxCategory/ID and TaxCategory/Percent identify the VAT category and rate.
                (string) $xpath->nodeValue($subtotal, self::path(
                    self::cac(UblElementName::TAX_CATEGORY),
                    self::cbc(UblElementName::ID)
                )),
                (string) $xpath->nodeValue($subtotal, self::path(
                    self::cac(UblElementName::TAX_CATEGORY),
                    self::cbc(UblElementName::PERCENT)
                )),
                // TaxableAmount is the taxable base; TaxAmount is tax for this subtotal.
                (string) $xpath->nodeValue($subtotal, self::cbc(UblElementName::TAXABLE_AMOUNT)),
                (string) $xpath->nodeValue($subtotal, self::cbc(UblElementName::TAX_AMOUNT))
            );
        }

        return $taxSummary;
    }
}
