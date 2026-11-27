<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Validation;

use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationSeverity;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\LocalUblValidator;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\PeppolSlovakBillingValidationStage;
use PHPUnit\Framework\TestCase;

/** Tests Slovak-specific Peppol Billing validation findings. */
class PeppolSlovakBillingValidationStageTest extends TestCase
{
    public function testReportsAllApplicableSlovakRulesAsWarnings(): void
    {
        $document = $this->document(
            '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"'
                . ' xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"'
                . ' xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">'
                . '<cbc:InvoiceTypeCode>384</cbc:InvoiceTypeCode>'
                . $this->party('AccountingSupplierParty')
                . $this->party('AccountingCustomerParty')
                . '</Invoice>'
        );
        $result = (new LocalUblValidator([
            new PeppolSlovakBillingValidationStage(),
        ]))->validateDocument($document);

        $issues = $result->issuesForStep(PeppolSlovakBillingValidationStage::ID);
        $this->assertSame([
            'SK-R-001',
            'SK-R-002',
            'SK-R-003',
            'SK-R-004',
            'SK-R-005',
            'SK-R-006',
            'SK-R-007',
        ], array_map(static fn ($issue): string => $issue->toArray()['rule_id'], $issues));

        foreach ($issues as $issue) {
            $this->assertSame(UblValidationSeverity::WARNING, $issue->severity()->value());
        }

        $this->assertTrue($result->isValid());
        $this->assertSame(
            PeppolSlovakBillingValidationStage::RULES_VERSION,
            $result->stepResults()[PeppolSlovakBillingValidationStage::ID]
                ->metadata()->ruleVersions()['peppol_slovak_country_rules']
        );
    }

    public function testDoesNotApplySlovakWarningsToCrossBorderParties(): void
    {
        $document = $this->document(
            '<CreditNote xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2"'
                . ' xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"'
                . ' xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">'
                . $this->party('AccountingSupplierParty', 'SK')
                . $this->party('AccountingCustomerParty', 'CZ')
                . '</CreditNote>'
        );

        $result = (new LocalUblValidator([
            new PeppolSlovakBillingValidationStage(),
        ]))->validateDocument($document);

        $this->assertSame([], $result->issues());
    }

    public function testSlovakAddressRulesCheckElementPresenceAsDefinedBySchematron(): void
    {
        $emptyAddressFields = '<cbc:StreetName/><cbc:CityName/><cbc:PostalZone/>';
        $document           = $this->document(
            '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"'
                . ' xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"'
                . ' xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">'
                . $this->party('AccountingSupplierParty', 'SK', null, $emptyAddressFields)
                . $this->party('AccountingCustomerParty', 'SK', null, $emptyAddressFields)
                . '</Invoice>'
        );

        $issues = (new PeppolSlovakBillingValidationStage())->validate($document)->issues();

        $this->assertSame([], $issues);
    }

