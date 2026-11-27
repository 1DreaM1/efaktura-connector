<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation;

use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationStageInterface;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\LimitedValidationIssues;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolBillingRule;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolValidationIssue;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationStepResult;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Validation\PeppolSlovakBillingRules;

/** Adds warnings for corrected-invoice references and Slovak supplier/buyer postal-address fields. */
final class PeppolSlovakBillingValidationStage implements UblValidationStageInterface
{
    /** Stable identifier reported for the Slovak-specific validation stage. */
    public const ID = 'peppol_slovak_country_rules';
    /** Version identifier for the bundled Slovak-specific validation rules. */
    public const RULES_VERSION = '2026-Q4-MR';

    private PeppolSlovakBillingRules $rules;

    /**
     * Creates the stage with the default Slovak rules or the supplied rule set.
     *
     * @param PeppolSlovakBillingRules|null $rules Optional rules instance to use.
     */
    public function __construct(?PeppolSlovakBillingRules $rules = null)
    {
        $this->rules = $rules ?? new PeppolSlovakBillingRules();
    }

    /** Returns the stable stage identifier used in validation results. */
    public function id(): string
    {
        return self::ID;
    }

    /**
     * Returns the identifiers and versions of all rule sets used by this stage.
     *
     * @return array<string, string>
     */
    public function ruleVersions(): array
    {
        return [
            PeppolBillingRule::RULESET_ID => ValidationResources::PEPPOL_RULES_VERSION,
            self::ID                      => self::RULES_VERSION,
        ];
    }

    /**
     * Returns the Slovak-specific validation findings for the UBL document.
     *
     * @param  UblXmlDocument          $document XML document to validate.
     * @return UblValidationStepResult Validation findings produced by this stage.
     *                                 {@inheritdoc}
     */
    public function validate(UblXmlDocument $document): UblValidationStepResult
    {
        return new UblValidationStepResult(new LimitedValidationIssues(
            $this->rules->issues($document),
            0,
            PeppolValidationIssue::class
        ));
    }
}
