<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron;

use DreaM1\EfakturaConnector\Core\Contracts\SchematronRunnerInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationProvenanceProviderInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationStageInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\UblElementName;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolValidationIssue;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationProvenance;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationSeverity;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationStepResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationIssueLimiter;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationResources;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CodeListResources;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\ElectronicAddressSchemeCodeList;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader\UblDocumentHeaderReader;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader\UblXPath;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Runs CEN Schematron alone and applies the current Peppol EAS restriction to UBL endpoints.
 *
 * Use this stage for a CEN-only pipeline that must reject endpoint schemes outside the current Peppol EAS list.
 * It does not run the remaining Peppol Billing Schematron rules.
 */
final class CenSchematronValidationStage implements UblValidationStageInterface, UblValidationProvenanceProviderInterface
{
    /** Stable identifier reported for this standalone CEN validation stage. */
    public const ID = 'cen_schematron_standalone';
    /** CEN rule identifier for the electronic address scheme check. */
    public const RULE_CEN_ELECTRONIC_ADDRESS_SCHEME = 'BR-CL-25';
    /** Peppol rule identifier for the electronic address scheme check. */
    public const RULE_PEPPOL_ELECTRONIC_ADDRESS_SCHEME = 'PEPPOL-EN16931-CL008';
    private SchematronRunnerInterface $runner;
    private UblSchematronReportParser $reportParser;
    private UblDocumentHeaderReader $headerReader;
    private string $resourceDirectory;
    private int $maxIssues;

    /**
     * Creates a standalone CEN Schematron stage with the current Peppol EAS endpoint guard.
     *
     * @param  SchematronRunnerInterface $runner            Runner used to execute the CEN stylesheet.
     * @param  UblSchematronReportParser $reportParser      Parser for CEN SVRL reports.
     * @param  string|null               $resourceDirectory Root directory containing versioned validation resources.
     * @param  int                       $maxIssues         Maximum number of findings retained by this stage.
     * @throws InvalidInputException     when the finding limit is outside the supported range.
     */
    public function __construct(
        SchematronRunnerInterface $runner,
        UblSchematronReportParser $reportParser,
        ?string $resourceDirectory = null,
        int $maxIssues = ValidationIssueLimiter::DEFAULT_LIMIT
    ) {
        ValidationIssueLimiter::assertLimit($maxIssues);

        $this->runner            = $runner;
        $this->reportParser      = $reportParser;
        $this->headerReader      = new UblDocumentHeaderReader();
        $this->resourceDirectory = $resourceDirectory ?? ValidationResources::directory();
        $this->maxIssues         = $maxIssues;
    }

    /**
     * Returns the stable stage identifier included in validation results.
     *
     * @return string
     */
    public function id(): string
    {
        return self::ID;
    }

    /**
     * Returns the CEN Schematron and Peppol EAS rule versions used by this stage.
     *
     * @return array<string, string> Rule set identifiers mapped to their versions.
     */
    public function ruleVersions(): array
    {
        return [
            ValidationResources::CEN_RULESET_ID => ValidationResources::CEN_RULES_VERSION,
            'peppol_eas'                        => CodeListResources::ELECTRONIC_ADDRESS_SCHEMES_RELEASE,
        ];
    }

    /** @return array<string, string> */
    public function provenance(UblXmlDocument $document): array
    {
        $provenance = UblValidationProvenance::from($this->runner, $document);

        return UblValidationProvenance::withFileHashes($provenance, [
            'artifact.cen_en16931.xsl_sha256'                     => ValidationResources::cenStylesheet($this->resourceDirectory),
            ValidationResources::MANIFEST_ARTIFACT_PROVENANCE_KEY => ResourcePath::join(
                $this->resourceDirectory,
                ValidationResources::RULES_MANIFEST_FILENAME
            ),
        ]);
    }

