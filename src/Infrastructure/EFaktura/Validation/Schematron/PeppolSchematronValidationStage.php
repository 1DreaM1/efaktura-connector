<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron;

use DreaM1\EfakturaConnector\Core\Contracts\PeppolSchematronValidationInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationProvenanceProviderInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationStageInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationProvenance;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationStepResult;

/** Exposes CEN EN 16931 and Peppol Billing Schematron findings as one validation stage. */
final class PeppolSchematronValidationStage implements UblValidationStageInterface, UblValidationProvenanceProviderInterface
{
    /** Stable identifier reported for the Peppol Schematron validation stage. */
    public const ID = 'peppol_schematron';

    private PeppolSchematronValidationInterface $validator;

    /**
     * Creates a Peppol Schematron validation stage.
     *
     * @param PeppolSchematronValidationInterface $validator Validator used to check the document.
     */
    public function __construct(PeppolSchematronValidationInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Returns the stable stage identifier used in validation results.
     *
     * @return string
     */
    public function id(): string
    {
        return self::ID;
    }

    /**
     * Returns the versions of the rule sets exposed by the configured validator.
     *
     * @return array<string, string>
     */
    public function ruleVersions(): array
    {
        return $this->validator->ruleVersions();
    }

    /** @return array<string, string> */
    public function provenance(UblXmlDocument $document): array
    {
        return UblValidationProvenance::from($this->validator, $document);
    }

    /**
     * Runs Schematron and returns its findings as one local validation stage result.
     *
     * @param  UblXmlDocument                         $document Parsed UBL document to validate with the configured Schematron rules.
     * @return UblValidationStepResult
     * @throws LocalValidationInfrastructureException when validation cannot run.
     *                                                {@inheritdoc}
     */
    public function validate(UblXmlDocument $document): UblValidationStepResult
    {
        $result = $this->validator->validateDocument($document);

        return new UblValidationStepResult($result->findings());
    }
}
