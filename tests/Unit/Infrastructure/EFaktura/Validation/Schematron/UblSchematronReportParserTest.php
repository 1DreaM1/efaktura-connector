<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Validation\Schematron;

use DOMDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolBillingRule;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolBillingRuleIssue;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\SchematronReport;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\UblSchematronReportParser;
use DreaM1\EfakturaConnector\Support\Xml\XmlParseException;
use PHPUnit\Framework\TestCase;

/** Tests parsing of UBL Schematron validation reports. */
class UblSchematronReportParserTest extends TestCase
{
    private const CAC_NAMESPACE = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const CBC_NAMESPACE = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    public function testReturnsRuleAndSourceLineDetailsWithoutFormattingUserText(): void
    {
        $report = $this->report(
            '<svrl:failed-assert id="PEPPOL-EN16931-R120" location="/Invoice/cac:InvoiceLine[1]">'
                . '<svrl:text>Line net amount must equal the calculated amount.</svrl:text>'
                . '</svrl:failed-assert>'
        );

        $issues = $this->parser()->parseIssues([new SchematronReport($report)], $this->invoiceSource());
        $issue  = $issues[0]->toArray();

        $this->assertCount(1, $issues);
        $this->assertSame('PEPPOL-EN16931-R120', $issue['rule_id']);
        $this->assertSame('/Invoice/cac:InvoiceLine[1]', $issue['location']);
        $this->assertSame('Line net amount must equal the calculated amount.', $issue['message']);
        $this->assertSame('Synthetic item', $issue['line']['name']);
        $this->assertSame('1', $issue['line']['id']);
        $this->assertSame('2', $issue['line']['quantity']);
        $this->assertSame('50.00', $issue['line']['unit_price']);
        $this->assertSame('99.00', $issue['line']['line_amount']);
        $this->assertSame('EUR', $issue['line']['currency']);
    }

    public function testIncludesCreditNoteBaseQuantityAndAdjustmentsAsData(): void
    {
        $report = $this->report(
            '<svrl:failed-assert id="PEPPOL-EN16931-R120" location="/CreditNote/cac:CreditNoteLine[1]">'
                . '<svrl:text>Line net amount mismatch.</svrl:text>'
                . '</svrl:failed-assert>'
        );

        $issue = $this->parser()->parseIssues([new SchematronReport($report)], $this->creditNoteSource())[0]->toArray();

        $this->assertSame('Synthetic credit item', $issue['line']['name']);
        $this->assertSame('CN-1', $issue['line']['id']);
        $this->assertSame('-2', $issue['line']['quantity']);
        $this->assertSame('2', $issue['line']['base_quantity']);
        $this->assertSame('-50.00', $issue['line']['line_amount']);
        $this->assertSame([['amount' => '10.00', 'charge' => false]], $issue['line']['adjustments']);
    }

    public function testKeepsRawRuleTextAndLocationForNonLineIssues(): void
    {
        $report = $this->report(
            '<svrl:failed-assert id="CUSTOM-1" location="/Invoice/cac:AccountingSupplierParty">'
                . '<svrl:text>[CUSTOM-1] - Supplier&#10;details are incomplete.</svrl:text>'
                . '</svrl:failed-assert>'
        );

        $parsedIssue = $this->parser()->parseIssues([new SchematronReport($report)], $this->invoiceSource())[0];
        $issue       = $parsedIssue->toArray();

        $this->assertSame('CUSTOM-1', $issue['rule_id']);
        $this->assertSame('/Invoice/cac:AccountingSupplierParty', $issue['location']);
        $this->assertSame("[CUSTOM-1] - Supplier\ndetails are incomplete.", $issue['message']);
        $this->assertNull($issue['line']);
        $this->assertNull($parsedIssue->guidance());
    }

