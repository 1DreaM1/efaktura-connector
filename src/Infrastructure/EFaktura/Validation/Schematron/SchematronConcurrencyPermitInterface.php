<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron;

/**
 * A held slot that limits concurrent local Schematron validations.
 *
 * @api
 */
interface SchematronConcurrencyPermitInterface
{
    /** Releases the reserved validation slot. */
    public function release(): void;
}
