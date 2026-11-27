<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Contracts;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\PeppolBillingDraftRulesException;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentDraft;

/** Checks Peppol Billing requirements that can be checked before serializing a draft. */
interface PeppolBillingDraftRulesInterface
{
    /**
     * Returns the violated Peppol draft rules.
     *
     * @param  UblDocumentDraft $draft Draft to check before serialization.
     * @return string[]         rule identifiers in check order
     */
    public function violations(UblDocumentDraft $draft): array;

    /**
     * Throws when the draft violates a Peppol billing rule.
     *
     * @param  UblDocumentDraft                 $draft Draft to check before serialization.
     * @throws PeppolBillingDraftRulesException when one or more rules are violated
     */
    public function assertSatisfied(UblDocumentDraft $draft): void;
}
