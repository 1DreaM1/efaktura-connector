<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Validation\Schematron;

use DreaM1\EfakturaConnector\Core\Contracts\SchematronRunnerInterface;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolValidationIssue;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\SchematronReport;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\CenSchematronValidationStage;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\UblSchematronReportParser;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationResources;
use PHPUnit\Framework\TestCase;

/** Tests standalone CEN Schematron validation and endpoint scheme checks. */
class CenSchematronValidationStageTest extends TestCase
{
    /**
     * Adds a local finding when CEN accepts a deprecated endpoint scheme.
     *
     * @param string $scheme Endpoint scheme under test.
     * @param string $party  Party role containing the endpoint.
     * @param string $path   UBL path to the party aggregate.
     * @dataProvider deprecatedEndpointSchemes
     */
    public function testRejectsDeprecatedEasSchemeWhenCenReportAcceptsIt(
        string $scheme,
        string $party,
        string $path
    ): void {
        $xml = $this->invoiceXml(
            $party === 'supplier' ? $scheme : '0088',
            $party === 'customer' ? $scheme : '0088'
        );
        $document = $this->document($xml);
        $runner   = $this->runner($xml, $this->emptyReport());
        $stage    = new CenSchematronValidationStage($runner, new UblSchematronReportParser());

        $result = $stage->validate($document);

        $this->assertFalse($result->isValid());
        $this->assertCount(1, $result->issues());
        $this->assertInstanceOf(PeppolValidationIssue::class, $result->issues()[0]);
        $this->assertSame('PEPPOL-EN16931-CL008', $result->issues()[0]->toArray()['rule_id']);
        $this->assertSame('/Invoice/cac:' . $path . '/cac:Party/cbc:EndpointID', $result->issues()[0]->toArray()['location']);
    }

    /**
     * Provides deprecated endpoint schemes for both seller and buyer parties.
     *
     * @return array<array-key, array<array-key, mixed>> Endpoint scheme and party cases.
     */
    public function deprecatedEndpointSchemes(): array
    {
        $cases = [];

        foreach (['0193', '9909', '9912', '9916', '9917'] as $scheme) {
            $cases[$scheme . ' seller endpoint'] = [$scheme, 'supplier', 'AccountingSupplierParty'];
            $cases[$scheme . ' buyer endpoint']  = [$scheme, 'customer', 'AccountingCustomerParty'];
        }

        return $cases;
    }

    public function testAcceptsCurrentEasSchemesWhenCenReportHasNoFindings(): void
    {
        $xml   = $this->invoiceXml('0088', '0245');
        $stage = new CenSchematronValidationStage(
            $this->runner($xml, $this->emptyReport()),
            new UblSchematronReportParser()
        );

        $result = $stage->validate($this->document($xml));

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->issues());
    }

    public function testDoesNotDuplicateAnExistingCenEndpointSchemeFinding(): void
    {
        $xml    = $this->invoiceXml('9999', '0088');
        $report = '<svrl:schematron-output xmlns:svrl="http://purl.oclc.org/dsdl/svrl">'
            . '<svrl:failed-assert id="BR-CL-25" flag="fatal" location="/Invoice/cac:AccountingSupplierParty/cac:Party/cbc:EndpointID">'
            . '<svrl:text>Endpoint identifier scheme is not in the CEN list.</svrl:text>'
            . '</svrl:failed-assert></svrl:schematron-output>';
        $stage = new CenSchematronValidationStage(
            $this->runner($xml, $report),
            new UblSchematronReportParser()
        );

        $result = $stage->validate($this->document($xml));

        $this->assertFalse($result->isValid());
        $this->assertCount(1, $result->issues());
        $this->assertSame('BR-CL-25', $result->issues()[0]->toArray()['rule_id']);
    }

    public function testCenFailureForOnePartyDoesNotSuppressOtherPartyEasFinding(): void
    {
        $xml    = $this->invoiceXml('9999', '0193');
        $report = '<svrl:schematron-output xmlns:svrl="http://purl.oclc.org/dsdl/svrl">'
            . '<svrl:failed-assert id="BR-CL-25" flag="fatal"'
            . ' location="/Invoice/cac:AccountingSupplierParty/cac:Party/cbc:EndpointID">'
            . '<svrl:text>Supplier endpoint scheme is not in the CEN list.</svrl:text>'
            . '</svrl:failed-assert></svrl:schematron-output>';
        $stage = new CenSchematronValidationStage(
            $this->runner($xml, $report),
            new UblSchematronReportParser()
        );

        $result = $stage->validate($this->document($xml));

        $this->assertCount(2, $result->issues());
        $this->assertSame('BR-CL-25', $result->issues()[0]->toArray()['rule_id']);
        $this->assertSame('PEPPOL-EN16931-CL008', $result->issues()[1]->toArray()['rule_id']);
        $this->assertSame(
            '/Invoice/cac:AccountingCustomerParty/cac:Party/cbc:EndpointID',
            $result->issues()[1]->toArray()['location']
        );
    }

    private function runner(string $xml, string $report): SchematronRunnerInterface
    {
        $runner = $this->createMock(SchematronRunnerInterface::class);
        $runner->expects($this->once())
            ->method('run')
            ->with($xml, [ValidationResources::cenStylesheet()])
            ->willReturn([new SchematronReport($report)]);

        return $runner;
    }

    private function document(string $xml): UblXmlDocument
    {
        return UblXmlDocument::fromXml($xml);
    }

    private function invoiceXml(string $supplierScheme, string $customerScheme): string
    {
        return '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"'
            . ' xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"'
            . ' xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">'
            . '<cac:AccountingSupplierParty><cac:Party><cbc:EndpointID schemeID="'
            . $supplierScheme . '">SUPPLIER-1</cbc:EndpointID></cac:Party></cac:AccountingSupplierParty>'
            . '<cac:AccountingCustomerParty><cac:Party><cbc:EndpointID schemeID="'
            . $customerScheme . '">CUSTOMER-1</cbc:EndpointID></cac:Party></cac:AccountingCustomerParty>'
            . '</Invoice>';
    }

    private function emptyReport(): string
    {
        return '<svrl:schematron-output xmlns:svrl="http://purl.oclc.org/dsdl/svrl" />';
    }
}
