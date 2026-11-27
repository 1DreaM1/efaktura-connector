<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation;

use DreaM1\EfakturaConnector\Core\Contracts\LocalUblValidationInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationStageInterface;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\LocalUblValidationResult;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Runs configured local validation stages and combines their findings. */
final class LocalUblValidator implements LocalUblValidationInterface
{
    private UblValidationPipeline $pipeline;

    /**
     * Creates a validator from an ordered list of report-producing stages.
     *
     * @param  iterable<UblValidationStageInterface> $stages Validation stages to run in sequence.
     * @throws InvalidInputException                 when a stage is invalid or the list is empty.
     */
    public function __construct(iterable $stages)
    {
        $this->pipeline = new UblValidationPipeline($stages);
    }

    /**
     * Validates the supplied UBL document.
     *
     * @param  UblXmlDocument           $document Parsed UBL document to validate locally.
     * @return LocalUblValidationResult
     *                                  {@inheritdoc}
     */
    public function validateDocument(UblXmlDocument $document): LocalUblValidationResult
    {
        // Keep per-stage results and findings together when producing the public validation result.
        return LocalUblValidationResult::fromStepResults($this->pipeline->validate($document));
    }
}