    /**
     * Runs the CEN/EN 16931 Schematron rules and Peppol EAS endpoint check against the supplied document.
     *
     * @param  UblXmlDocument                         $document XML document to validate.
     * @return UblValidationStepResult                Findings and execution details from this stage.
     * @throws LocalValidationInfrastructureException when resources are unavailable or the report is invalid.
     *                                                {@inheritdoc}
     */
    public function validate(UblXmlDocument $document): UblValidationStepResult
    {
        $stylesheet = ValidationResources::cenStylesheet($this->resourceDirectory);

        if (! is_file($stylesheet) || ! is_readable($stylesheet)) {
            throw new LocalValidationInfrastructureException('Local CEN Schematron rules are unavailable.');
        }

        $cenElectronicAddressSchemeFailures = [];
        $parsedIssues                       = $this->reportParser->parseLimitedIssues(
            $this->runner->run($document->xml(), [$stylesheet]),
            $document->domDocument(),
            $this->maxIssues,
            static function (
                string $ruleId,
                UblValidationSeverity $severity,
                string $location
            ) use (&$cenElectronicAddressSchemeFailures): void {
                // Track CEN BR-CL-25 errors by party to avoid reporting the same EndpointID scheme failure twice.
                if ($ruleId !== self::RULE_CEN_ELECTRONIC_ADDRESS_SCHEME || ! $severity->isError()) {
                    return;
                }

                $party = self::endpointPartyForLocation($location);

                if ($party !== null) {
                    $cenElectronicAddressSchemeFailures[$party] = true;
                }
            }
        );

        $issues = $this->appendCurrentEasFindings(
            $document,
            $parsedIssues->issues(),
            $cenElectronicAddressSchemeFailures
        );

        $limitedIssues = new ValidationIssueLimiter($this->maxIssues, PeppolValidationIssue::class);

        foreach ($issues as $issue) {
            $limitedIssues->add($issue);
        }

        $limitedIssues->addOmitted($parsedIssues->omittedCount());

        return new UblValidationStepResult($limitedIssues->result());
    }

    /**
     * Adds Peppol's narrower EAS rule unless CEN already rejected the same endpoint scheme.
     *
     * @param  PeppolValidationIssue[] $issues                             CEN findings retained from the parsed stylesheet report.
     * @param  array<string, true>     $cenElectronicAddressSchemeFailures Parties with a matching CEN endpoint scheme failure.
     * @return PeppolValidationIssue[]
     */
    private function appendCurrentEasFindings(
        UblXmlDocument $document,
        array $issues,
        array $cenElectronicAddressSchemeFailures
    ): array {
        $header = $this->headerReader->read(
            new UblXPath($document->domDocument()),
            $document->kind()
        );

        $endpoints = [
            UblElementName::ACCOUNTING_SUPPLIER_PARTY => $header->supplierEndpoint(),
            UblElementName::ACCOUNTING_CUSTOMER_PARTY => $header->customerEndpoint(),
        ];

        // Check supplier and customer Party/EndpointID scheme IDs against the current Peppol EAS list.
        foreach ($endpoints as $party => $endpoint) {
            if (
                $endpoint === null
                || $endpoint->scheme() === null
                || ElectronicAddressSchemeCodeList::isCanonical($endpoint->scheme())
                || isset($cenElectronicAddressSchemeFailures[$party])
            ) {
                continue;
            }

            $issues[] = new PeppolValidationIssue(
                self::RULE_PEPPOL_ELECTRONIC_ADDRESS_SCHEME,
                'Endpoint identifier scheme is not in the current Peppol EAS code list.',
                '/' . $document->kind()
                    . '/cac:' . $party
                    . '/cac:Party/cbc:' . UblElementName::ENDPOINT_ID,
                null
            );
        }

        return $issues;
    }

    /** Returns the endpoint party named by a CEN endpoint location, if it is specific enough. */
    private static function endpointPartyForLocation(string $location): ?string
    {
        if (strpos($location, UblElementName::ENDPOINT_ID) === false) {
            return null;
        }

        foreach ([UblElementName::ACCOUNTING_SUPPLIER_PARTY, UblElementName::ACCOUNTING_CUSTOMER_PARTY] as $party) {
            if (strpos($location, $party) !== false) {
                return $party;
            }
        }

        return null;
    }
}
