<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Contracts;

/** Reports the versions of standards or rule sets used by a validation component. */
interface UblValidationRuleVersionProviderInterface
{
    /**
     * Returns rule-set identifiers mapped to their version strings.
     * Return an empty array when the component has no rule-version metadata.
     *
     * @return array<string, string>
     */
    public function ruleVersions(): array;
}
