<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\SchematronReport;

/** Runs Schematron stylesheets against XML and returns their parsed reports. */
interface SchematronRunnerInterface
{
    /**
     * Applies every stylesheet to the same XML.
     *
     * @param  string[]                               $stylesheets Paths to the Schematron stylesheets to apply.
     * @param  string                                 $xml         XML document supplied as input to each stylesheet.
     * @return SchematronReport[]                     Reports in the same order as the supplied stylesheets.
     * @throws LocalValidationInfrastructureException when validation resources or process execution are unavailable.
     */
    public function run(string $xml, array $stylesheets): array;
}
