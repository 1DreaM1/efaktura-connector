<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation;

use DreaM1\EfakturaConnector\Core\Ubl\Contracts\PeppolBillingRulesInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationStageInterface;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\LimitedValidationIssues;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolBillingRuleIssue;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationStepResult;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader\UblDocumentHeaderReader;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader\UblXPath;

/** Adds BR-CO-25 to the pipeline: a positive payable Invoice needs DueDate or PaymentTerms. */
final class PeppolBillingHeaderValidationStage implements UblValidationStageInterface
{
    /** Stable identifier reported for the standalone BR-CO-25 header stage. */
    public const ID = 'peppol_billing_header';

    private PeppolBillingRulesInterface $rules;
    private UblDocumentHeaderReader $headerReader;

    /**
     * Creates the Peppol Billing header validation stage.
     *
     * @param PeppolBillingRulesInterface $rules Validation rules to execute.
     */
    public function __construct(PeppolBillingRulesInterface $rules)
    {
        $this->rules        = $rules;
        $this->headerReader = new UblDocumentHeaderReader();
    }

    /**
     * Returns the stable stage identifier used in validation results.
     *
     * @return string
     */
    public function id(): string
    {
        return self::ID;
    }

    /**
     * Returns the versions of the rule sets exposed by the configured rule service.
     *
     * @return array<string, string>
     */
    public function ruleVersions(): array
    {
        return $this->rules->ruleVersions();
    }

    /**
     * Runs the header rules and returns each violation as an error finding.
     *
     * @param  UblXmlDocument          $document Parsed UBL document whose header is checked against Peppol Billing rules.
     * @return UblValidationStepResult
     *                                 {@inheritdoc}
     */
    public function validate(UblXmlDocument $document): UblValidationStepResult
    {
        $header = $this->headerReader->read(
            new UblXPath($document->domDocument()),
            $document->kind()
        );

        $issues = [];

        foreach ($this->rules->violations($header) as $rule) {
            $issues[] = new PeppolBillingRuleIssue($rule);
        }

        return new UblValidationStepResult(
            new LimitedValidationIssues(
                $issues,
                0,
                PeppolBillingRuleIssue::class
            )
        );
    }
}
