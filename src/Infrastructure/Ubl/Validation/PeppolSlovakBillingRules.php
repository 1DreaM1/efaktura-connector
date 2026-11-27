<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Validation;

use DreaM1\EfakturaConnector\Core\Ubl\UblElementName;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolValidationIssue;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationSeverity;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\DocumentTypeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\TaxSchemeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentRoot;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CountryCodeList;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader\UblXPath;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\UblXPathExpressionBuilder;

/** Warns about missing preceding-invoice references and Slovak supplier/buyer street, city, or postal-zone fields. */
final class PeppolSlovakBillingRules
{
    /** Rule ID for the required billing reference on corrected invoices. */
    public const RULE_SK_CORRECTED_INVOICE_REFERENCE = 'SK-R-001';
    /** Rule ID for a missing Slovak supplier street name. */
    public const RULE_SK_SUPPLIER_STREET_NAME = 'SK-R-002';
    /** Rule ID for a missing Slovak supplier city name. */
    public const RULE_SK_SUPPLIER_CITY_NAME = 'SK-R-003';
    /** Rule ID for a missing Slovak supplier postal code. */
    public const RULE_SK_SUPPLIER_POSTAL_CODE = 'SK-R-004';
    /** Rule ID for a missing Slovak buyer street name. */
    public const RULE_SK_BUYER_STREET_NAME = 'SK-R-005';
    /** Rule ID for a missing Slovak buyer city name. */
    public const RULE_SK_BUYER_CITY_NAME = 'SK-R-006';
    /** Rule ID for a missing Slovak buyer postal code. */
    public const RULE_SK_BUYER_POSTAL_CODE = 'SK-R-007';

    /**
     * Returns Slovak-specific warning findings for the supplied UBL document.
     *
     * @param  UblXmlDocument          $document Parsed Invoice or CreditNote to inspect.
     * @return PeppolValidationIssue[]
     */
    public function issues(UblXmlDocument $document): array
    {
        $xpath = new UblXPath($document->domDocument());
        // Check corrected-invoice references first; address warnings require both PostalAddress/Country codes to be SK.
        $supplierCountry = $this->postalCountry($xpath, UblElementName::ACCOUNTING_SUPPLIER_PARTY);
        $customerCountry = $this->postalCountry($xpath, UblElementName::ACCOUNTING_CUSTOMER_PARTY);

        $root   = $document->kind();
        $issues = [];

        $correctedInvoiceWarning = $this->correctedInvoiceReferenceWarning($xpath, $root);

        if ($correctedInvoiceWarning !== null) {
            $issues[] = $correctedInvoiceWarning;
        }

        if (
            $supplierCountry !== CountryCodeList::SLOVAKIA
            || $customerCountry !== CountryCodeList::SLOVAKIA
        ) {
            return $issues;
        }

        return array_merge($issues, $this->domesticAddressWarnings($xpath, $root));
    }

    private function correctedInvoiceReferenceWarning(UblXPath $xpath, string $root): ?PeppolValidationIssue
    {
        // SK-R-001 uses Peppol's VAT-based country selection and applies only to corrected invoices.
        if ($root !== UblDocumentRoot::INVOICE) {
            return null;
        }

        $supplierCountry = $this->peppolCountry($xpath, UblElementName::ACCOUNTING_SUPPLIER_PARTY);

        if ($supplierCountry !== CountryCodeList::SLOVAKIA) {
            return null;
        }

        $customerCountry = $this->peppolCountry($xpath, UblElementName::ACCOUNTING_CUSTOMER_PARTY);

        if ($customerCountry !== CountryCodeList::SLOVAKIA) {
            return null;
        }

        // SK-R-001 applies only when root Invoice/InvoiceTypeCode identifies a corrected invoice.
        $invoiceType = $xpath->value(UblXPathExpressionBuilder::cbc(UblElementName::INVOICE_TYPE_CODE));

        if ($invoiceType !== DocumentTypeCode::CORRECTED_INVOICE) {
            return null;
        }

        // The required preceding-invoice reference is cac:BillingReference.
        $billingReferences = $xpath->nodes(UblXPathExpressionBuilder::cac(UblElementName::BILLING_REFERENCE));

        if ($billingReferences !== []) {
            return null;
        }

        return $this->warning(
            self::RULE_SK_CORRECTED_INVOICE_REFERENCE,
            'If both supplier and customer are located in Slovakia, and if "Invoice type code" (BT-3) '
                . 'contains the code ' . DocumentTypeCode::CORRECTED_INVOICE
                . ' (Corrected invoice), "Preceding invoice reference" (BG-3) '
                . 'SHOULD be provided at least once.',
            '/' . $root . '/' . UblXPathExpressionBuilder::cac(UblElementName::BILLING_REFERENCE)
        );
    }

