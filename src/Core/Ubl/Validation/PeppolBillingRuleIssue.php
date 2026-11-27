<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

use DreaM1\EfakturaConnector\Support\NonBlankString;

/** One violation reported by a lightweight Peppol Billing check. */
final class PeppolBillingRuleIssue implements UblValidationIssueInterface
{
    private string $rule;

    /**
     * Creates an issue for a violated Peppol Billing rule.
     *
     * @param string $rule Validation rule to apply.
     */
    public function __construct(string $rule)
    {
        NonBlankString::assertNotBlank($rule, 'Peppol Billing rule identifier must not be empty.');

        $this->rule = $rule;
    }

    /**
     * Returns the violated rule identifier.
     *
     * @return string
     */
    public function rule(): string
    {
        return $this->rule;
    }

    /**
     * Returns the severity of a violated Peppol Billing rule.
     *
     * @return UblValidationSeverity
     */
    public function severity(): UblValidationSeverity
    {
        return UblValidationSeverity::error();
    }

    /**
     * Returns the issue in a stable serialization shape.
     *
     * @return array{rule: string, severity: string}
     */
    public function toArray(): array
    {
        return [
            'rule'     => $this->rule,
            'severity' => $this->severity()->value(),
        ];
    }

    /**
     * Converts this issue to the common reporting model.
     *
     * @param  string               $stageId Identifier of the validation stage reporting findings.
     * @return UblValidationFinding
     */
    public function toFinding(string $stageId): UblValidationFinding
    {
        $guidance = $this->rule === PeppolBillingRule::PAYMENT_DUE_DATE_OR_TERMS
            ? new UblValidationGuidance(
                null,
                'For a positive invoice amount, provide a due date or payment terms.'
            )
            : null;

        return new UblValidationFinding(
            $stageId,
            $this,
            $this->rule,
            'Peppol Billing rule is not satisfied: ' . $this->rule . '.',
            new UblValidationLocation(),
            [],
            $this->severity(),
            $guidance
        );
    }
}
