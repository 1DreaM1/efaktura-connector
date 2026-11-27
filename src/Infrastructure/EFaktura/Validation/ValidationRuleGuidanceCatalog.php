<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation;

use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationGuidance;

/** Resolves stable Peppol rule IDs to documentation and curated correction hints. */
final class ValidationRuleGuidanceCatalog
{
    /** @var array<string, string> */
    private const RECOMMENDED_ACTIONS = [
        'BR-02'               => 'Provide the invoice or credit note identifier in cbc:ID.',
        'BR-03'               => 'Provide a valid issue date in cbc:IssueDate.',
        'BR-16'               => 'Add at least one invoice or credit note line.',
        'BR-CO-25'            => 'For a positive invoice amount, provide a due date or payment terms.',
        'PEPPOL-EN16931-R003' => 'Provide a buyer reference or purchase order reference.',
        'PEPPOL-EN16931-R010' => 'Provide the buyer endpoint identifier and its scheme.',
        'PEPPOL-EN16931-R020' => 'Provide the seller endpoint identifier and its scheme.',
    ];

    private function __construct()
    {
    }

    /** Returns guidance for a recognized Peppol rule identifier. */
    public static function forRule(string $ruleId): ?UblValidationGuidance
    {
        if (preg_match('/^(?:BR-[A-Z0-9-]+|PEPPOL-[A-Z0-9-]+|SK-[A-Z0-9-]+)$/D', $ruleId) !== 1) {
            return null;
        }

        $ruleGroup = strpos($ruleId, 'BR-') === 0 ? 'ubl-tc434' : 'ubl-peppol';
        $reference = sprintf(
            'https://test-docs.peppol.eu/poacc/billing/3.0/%s/rules/%s/%s/',
            ValidationResources::PEPPOL_RULES_RELEASE,
            $ruleGroup,
            rawurlencode($ruleId)
        );

        return new UblValidationGuidance($reference, self::RECOMMENDED_ACTIONS[$ruleId] ?? null);
    }
}