    /**
     * @dataProvider missingDomesticAddressFieldCases
     */
    public function testReportsEachMissingDomesticAddressFieldIndependently(
        string $partyRole,
        string $missingElement,
        string $expectedRule
    ): void {
        $supplierFields = $this->addressFieldsWithout(
            $partyRole === 'AccountingSupplierParty' ? $missingElement : ''
        );
        $customerFields = $this->addressFieldsWithout(
            $partyRole === 'AccountingCustomerParty' ? $missingElement : ''
        );
        $document = $this->envelope(
            'Invoice',
            '<cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>'
                . $this->party('AccountingSupplierParty', 'SK', null, $supplierFields)
                . $this->party('AccountingCustomerParty', 'SK', null, $customerFields)
        );

        $issues = (new PeppolSlovakBillingValidationStage())->validate($document)->issues();

        $this->assertSame([$expectedRule], array_map(static function ($issue): string {
            return $issue->toArray()['rule_id'];
        }, $issues));
        $this->assertSame(UblValidationSeverity::WARNING, $issues[0]->severity()->value());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function missingDomesticAddressFieldCases(): array
    {
        return [
            'seller street'   => ['AccountingSupplierParty', 'StreetName', 'SK-R-002'],
            'seller city'     => ['AccountingSupplierParty', 'CityName', 'SK-R-003'],
            'seller postcode' => ['AccountingSupplierParty', 'PostalZone', 'SK-R-004'],
            'buyer street'    => ['AccountingCustomerParty', 'StreetName', 'SK-R-005'],
            'buyer city'      => ['AccountingCustomerParty', 'CityName', 'SK-R-006'],
            'buyer postcode'  => ['AccountingCustomerParty', 'PostalZone', 'SK-R-007'],
        ];
    }

    /**
     * @dataProvider correctedInvoiceRuleExemptionCases
     */
    public function testCorrectedInvoiceRuleHonorsItsApplicabilityConditions(
        string $root,
        string $invoiceType,
        ?string $supplierVatCountry,
        ?string $customerVatCountry,
        bool $hasBillingReference
    ): void {
        $billingReference = $hasBillingReference
            ? '<cac:BillingReference><cac:InvoiceDocumentReference><cbc:ID>INV-1</cbc:ID>'
                . '</cac:InvoiceDocumentReference></cac:BillingReference>'
            : '';
        $typeElement = $root === 'Invoice' ? 'InvoiceTypeCode' : 'CreditNoteTypeCode';
        $document    = $this->envelope(
            $root,
            '<cbc:' . $typeElement . '>' . $invoiceType . '</cbc:' . $typeElement . '>'
                . $this->party('AccountingSupplierParty', 'SK', $supplierVatCountry, $this->completeAddressFields())
                . $this->party('AccountingCustomerParty', 'SK', $customerVatCountry, $this->completeAddressFields())
                . $billingReference
        );

        $issues  = (new PeppolSlovakBillingValidationStage())->validate($document)->issues();
        $ruleIds = array_map(static function ($issue): string {
            return $issue->toArray()['rule_id'];
        }, $issues);

        $this->assertNotContains('SK-R-001', $ruleIds);
        $this->assertSame([], $issues);
    }

    /**
     * @return array<string, array{string, string, ?string, ?string, bool}>
     */
    public static function correctedInvoiceRuleExemptionCases(): array
    {
        return [
            'credit note'                     => ['CreditNote', '381', null, null, false],
            'ordinary invoice'                => ['Invoice', '380', null, null, false],
            'existing preceding reference'    => ['Invoice', '384', null, null, true],
            'supplier VAT country outside SK' => ['Invoice', '384', 'DE', null, false],
            'buyer VAT country outside SK'    => ['Invoice', '384', null, 'DE', false],
        ];
    }

    public function testCorrectedInvoiceRuleUsesVatCountryBeforePostalCountry(): void
    {
        $document = $this->document(
            '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"'
                . ' xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"'
                . ' xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">'
                . '<cbc:InvoiceTypeCode>384</cbc:InvoiceTypeCode>'
                . $this->party('AccountingSupplierParty', 'DE', 'SK')
                . $this->party('AccountingCustomerParty', 'DE', 'SK')
                . '</Invoice>'
        );

        $issues = (new PeppolSlovakBillingValidationStage())->validate($document)->issues();

        $this->assertCount(1, $issues);
        $this->assertSame('SK-R-001', $issues[0]->toArray()['rule_id']);
    }

    public function testCorrectedInvoiceRuleFallsBackToTaxRepresentativeVatCountry(): void
    {
        $document = $this->document(
            '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"'
                . ' xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"'
                . ' xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">'
                . '<cbc:InvoiceTypeCode>384</cbc:InvoiceTypeCode>'
                . $this->party('AccountingSupplierParty', 'DE')
                . $this->party('AccountingCustomerParty', 'DE', 'SK')
                . '<cac:TaxRepresentativeParty><cac:Party><cac:PartyTaxScheme>'
                . '<cbc:CompanyID>SK1234567890</cbc:CompanyID><cac:TaxScheme><cbc:ID>VAT</cbc:ID>'
                . '</cac:TaxScheme></cac:PartyTaxScheme></cac:Party></cac:TaxRepresentativeParty>'
                . '</Invoice>'
        );

        $issues = (new PeppolSlovakBillingValidationStage())->validate($document)->issues();

        $this->assertCount(1, $issues);
        $this->assertSame('SK-R-001', $issues[0]->toArray()['rule_id']);
    }

    private function document(string $xml): UblXmlDocument
    {
        return UblXmlDocument::fromXml($xml);
    }

    private function envelope(string $root, string $content): UblXmlDocument
    {
        return $this->document(
            '<' . $root . ' xmlns="urn:oasis:names:specification:ubl:schema:xsd:' . $root . '-2"'
                . ' xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"'
                . ' xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">'
                . $content . '</' . $root . '>'
        );
    }

    private function completeAddressFields(): string
    {
        return '<cbc:StreetName>Test Street</cbc:StreetName>'
            . '<cbc:CityName>Test City</cbc:CityName>'
            . '<cbc:PostalZone>00000</cbc:PostalZone>';
    }

    private function addressFieldsWithout(string $missingElement): string
    {
        $fields = [
            'StreetName' => '<cbc:StreetName>Test Street</cbc:StreetName>',
            'CityName'   => '<cbc:CityName>Test City</cbc:CityName>',
            'PostalZone' => '<cbc:PostalZone>00000</cbc:PostalZone>',
        ];
        unset($fields[$missingElement]);

        return implode('', $fields);
    }

    private function party(
        string $role,
        string $country = 'SK',
        ?string $vatCountry = null,
        string $addressFields = ''
    ): string {
        $taxScheme = $vatCountry === null
            ? ''
            : '<cac:PartyTaxScheme><cbc:CompanyID>' . $vatCountry . '1234567890</cbc:CompanyID>'
                . '<cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:PartyTaxScheme>';

        return '<cac:' . $role . '><cac:Party><cac:PostalAddress>' . $addressFields . '<cac:Country>'
            . '<cbc:IdentificationCode>' . $country . '</cbc:IdentificationCode>'
            . '</cac:Country></cac:PostalAddress>' . $taxScheme . '</cac:Party></cac:' . $role . '>';
    }
}
