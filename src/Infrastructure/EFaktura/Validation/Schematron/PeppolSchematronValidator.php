<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron;

use DreaM1\EfakturaConnector\Core\Contracts\PeppolSchematronValidationInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SchematronRunnerInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationProvenanceProviderInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\LimitedValidationIssues;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolBillingRule;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolSchematronValidationResult;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolValidationIssue;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationProvenance;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationIssueLimiter;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationResources;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Checks UBL against bundled CEN EN 16931 and Peppol Billing Schematron rules. */
class PeppolSchematronValidator implements PeppolSchematronValidationInterface, UblValidationProvenanceProviderInterface
{
    private UblSchematronReportParser $reportParser;
    private SchematronRunnerInterface $runner;
    private string $resourceDirectory;
    private int $maxIssues;

    /**
     * @param  UblSchematronReportParser $reportParser      Parser used to convert Schematron reports into findings.
     * @param  SchematronRunnerInterface $runner            Schematron runner used to execute the stylesheets.
     * @param  string|null               $resourceDirectory Directory containing validation resources.
     * @param  int                       $maxIssues         Maximum number of validation findings retained.
     * @throws InvalidInputException     when the finding limit is outside the supported range.
     */
    public function __construct(
        UblSchematronReportParser $reportParser,
        SchematronRunnerInterface $runner,
        ?string $resourceDirectory = null,
        int $maxIssues = ValidationIssueLimiter::DEFAULT_LIMIT
    ) {
        ValidationIssueLimiter::assertLimit($maxIssues);

        $this->reportParser      = $reportParser;
        $this->runner            = $runner;
        $this->resourceDirectory = $resourceDirectory ?? ValidationResources::directory();
        $this->maxIssues         = $maxIssues;
    }

    /**
     * Validates the supplied UBL document.
     *
     * @param  UblXmlDocument                         $document Parsed UBL document to validate with the bundled Peppol rules.
     * @return PeppolSchematronValidationResult
     * @throws LocalValidationInfrastructureException when validation resources or process execution are unavailable.
     */
    public function validateDocument(UblXmlDocument $document): PeppolSchematronValidationResult
    {
        $limitedIssues = $this->issues($document);

        return new PeppolSchematronValidationResult($limitedIssues);
    }

    /**
     * Returns the CEN and Peppol ruleset versions used by this validator.
     *
     * @return array<string, string>
     */
    public function ruleVersions(): array
    {
        return [
            ValidationResources::CEN_RULESET_ID => ValidationResources::CEN_RULES_VERSION,
            PeppolBillingRule::RULESET_ID       => ValidationResources::PEPPOL_RULES_VERSION,
        ];
    }

    /** @return array<string, string> */
    public function provenance(UblXmlDocument $document): array
    {
        $provenance  = UblValidationProvenance::from($this->runner, $document);
        $rulesets    = [ValidationResources::CEN_RULESET_ID, PeppolBillingRule::RULESET_ID];
        $stylesheets = ValidationResources::schematronStylesheets($this->resourceDirectory);
        $artifacts   = [];

        foreach ($stylesheets as $index => $stylesheet) {
            if (isset($rulesets[$index])) {
                $artifacts['artifact.' . $rulesets[$index] . '.xsl_sha256'] = $stylesheet;
            }
        }

        $artifacts[ValidationResources::MANIFEST_ARTIFACT_PROVENANCE_KEY] = ResourcePath::join(
            $this->resourceDirectory,
            ValidationResources::RULES_MANIFEST_FILENAME
        );

        return UblValidationProvenance::withFileHashes($provenance, $artifacts);
    }

    /** @return LimitedValidationIssues<PeppolValidationIssue> */
    private function issues(UblXmlDocument $document): LimitedValidationIssues
    {
        $stylesheets = ValidationResources::schematronStylesheets($this->resourceDirectory);

        // Treat missing or unreadable bundled rules as an infrastructure problem, not a document violation.
        foreach ($stylesheets as $stylesheet) {
            if (! is_file($stylesheet) || ! is_readable($stylesheet)) {
                throw new LocalValidationInfrastructureException('Local Peppol Schematron rules are unavailable.');
            }
        }

        // Run every CEN/Peppol rule set against the same XML, then map its failed assertions back to the source DOM.
        $reports = $this->runner->run($document->xml(), $stylesheets);

        return $this->reportParser->parseLimitedIssues($reports, $document->domDocument(), $this->maxIssues);
    }
}