    public function testAddsRuleGuidanceToNormalizedFindings(): void
    {
        $report = $this->report(
            '<svrl:failed-assert id="BR-02" location="/Invoice/cbc:ID">'
                . '<svrl:text>An invoice identifier is required.</svrl:text>'
                . '</svrl:failed-assert>'
        );

        $issue = $this->parser()->parseIssues([new SchematronReport($report)], $this->invoiceSource())[0];

        $this->assertNotNull($issue->guidance());
        $this->assertSame(
            'https://test-docs.peppol.eu/poacc/billing/3.0/2026-Q4-MR/rules/ubl-tc434/BR-02/',
            $issue->guidance()->referenceUrl()
        );
        $this->assertSame(
            'Provide the invoice or credit note identifier in cbc:ID.',
            $issue->guidance()->recommendedAction()
        );
        $this->assertSame(
            $issue->guidance()->toArray(),
            $issue->toFinding('peppol_schematron')->toArray()['guidance']
        );
    }

    public function testUsesTheSameBrCo25RecommendationForCoreAndSchematronFindings(): void
    {
        $report = $this->report(
            '<svrl:failed-assert id="BR-CO-25" location="/Invoice/cbc:DueDate">'
                . '<svrl:text>A positive invoice needs a due date or payment terms.</svrl:text>'
                . '</svrl:failed-assert>'
        );

        $schematronIssue = $this->parser()->parseIssues(
            [new SchematronReport($report)],
            $this->invoiceSource()
        )[0];
        $coreFinding = (new PeppolBillingRuleIssue(PeppolBillingRule::PAYMENT_DUE_DATE_OR_TERMS))
            ->toFinding('peppol_billing_header');
        $schematronGuidance = $schematronIssue->guidance();
        $coreGuidance       = $coreFinding->guidance();

        $this->assertNotNull($schematronGuidance);
        $this->assertNotNull($coreGuidance);
        $this->assertSame(
            'For a positive invoice amount, provide a due date or payment terms.',
            $schematronGuidance->recommendedAction()
        );
        $this->assertSame($coreGuidance->recommendedAction(), $schematronGuidance->recommendedAction());
        $this->assertSame(
            $coreGuidance->recommendedAction(),
            $schematronIssue->toFinding('peppol_schematron')->toArray()['guidance']['recommended_action']
        );
    }

    public function testIncludesWarningAndInformationalAssertionsWithTheirSeverity(): void
    {
        $report = $this->report(
            '<svrl:failed-assert flag="warning" id="WARN-1"><svrl:text>Warning</svrl:text></svrl:failed-assert>'
                . '<svrl:failed-assert flag="info" id="INFO-1"><svrl:text>Information</svrl:text></svrl:failed-assert>'
                . '<svrl:successful-report id="PASS-1"><svrl:text>Valid</svrl:text></svrl:successful-report>'
        );

        $issues = $this->parser()->parseIssues([new SchematronReport($report)], $this->invoiceSource());

        $this->assertCount(2, $issues);
        $this->assertSame(['warning', 'info'], array_map(
            static fn ($issue): string => $issue->severity()->value(),
            $issues
        ));
        $this->assertSame(['warning', 'info'], array_column(array_map(
            static fn ($issue): array => $issue->toArray(),
            $issues
        ), 'severity'));
    }

    public function testRejectsFailedAssertionsWithEmptyMessages(): void
    {
        $report = $this->report(
            '<svrl:failed-assert id="EMPTY-1"><svrl:text> </svrl:text></svrl:failed-assert>'
        );

        $this->expectException(LocalValidationInfrastructureException::class);
        $this->expectExceptionMessage('invalid report');
        $this->parser()->parseIssues([new SchematronReport($report)], $this->invoiceSource());
    }

    public function testKeepsUnmatchedLineLocationsWithoutSourceLineDetails(): void
    {
        $report = $this->report(
            '<svrl:failed-assert id="LINE-99" location="/Invoice/cac:InvoiceLine[99]">'
                . '<svrl:text>Line not found in source.</svrl:text></svrl:failed-assert>'
        );

        $issues = $this->parser()->parseIssues([new SchematronReport($report)], $this->invoiceSource());

        $this->assertCount(1, $issues);
        $this->assertSame('LINE-99', $issues[0]->toArray()['rule_id']);
        $this->assertNull($issues[0]->toArray()['line']);
    }

