<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schema;

use DreaM1\EfakturaConnector\Core\Contracts\UblSchemaValidationInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationProvenanceProviderInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationStageInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\SchemaValidationException;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\LimitedValidationIssues;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationProvenance;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationStepResult;

/** Adapts the exception-based XSD validator to the report-producing validation pipeline. */
final class UblSchemaValidationStage implements UblValidationStageInterface, UblValidationProvenanceProviderInterface
{
    /** Stable identifier reported for the UBL XSD validation stage. */
    public const ID = 'schema';

    private UblSchemaValidationInterface $validator;

    /**
     * Creates an XSD validation stage.
     *
     * @param UblSchemaValidationInterface $validator Validator used to check the document.
     */
    public function __construct(UblSchemaValidationInterface $validator)
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
     * Runs XSD validation and converts schema violations into a stage result.
     *
     * @param  UblXmlDocument                         $document Parsed UBL document to validate against its XSD schema.
     * @return UblValidationStepResult
     * @throws LocalValidationInfrastructureException when schema validation cannot run.
     *                                                {@inheritdoc}
     */
    public function validate(UblXmlDocument $document): UblValidationStepResult
    {
        try {
            $this->validator->validateDocument($document);
        } catch (SchemaValidationException $exception) {
            return new UblValidationStepResult(new LimitedValidationIssues(
                $exception->issues(),
                $exception->omittedCount()
            ));
        }

        return new UblValidationStepResult();
    }
}
