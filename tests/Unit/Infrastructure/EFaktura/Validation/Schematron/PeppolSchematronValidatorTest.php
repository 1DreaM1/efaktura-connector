<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Validation\Schematron;

use DOMDocument;
use DOMXPath;
use DreaM1\EfakturaConnector\Core\Contracts\SchematronRunnerInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolSchematronValidationResult;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\SchematronReport;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\PeppolSchematronValidator;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\SaxonSchematronRunner;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\UblSchematronReportParser;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationResources;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Tests\Support\RequiresSaxonRuntime;
use PHPUnit\Framework\TestCase;

/** Tests Peppol Schematron validation execution and results. */
class PeppolSchematronValidatorTest extends TestCase
{
    use RequiresSaxonRuntime;

    public function testUsesTheOfficialPeppol2026Q4MrRuleSet(): void
    {
        $stylesheets      = ValidationResources::schematronStylesheets();
        $peppolStylesheet = $stylesheets[1];
        $sourcePath       = ResourcePath::join(dirname($peppolStylesheet), 'PEPPOL-EN16931-UBL.sch');

        $this->assertSame('2026-Q4-MR', ValidationResources::PEPPOL_RULES_RELEASE);
        $this->assertSame('peppol-3.0.22-2026-Q4-MR', basename(dirname($peppolStylesheet)));
        $this->assertFileExists($peppolStylesheet);
        $source = new DOMDocument();
        $this->assertTrue($source->load($sourcePath));

        $xpath = new DOMXPath($source);
        $xpath->registerNamespace('sch', 'http://purl.oclc.org/dsdl/schematron');

        foreach (['R054', 'R055', 'R056-1', 'R056-2', 'R057'] as $rule) {
            $assert = $xpath->query("//sch:assert[@id='PEPPOL-COMMON-$rule']")->item(0);
            $this->assertNotNull($assert, 'Missing Q4 rule PEPPOL-COMMON-' . $rule);
            $this->assertSame('fatal', $assert->getAttribute('flag'));
        }

        foreach (
            [
                'PEPPOL-COMMON-R058',
                'PEPPOL-COMMON-R059',
                'PEPPOL-COMMON-R062',
                'PEPPOL-EN16931-R140',
                'PEPPOL-EN16931-R276',
                'SK-R-001',
                'SK-R-007',
            ] as $ruleId
        ) {
            $this->assertSame(
                1,
                $xpath->query("//sch:assert[@id='$ruleId']")->length,
                'Missing Q4 rule ' . $ruleId
            );
        }

        $this->assertSame(0, $xpath->query("//sch:assert[@id='GR-R-001-5']")->length);
    }