    public function testMalformedReportIsRejectedAndRestoresLibxmlErrorMode(): void
    {
        $previous = libxml_use_internal_errors(false);

        try {
            $this->parser()->parseIssues([new SchematronReport('<svrl:schematron-output')], $this->invoiceSource());
            $this->fail('Expected malformed Schematron report to be rejected.');
        } catch (LocalValidationInfrastructureException $exception) {
            $this->assertStringContainsString('invalid report', $exception->getMessage());
            $this->assertFalse(libxml_use_internal_errors());
        } finally {
            libxml_use_internal_errors($previous);
        }
    }

    public function testRejectsAnUnexpectedSchematronNamespace(): void
    {
        $report = new SchematronReport('<schematron-output xmlns="urn:synthetic:wrong-svrl-namespace"/>');

        $this->expectException(LocalValidationInfrastructureException::class);
        $this->expectExceptionMessage('invalid report');
        $this->parser()->parseIssues([$report], $this->invoiceSource());
    }

    public function testRejectsReportsWithDocumentTypeDeclarations(): void
    {
        $report = new SchematronReport(
            '<!DOCTYPE svrl:schematron-output [<!ENTITY synthetic "unsafe">]>'
                . $this->report('<svrl:failed-assert><svrl:text>&synthetic;</svrl:text></svrl:failed-assert>')
        );

        try {
            $this->parser()->parseIssues([$report], $this->invoiceSource());
            $this->fail('A Schematron report with a DTD should be rejected.');
        } catch (LocalValidationInfrastructureException $exception) {
            $this->assertStringContainsString('invalid report', $exception->getMessage());
            $this->assertInstanceOf(XmlParseException::class, $exception->getPrevious());
            $cause = $exception->getPrevious();

            if ($cause instanceof XmlParseException) {
                $this->assertStringContainsString('prohibited DTD', $cause->getMessage());
            }
        }
    }

    private function parser(): UblSchematronReportParser
    {
        return new UblSchematronReportParser();
    }

    private function invoiceSource(): DOMDocument
    {
        $source = new DOMDocument();
        $source->loadXML(
            '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"'
                . ' xmlns:cac="' . self::CAC_NAMESPACE . '"'
                . ' xmlns:cbc="' . self::CBC_NAMESPACE . '">'
                . '<cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>'
                . '<cac:AccountingSupplierParty/><cac:InvoiceLine>'
                . '<cbc:ID>1</cbc:ID><cbc:InvoicedQuantity>2</cbc:InvoicedQuantity>'
                . '<cbc:LineExtensionAmount currencyID="EUR">99.00</cbc:LineExtensionAmount>'
                . '<cac:Item><cbc:Name>Synthetic item</cbc:Name></cac:Item>'
                . '<cac:Price><cbc:PriceAmount currencyID="EUR">50.00</cbc:PriceAmount></cac:Price>'
                . '</cac:InvoiceLine></Invoice>'
        );

        return $source;
    }

    private function creditNoteSource(): DOMDocument
    {
        $source = new DOMDocument();
        $source->loadXML(
            '<CreditNote xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2"'
                . ' xmlns:cac="' . self::CAC_NAMESPACE . '"'
                . ' xmlns:cbc="' . self::CBC_NAMESPACE . '">'
                . '<cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode><cac:CreditNoteLine>'
                . '<cbc:ID>CN-1</cbc:ID><cbc:CreditedQuantity>-2</cbc:CreditedQuantity>'
                . '<cbc:LineExtensionAmount currencyID="EUR">-50.00</cbc:LineExtensionAmount>'
                . '<cac:Item><cbc:Name>Synthetic credit item</cbc:Name></cac:Item>'
                . '<cac:Price><cbc:PriceAmount currencyID="EUR">50.00</cbc:PriceAmount>'
                . '<cbc:BaseQuantity>2</cbc:BaseQuantity></cac:Price>'
                . '<cac:AllowanceCharge><cbc:ChargeIndicator>false</cbc:ChargeIndicator>'
                . '<cbc:Amount currencyID="EUR">10.00</cbc:Amount></cac:AllowanceCharge>'
                . '</cac:CreditNoteLine></CreditNote>'
        );

        return $source;
    }

    private function report(string $assertions): string
    {
        return '<svrl:schematron-output xmlns:svrl="http://purl.oclc.org/dsdl/svrl">'
            . $assertions
            . '</svrl:schematron-output>';
    }
}
