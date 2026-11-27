<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Validation;

use DreaM1\EfakturaConnector\Core\Ubl\UblNamespaces;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolBillingRule;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationSeverity;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationStepMetadata;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationStepStatus;
use DreaM1\EfakturaConnector\Domain\Ubl\PeppolBillingProfile;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentRoot;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\LocalUblValidator;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\PeppolBillingHeaderValidationStage;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Validation\PeppolBillingHeaderRules;
use PHPUnit\Framework\TestCase;

/** Tests the Peppol Billing header validation stage. */
class PeppolBillingHeaderValidationStageTest extends TestCase
{
    public function testIncludesTheStandaloneBrCo25ViolationInTheLocalResult(): void
    {
        $result = (new LocalUblValidator([
            new PeppolBillingHeaderValidationStage(new PeppolBillingHeaderRules()),
        ]))->validateDocument($this->document($this->billingXml(UblDocumentRoot::INVOICE)));

        $this->assertFalse($result->isValid());
        $this->assertSame(PeppolBillingHeaderValidationStage::ID, $result->issues()[0]->stageId());
        $this->assertSame(PeppolBillingRule::PAYMENT_DUE_DATE_OR_TERMS, $result->issues()[0]->code());
        $this->assertSame(UblValidationSeverity::ERROR, $result->issues()[0]->severity()->value());
        $this->assertSame(
            UblValidationStepStatus::FAILED,
            $result->stepResults()[PeppolBillingHeaderValidationStage::ID]->metadata()->status()->value()
        );
        $this->assertSame(
            ['peppol_bis_billing' => '3.0'],
            $result->stepResults()[PeppolBillingHeaderValidationStage::ID]->metadata()->ruleVersions()
        );
        $this->assertSame(
            PeppolBillingRule::PAYMENT_DUE_DATE_OR_TERMS,
            $result->issuesForStep(PeppolBillingHeaderValidationStage::ID)[0]->rule()
        );
        $this->assertSame(
            UblValidationSeverity::ERROR,
            $result->issuesForStep(PeppolBillingHeaderValidationStage::ID)[0]->severity()->value()
        );
    }

    public function testRequiresDueDateForPositiveInvoiceButNotCreditNote(): void
    {
        $invoiceCodes    = $this->ruleIdentifiers($this->billingXml(UblDocumentRoot::INVOICE));
        $creditNoteCodes = $this->ruleIdentifiers($this->billingXml(UblDocumentRoot::CREDIT_NOTE));

        $this->assertContains(PeppolBillingRule::PAYMENT_DUE_DATE_OR_TERMS, $invoiceCodes);
        $this->assertNotContains(PeppolBillingRule::PAYMENT_DUE_DATE_OR_TERMS, $creditNoteCodes);
    }

    public function testDoesNotEmitFindingsForRulesCoveredBySchematron(): void
    {
        $xml = '<Invoice xmlns="' . UblNamespaces::DOCUMENT_BASE . 'Invoice-2"'
            . ' xmlns:cac="' . UblNamespaces::COMMON_AGGREGATE_COMPONENTS . '"'
            . ' xmlns:cbc="' . UblNamespaces::COMMON_BASIC_COMPONENTS . '">'
            . '<cac:LegalMonetaryTotal><cbc:PayableAmount>0.00</cbc:PayableAmount>'
            . '</cac:LegalMonetaryTotal></Invoice>';
        $result = (new LocalUblValidator([
            new PeppolBillingHeaderValidationStage(new PeppolBillingHeaderRules()),
        ]))->validateDocument($this->document($xml));

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->issuesForStep(PeppolBillingHeaderValidationStage::ID));
    }

    /**
     * @param  string   $xml Complete header fixture without a due date or payment terms.
     * @return string[] Finding codes from the header validation stage.
     */
    private function ruleIdentifiers(string $xml): array
    {
        $result = (new LocalUblValidator([
            new PeppolBillingHeaderValidationStage(new PeppolBillingHeaderRules()),
        ]))->validateDocument($this->document($xml));

        $findings = $result->issuesForStep(PeppolBillingHeaderValidationStage::ID);

        return array_map(static function ($finding): string {
            return $finding->rule();
        }, $findings);
    }

    private function billingXml(string $root): string
    {
        $typeElement = $root === UblDocumentRoot::CREDIT_NOTE
            ? 'CreditNoteTypeCode'
            : 'InvoiceTypeCode';
        $typeCode = $root === UblDocumentRoot::CREDIT_NOTE ? '381' : '380';

        return '<' . $root
            . ' xmlns="' . UblNamespaces::DOCUMENT_BASE . $root . '-2"'
            . ' xmlns:cac="' . UblNamespaces::COMMON_AGGREGATE_COMPONENTS . '"'
            . ' xmlns:cbc="' . UblNamespaces::COMMON_BASIC_COMPONENTS . '">'
            . '<cbc:CustomizationID>' . PeppolBillingProfile::CUSTOMIZATION_ID . '</cbc:CustomizationID>'
            . '<cbc:ProfileID>' . PeppolBillingProfile::PROCESS_ID . '</cbc:ProfileID>'
            . '<cbc:ID>SYNTHETIC-1</cbc:ID>'
            . '<cbc:IssueDate>2026-11-10</cbc:IssueDate>'
            . '<cbc:' . $typeElement . '>' . $typeCode . '</cbc:' . $typeElement . '>'
            . '<cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>'
            . '<cbc:BuyerReference>BUYER-1</cbc:BuyerReference>'
            . '<cac:AccountingSupplierParty><cac:Party>'
            . '<cbc:EndpointID schemeID="0088">SELLER-1</cbc:EndpointID>'
            . '</cac:Party></cac:AccountingSupplierParty>'
            . '<cac:AccountingCustomerParty><cac:Party>'
            . '<cbc:EndpointID schemeID="0088">BUYER-1</cbc:EndpointID>'
            . '</cac:Party></cac:AccountingCustomerParty>'
            . '<cac:LegalMonetaryTotal><cbc:PayableAmount>100.00</cbc:PayableAmount>'
            . '</cac:LegalMonetaryTotal>'
            . '</' . $root . '>';
    }

    private function document(string $xml): UblXmlDocument
    {
        return UblXmlDocument::fromXml($xml);
    }
}
