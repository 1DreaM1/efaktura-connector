<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationRuleVersionProviderInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\SchemaValidationException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\UnsupportedDocumentTypeException;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;

/** Validates a loaded UBL document against the bundled UBL XSD schema. */
interface UblSchemaValidationInterface extends UblValidationRuleVersionProviderInterface
{
    /**
     * Validates the document against the UBL XSD schema.
     *
     * @param  UblXmlDocument                         $document Parsed UBL XML document to validate.
     * @return void
     * @throws SchemaValidationException              when the XML violates the schema
     * @throws LocalValidationInfrastructureException when validation cannot run
     * @throws UnsupportedDocumentTypeException       when the document root is not a supported UBL type
     */
    public function validateDocument(UblXmlDocument $document): void;
}
