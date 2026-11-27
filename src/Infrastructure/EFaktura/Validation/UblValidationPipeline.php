<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation;

use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationStageInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationProvenance;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationStepMetadata;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationStepResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\CenSchematronValidationStage;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\PeppolSchematronValidationStage;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;

/** Runs report-producing local UBL validation stages in their configured order. */
final class UblValidationPipeline
{
    /** Number of nanoseconds in one millisecond, used to report stage durations. */
    private const NANOSECONDS_PER_MILLISECOND = 1000000;

    /** @var array<string, UblValidationStageInterface> */
    private array $stages;

    /**
     * Creates a pipeline with uniquely identified validation stages.
     *
     * @param  iterable<UblValidationStageInterface> $stages Validation stages to run in sequence.
     * @throws InvalidInputException                 when the stages are invalid, the list is empty, or the CEN-only
     *                                               and full Peppol Schematron stages are combined
     */
    public function __construct(iterable $stages)
    {
        $stagesById = [];

        foreach ($stages as $stage) {
            if (! $stage instanceof UblValidationStageInterface) {
                throw new InvalidInputException(
                    'Validation pipeline stages must implement UblValidationStageInterface.'
                );
            }

            $id = $stage->id();
            NonBlankString::assertNotBlank($id, 'Validation pipeline stage identifiers must not be empty.');

            if ((string) (int) $id === $id) {
                throw new InvalidInputException(
                    'Validation pipeline stage identifiers must not be strings that PHP converts '
                    . 'to integer array keys.'
                );
            }

            if (isset($stagesById[$id])) {
                throw new InvalidInputException('Validation pipeline stage identifiers must be unique.');
            }

            $stagesById[$id] = $stage;
        }

        // PeppolSchematronValidationStage already runs the CEN rules, so adding the CEN-only stage would repeat them.
        if (isset($stagesById[CenSchematronValidationStage::ID], $stagesById[PeppolSchematronValidationStage::ID])) {
            throw new InvalidInputException(
                'Validation pipeline must not combine CenSchematronValidationStage with '
                . 'PeppolSchematronValidationStage because the latter already runs the CEN rules.'
            );
        }

        if ($stagesById === []) {
            throw new InvalidInputException('Validation pipeline must contain at least one stage.');
        }

        $this->stages = $stagesById;
    }

    /**
     * Runs all configured stages and returns their reports keyed by stage identifier.
     *
     * @param  UblXmlDocument                         $document Parsed UBL document to validate through the configured stages.
     * @return array<string, UblValidationStepResult>
     */
    public function validate(UblXmlDocument $document): array
    {
        $results = [];

        foreach ($this->stages as $stageId => $stage) {
            // Time each stage independently so one slow check does not hide where validation spent its time.
            $startedAt = hrtime(true);
            try {
                $stepResult = $stage->validate($document);
            } catch (LocalValidationInfrastructureException $exception) {
                // Keep the failure attached to this step and continue collecting independent stage results.
                $stepResult = UblValidationStepResult::infrastructureFailure($exception->getMessage());
            }

            $durationMilliseconds = (hrtime(true) - $startedAt) / self::NANOSECONDS_PER_MILLISECOND;
            $provenance           = UblValidationProvenance::from($stage, $document);

            $metadata = new UblValidationStepMetadata(
                $durationMilliseconds,
                $stepResult->status(),
                $stage->ruleVersions(),
                $provenance
            );

            // Preserve stage order and attach timing, status, and rule versions to that stage's result.
            $results[$stageId] = $stepResult->withMetadata($metadata);
        }

        return $results;
    }
}