    public function testReturnsFindingsFromBothRuleSetsAndTreatsWarningsAsNonBlocking(): void
    {
        $xml    = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" />';
        $runner = $this->createMock(SchematronRunnerInterface::class);
        $runner->expects($this->once())
            ->method('run')
            ->willReturnCallback(function ($passedXml, array $stylesheets) use ($xml) {
                $this->assertSame($xml, $passedXml);
                $this->assertSame(
                    ['CEN-EN16931-UBL', 'PEPPOL-EN16931-UBL'],
                    array_map(
                        static fn (string $stylesheet): string => basename($stylesheet, '.xsl'),
                        $stylesheets
                    )
                );

                return array_map(
                    fn (string $stylesheet): SchematronReport => new SchematronReport(
                        $this->report(basename($stylesheet, '.xsl') . '-RULE', 'warning')
                    ),
                    $stylesheets
                );
            });

        $document  = UblXmlDocument::fromXml($xml);
        $validator = new PeppolSchematronValidator(new UblSchematronReportParser(), $runner);
        $this->assertSame([
            'cen_en16931'        => '1.3.17',
            'peppol_bis_billing' => '3.0.22',
        ], $validator->ruleVersions());

        $result = $validator->validateDocument($document);

        $this->assertInstanceOf(PeppolSchematronValidationResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertFalse($result->hasErrors());
        $this->assertSame([
            'Synthetic validation error.',
            'Synthetic validation error.',
        ], array_column(array_map(
            static fn ($issue): array => $issue->toArray(),
            $result->issues()
        ), 'message'));
        $this->assertSame(['warning', 'warning'], array_map(
            static fn ($issue): string => $issue->severity()->value(),
            $result->issues()
        ));
    }

    public function testRunsTheBundledSlovakRulesAgainstDomesticInvoiceXml(): void
    {
        $this->requireSaxonRuntime();

        $result    = $this->validateWithBundledRules($this->slovakInvoice('SK'));
        $allIssues = $result->issues();
        $issues    = array_values(array_filter($allIssues, static function ($issue): bool {
            return strpos($issue->toArray()['rule_id'], 'SK-R-') === 0;
        }));
        $allRuleIds = array_map(static function ($issue): string {
            return $issue->toArray()['rule_id'];
        }, $allIssues);

        $this->assertContains('BR-01', $allRuleIds);
        $this->assertContains('PEPPOL-EN16931-R001', $allRuleIds);
        $this->assertSame([
            'SK-R-002',
            'SK-R-003',
            'SK-R-004',
            'SK-R-005',
            'SK-R-006',
            'SK-R-007',
            'SK-R-001',
        ], array_map(static function ($issue): string {
            return $issue->toArray()['rule_id'];
        }, $issues), 'Actual findings: ' . implode(', ', $allRuleIds));

        foreach ($issues as $issue) {
            $this->assertSame('warning', $issue->severity()->value());
        }
    }

    public function testBundledSlovakRulesDoNotApplyToCrossBorderInvoiceXml(): void
    {
        $this->requireSaxonRuntime();

        $result           = $this->validateWithBundledRules($this->slovakInvoice('CZ'));
        $slovakRuleIssues = array_filter($result->issues(), static function ($issue): bool {
            return strpos($issue->toArray()['rule_id'], 'SK-R-') === 0;
        });

        $this->assertSame([], array_values($slovakRuleIssues));
    }

    public function testAppliesTheIssueLimitToTheSchematronCheck(): void
    {
        $xml    = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" />';
        $runner = $this->createMock(SchematronRunnerInterface::class);
        $runner->expects($this->once())
            ->method('run')
            ->willReturn([
                new SchematronReport($this->report('CEN-RULE')),
                new SchematronReport($this->report('PEPPOL-RULE')),
            ]);
        $validator = new PeppolSchematronValidator(
            new UblSchematronReportParser(),
            $runner,
            null,
            1
        );

        $result = $validator->validateDocument(UblXmlDocument::fromXml($xml));

        $this->assertCount(1, $result->issues());
        $this->assertSame(1, $result->omittedCount());
    }

    public function testReportsMissingSchematronResourcesBeforeStartingTheRunner(): void
    {
        $xml    = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" />';
        $runner = $this->createMock(SchematronRunnerInterface::class);
        $runner->expects($this->never())->method('run');
        $resourceDirectory = ResourcePath::join(sys_get_temp_dir(), 'efaktura-missing-rules-' . uniqid());
        $validator         = new PeppolSchematronValidator(
            new UblSchematronReportParser(),
            $runner,
            $resourceDirectory
        );

        $this->expectException(LocalValidationInfrastructureException::class);
        $this->expectExceptionMessage('Schematron rules are unavailable');
        $validator->validateDocument(UblXmlDocument::fromXml($xml));
    }

    private function validateWithBundledRules(string $xml): PeppolSchematronValidationResult
    {
        $document  = UblXmlDocument::fromXml($xml);
        $runner    = new SaxonSchematronRunner(ValidationResources::directory(), sys_get_temp_dir());
        $validator = new PeppolSchematronValidator(
            new UblSchematronReportParser(),
            $runner,
            null,
            100
        );

        return $validator->validateDocument($document);
    }

    private function slovakInvoice(string $customerPostalCountry): string
    {
        return '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"'
            . ' xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"'
            . ' xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">'
            . '<cbc:InvoiceTypeCode>384</cbc:InvoiceTypeCode>'
            . '<cac:AccountingSupplierParty><cac:Party><cac:PostalAddress><cac:Country>'
            . '<cbc:IdentificationCode>SK</cbc:IdentificationCode></cac:Country></cac:PostalAddress></cac:Party>'
            . '</cac:AccountingSupplierParty>'
            . '<cac:AccountingCustomerParty><cac:Party><cac:PostalAddress><cac:Country>'
            . '<cbc:IdentificationCode>' . $customerPostalCountry . '</cbc:IdentificationCode>'
            . '</cac:Country></cac:PostalAddress></cac:Party></cac:AccountingCustomerParty>'
            . '</Invoice>';
    }

    private function report(string $ruleId, string $flag = 'fatal'): string
    {
        return '<svrl:schematron-output xmlns:svrl="http://purl.oclc.org/dsdl/svrl">'
            . '<svrl:failed-assert id="' . $ruleId . '" flag="' . $flag . '" location="/Invoice/cbc:ID">'
            . '<svrl:text>Synthetic validation error.</svrl:text>'
            . '</svrl:failed-assert></svrl:schematron-output>';
    }
}
