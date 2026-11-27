<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\UnsupportedDocumentTypeException;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\LocalUblValidationResult;

/** Runs the configured local UBL validation stages and combines their results. */
interface LocalUblValidationInterface
{
    /**
     * Runs each configured local validation stage and returns its reported findings.
     * Each stage may limit its findings; the result preserves omitted counts by stage.
     * Infrastructure failures are retained in the corresponding step result; other configured steps continue.
     * An unsupported document type is thrown.
     *
     * @param  UblXmlDocument                   $document Parsed UBL XML document to validate locally.
     * @return LocalUblValidationResult
     * @throws UnsupportedDocumentTypeException when a configured validation stage does not support the document root.
     */
    public function validateDocument(UblXmlDocument $document): LocalUblValidationResult;
}
