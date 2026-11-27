<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationRuleVersionProviderInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolSchematronValidationResult;

/** Validates a loaded UBL document against the package's Peppol Schematron rules. */
interface PeppolSchematronValidationInterface extends UblValidationRuleVersionProviderInterface
{
    /**
     * Validates the document against Peppol Schematron rules.
     *
     * @param  UblXmlDocument                         $document Parsed UBL XML document to validate.
     * @return PeppolSchematronValidationResult       Findings and omitted-finding count from the report.
     * @throws LocalValidationInfrastructureException when validation cannot run
     */
    public function validateDocument(UblXmlDocument $document): PeppolSchematronValidationResult;
}
