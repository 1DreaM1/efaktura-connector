<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

/**
 * Peppol Billing metadata and the one rule the standalone header stage can report.
 * The header validation stage only reports BR-CO-25, which is not asserted by the bundled Schematron.
 * The value is a stable package identifier a host can map to its own wording.
 * Draft preflight identifiers live in PeppolBillingDraftRule; docs/wiki/Validation.md lists their references.
 */
final class PeppolBillingRule
{
    /** Peppol BIS Billing specification version used by the standalone header rule. */
    public const SPECIFICATION_VERSION = '3.0';
    /** Stable identifier used for Peppol Billing rule-set metadata. */
    public const RULESET_ID = 'peppol_bis_billing';

    /** Positive Invoice documents require BT-9 or BT-20 under BR-CO-25; CreditNote documents are exempt. */
    public const PAYMENT_DUE_DATE_OR_TERMS = 'payment_due_date_or_terms';
}