    /** @return PeppolValidationIssue[] */
    private function domesticAddressWarnings(UblXPath $xpath, string $root): array
    {
        $issues = [];

        // Require PostalAddress/StreetName, CityName, and PostalZone for each domestic supplier and customer.
        foreach ($this->domesticAddressRequirements() as $partyElement => $requirements) {
            $addressPath = UblXPathExpressionBuilder::path(
                UblXPathExpressionBuilder::cac($partyElement),
                UblXPathExpressionBuilder::cac(UblElementName::PARTY),
                UblXPathExpressionBuilder::cac(UblElementName::POSTAL_ADDRESS)
            ) . '/';

            foreach ($requirements as $ruleId => $requirement) {
                if ($xpath->nodes($addressPath . $requirement['element']) === []) {
                    $issues[] = $this->warning(
                        $ruleId,
                        'For domestic Slovak transactions, ' . $requirement['description'] . ' shall be provided.',
                        '/' . $root . '/' . $addressPath . $requirement['element']
                    );
                }
            }
        }

        return $issues;
    }

    /**
     * @return array<string, array<string, array{element: string, description: string}>>
     */
    private function domesticAddressRequirements(): array
    {
        return [
            UblElementName::ACCOUNTING_SUPPLIER_PARTY => [
                self::RULE_SK_SUPPLIER_STREET_NAME => [
                    'element'     => UblXPathExpressionBuilder::cbc(UblElementName::STREET_NAME),
                    'description' => 'Seller street name',
                ],
                self::RULE_SK_SUPPLIER_CITY_NAME => [
                    'element'     => UblXPathExpressionBuilder::cbc(UblElementName::CITY_NAME),
                    'description' => 'Seller city name',
                ],
                self::RULE_SK_SUPPLIER_POSTAL_CODE => [
                    'element'     => UblXPathExpressionBuilder::cbc(UblElementName::POSTAL_ZONE),
                    'description' => 'Seller postal code',
                ],
            ],
            UblElementName::ACCOUNTING_CUSTOMER_PARTY => [
                self::RULE_SK_BUYER_STREET_NAME => [
                    'element'     => UblXPathExpressionBuilder::cbc(UblElementName::STREET_NAME),
                    'description' => 'Buyer street name',
                ],
                self::RULE_SK_BUYER_CITY_NAME => [
                    'element'     => UblXPathExpressionBuilder::cbc(UblElementName::CITY_NAME),
                    'description' => 'Buyer city name',
                ],
                self::RULE_SK_BUYER_POSTAL_CODE => [
                    'element'     => UblXPathExpressionBuilder::cbc(UblElementName::POSTAL_ZONE),
                    'description' => 'Buyer postal code',
                ],
            ],
        ];
    }

    private function postalCountry(UblXPath $xpath, string $partyElement): ?string
    {
        $country = $xpath->value(UblXPathExpressionBuilder::path(
            UblXPathExpressionBuilder::cac($partyElement),
            UblXPathExpressionBuilder::cac(UblElementName::PARTY),
            UblXPathExpressionBuilder::cac(UblElementName::POSTAL_ADDRESS),
            UblXPathExpressionBuilder::cac(UblElementName::COUNTRY),
            UblXPathExpressionBuilder::cbc(UblElementName::IDENTIFICATION_CODE)
        ));

        return $country === null ? null : strtoupper(trim($country));
    }

    /** Matches the country selection used by the Peppol invoice business rules. */
    private function peppolCountry(UblXPath $xpath, string $partyElement): ?string
    {
        $partyPath = UblXPathExpressionBuilder::path(
            UblXPathExpressionBuilder::cac($partyElement),
            UblXPathExpressionBuilder::cac(UblElementName::PARTY)
        ) . '/';

        $vatTaxSchemeIdPath = UblXPathExpressionBuilder::path(
            UblXPathExpressionBuilder::cac(UblElementName::TAX_SCHEME),
            UblXPathExpressionBuilder::cbc(UblElementName::ID)
        );

        $vatTaxIdentifierPath = UblXPathExpressionBuilder::cac(UblElementName::PARTY_TAX_SCHEME)
            . '[' . $vatTaxSchemeIdPath . ' = "' . TaxSchemeCode::VAT . '"]/'
            . UblXPathExpressionBuilder::cbc(UblElementName::COMPANY_ID);

        $vatCountry = $this->vatCountry($xpath, $partyPath . $vatTaxIdentifierPath);

        if ($vatCountry !== null) {
            return $vatCountry;
        }

        // A supplier's tax representative VAT ID is the next Peppol country source before postal address.
        if ($partyElement === UblElementName::ACCOUNTING_SUPPLIER_PARTY) {
            $representativeVatCountry = $this->vatCountry(
                $xpath,
                UblXPathExpressionBuilder::path(
                    UblXPathExpressionBuilder::cac(UblElementName::TAX_REPRESENTATIVE_PARTY),
                    UblXPathExpressionBuilder::cac(UblElementName::PARTY),
                    $vatTaxIdentifierPath
                )
            );

            if ($representativeVatCountry !== null) {
                return $representativeVatCountry;
            }
        }

        return $this->postalCountry($xpath, $partyElement);
    }

    private function vatCountry(UblXPath $xpath, string $path): ?string
    {
        $vatIdentifier = $xpath->value($path);

        if ($vatIdentifier === null) {
            return null;
        }

        // Peppol VAT identifiers begin with the ISO country prefix used by these conditional rules.
        return strtoupper(substr(trim($vatIdentifier), 0, 2));
    }

    private function warning(string $ruleId, string $message, string $location): PeppolValidationIssue
    {
        return new PeppolValidationIssue(
            $ruleId,
            $message,
            $location,
            null,
            UblValidationSeverity::warning()
        );
    }
}
