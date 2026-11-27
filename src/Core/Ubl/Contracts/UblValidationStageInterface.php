<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Contracts;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\UnsupportedDocumentTypeException;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationStepResult;

/** A report-producing stage in a local UBL validation pipeline. */
interface UblValidationStageInterface extends UblValidationRuleVersionProviderInterface
{
    /**
     * Returns a stable, unique, non-empty identifier used to expose this stage's result.
     * Identifiers must not be strings that PHP converts to integer array keys.
     *
     * @return string
     */
    public function id(): string;

    /**
     * Validates the document and returns its findings.
     *
     * Validation findings are returned as data. Infrastructure failures are captured by the pipeline
     * as a step outcome, and the pipeline continues with later stages.
     *
     * @param  UblXmlDocument                         $document Parsed UBL document to validate.
     * @return UblValidationStepResult
     * @throws UnsupportedDocumentTypeException       when the stage does not support the document root.
     * @throws LocalValidationInfrastructureException when the stage cannot run, for example missing rules or runtime.
     */
    public function validate(UblXmlDocument $document): UblValidationStepResult;
}
