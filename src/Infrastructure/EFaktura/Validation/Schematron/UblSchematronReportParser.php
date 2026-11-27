<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron;

use DOMDocument;
use DOMElement;
use DOMXPath;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\LimitedValidationIssues;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolValidationIssue;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\SchematronReport;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationSeverity;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationIssueLimiter;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Xml\SecureXmlDocumentParser;
use DreaM1\EfakturaConnector\Support\Xml\XmlParseException;

/** @internal Converts Schematron SVRL output into connector validation issues. */
class UblSchematronReportParser
{
    /** Namespace URI used by Schematron Validation Report Language elements. */
    private const SVRL_NAMESPACE = 'http://purl.oclc.org/dsdl/svrl';

    /**
     * Converts failed assertions from the reports into issues, in ruleset order.
     * Line details are attached when the SVRL location can be mapped to an InvoiceLine or
     * CreditNoteLine in the source document.
     * @param  SchematronReport[]                     $reports Schematron reports produced by the configured stylesheets.
     * @param  DOMDocument                            $source  Source UBL document used to resolve line-level context.
     * @return PeppolValidationIssue[]                Findings in report and assertion order.
     * @throws LocalValidationInfrastructureException when a Schematron report is invalid.
     */
    public function parseIssues(array $reports, DOMDocument $source): array
    {
        return $this->parse($reports, $source, null)->issues();
    }

    /**
     * Parses every failed assertion while retaining only the configured number of issue objects.
     *
     * @param  SchematronReport[]                                           $reports       Schematron reports produced by the configured stylesheets.
     * @param  DOMDocument                                                  $source        Source UBL document used to resolve line-level context.
     * @param  int                                                          $limit         Maximum number of issue objects to retain.
     * @param  (callable(string, UblValidationSeverity, string): void)|null $issueObserver Receives rule ID, severity and location before retention.
     * @return LimitedValidationIssues<PeppolValidationIssue>               Retained issues and the number omitted.
     * @throws InvalidInputException                                        when the issue limit is outside the supported range
     * @throws LocalValidationInfrastructureException                       when a Schematron report is invalid.
     */
    public function parseLimitedIssues(
        array $reports,
        DOMDocument $source,
        int $limit,
        ?callable $issueObserver = null
    ): LimitedValidationIssues {
        return $this->parse($reports, $source, $limit, $issueObserver);
    }

    /**
     * Parses all findings while optionally avoiding construction of issues beyond a limit.
     *
     * @param  SchematronReport[]                             $reports Reports in stylesheet order.
     * @return LimitedValidationIssues<PeppolValidationIssue>
     */
    private function parse(
        array $reports,
        DOMDocument $source,
        ?int $limit,
        ?callable $issueObserver = null
    ): LimitedValidationIssues {
        $issueFactory = new UblSchematronIssueFactory($source);
        $collector    = new ValidationIssueLimiter($limit, PeppolValidationIssue::class);

        // Reports arrive in stylesheet order; collect only failed assertions from each SVRL document.
        foreach ($reports as $report) {
            $xpath = new DOMXPath($this->loadReport($report->xml()));
            $xpath->registerNamespace('svrl', self::SVRL_NAMESPACE);
            $nodes = $xpath->query('//svrl:failed-assert');

            foreach ($nodes ?: [] as $node) {
                if (! $node instanceof DOMElement) {
                    throw new LocalValidationInfrastructureException(
                        'Local Schematron validation returned an invalid report.'
                    );
                }

                $message = trim((string) $xpath->evaluate('string(svrl:text)', $node));

                if ($message === '') {
                    throw new LocalValidationInfrastructureException(
                        'Local Schematron validation returned an invalid report.'
                    );
                }

                $ruleId = trim($node->getAttribute('id'));
                $flag   = strtolower(trim($node->getAttribute('flag')));

                // SVRL warning/info flags stay non-fatal; unknown or absent flags default to error.
                if ($flag === UblValidationSeverity::WARNING) {
                    $severity = UblValidationSeverity::warning();
                } elseif ($flag === UblValidationSeverity::INFO) {
                    $severity = UblValidationSeverity::info();
                } else {
                    $severity = UblValidationSeverity::error();
                }

                $location = trim($node->getAttribute('location'));

                if ($issueObserver !== null) {
                    $issueObserver($ruleId, $severity, $location);
                }

                $collector->addLazy(function () use ($issueFactory, $ruleId, $message, $location, $severity): PeppolValidationIssue {
                    return $issueFactory->create($ruleId, $message, $location, $severity);
                });
            }
        }

        return $collector->result();
    }

    /** Parses the runner output securely and rejects documents outside the SVRL format. */
    private function loadReport(string $report): DOMDocument
    {
        try {
            // Use the shared secure parser because reports are XML returned by an external process.
            $document = SecureXmlDocumentParser::parse($report);
        } catch (XmlParseException $exception) {
            throw new LocalValidationInfrastructureException(
                'Local Schematron validation returned an invalid report.',
                $exception
            );
        }

        if ($document === null || ! $this->isSchematronReport($document)) {
            throw new LocalValidationInfrastructureException('Local Schematron validation returned an invalid report.');
        }

        return $document;
    }

    /** Checks that the report root is a Schematron Validation Report Language element. */
    private function isSchematronReport(DOMDocument $document): bool
    {
        $root = $document->documentElement;

        return $root !== null
            && $root->localName === 'schematron-output'
            && $root->namespaceURI === self::SVRL_NAMESPACE;
    }
}
