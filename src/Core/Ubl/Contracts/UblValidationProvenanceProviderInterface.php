<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Contracts;

use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;

/** Provides non-sensitive details identifying the rules and tools used for validation. */
interface UblValidationProvenanceProviderInterface
{
    /**
     * Returns artifact fingerprints and runtime versions for the supplied document.
     *
     * Keys should be stable identifiers and values must not contain document data or absolute paths.
     *
     * @param  UblXmlDocument        $document Validated document associated with the reported artifacts.
     * @return array<string, string>
     */
    public function provenance(UblXmlDocument $document): array;
}
