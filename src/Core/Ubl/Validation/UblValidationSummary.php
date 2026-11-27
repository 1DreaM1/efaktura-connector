<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use Generator;

/** Aggregate counts for the retained findings and omitted findings in a local UBL result. */
final class UblValidationSummary
{
    private int $totalFindings;
    /** @var array{error: int, warning: int, info: int} */
    private array $countsBySeverity;
    /** @var array<string, array{error: int, warning: int, info: int, omitted_count: int}> */
    private array $countsByStep;
    private int $omittedCount;

    /**
     * @param int                                                                           $totalFindings    Total number of findings before applying the result limit.
     * @param array{error: int, warning: int, info: int}                                    $countsBySeverity Finding counts grouped by severity.
     * @param array<string, array{error: int, warning: int, info: int, omitted_count: int}> $countsByStep     Finding counts grouped by validation step.
     * @param int                                                                           $omittedCount     Number of findings omitted because of the configured result limit.
     */
    private function __construct(
        int $totalFindings,
        array $countsBySeverity,
        array $countsByStep,
        int $omittedCount
    ) {
        $this->totalFindings    = $totalFindings;
        $this->countsBySeverity = $countsBySeverity;
        $this->countsByStep     = $countsByStep;
        $this->omittedCount     = $omittedCount;
    }

    /**
     * Creates the aggregate directly from stage reports without constructing normalized finding copies.
     *
     * @param  array<string, UblValidationStepResult> $stepResults Results returned by the individual validation steps.
     * @return self
     */
    public static function fromStepResults(array $stepResults): self
    {
        return self::fromFindingRows($stepResults, self::rowsFromStepResults($stepResults));
    }

    /**
     * Returns the total number of retained findings.
     *
     * @return int
     */
    public function totalFindings(): int
    {
        return $this->totalFindings;
    }

    /**
     * Returns retained finding counts by severity.
     *
     * @return array{error: int, warning: int, info: int}
     */
    public function countsBySeverity(): array
    {
        return $this->countsBySeverity;
    }

    /**
     * Returns retained finding counts and omitted counts by validation step.
     *
     * @return array<string, array{error: int, warning: int, info: int, omitted_count: int}>
     */
    public function countsByStep(): array
    {
        return $this->countsByStep;
    }

    /**
     * Returns the total number of findings omitted by all steps.
     *
     * @return int
     */
    public function omittedCount(): int
    {
        return $this->omittedCount;
    }

    /**
     * Returns the summary in a serialization-friendly shape.
     *
     * @return array{total_findings: int, by_severity: array{error: int, warning: int, info: int}, by_step: array<string, array{error: int, warning: int, info: int, omitted_count: int}>, omitted_count: int}
     */
    public function toArray(): array
    {
        return [
            'total_findings' => $this->totalFindings,
            'by_severity'    => $this->countsBySeverity,
            'by_step'        => $this->countsByStep,
            'omitted_count'  => $this->omittedCount,
        ];
    }

    /**
     * Builds the aggregate from compact stage/severity pairs without normalizing or retaining new findings.
     *
     * @param array<string, UblValidationStepResult> $stepResults Results carrying omitted finding counts.
     * @param iterable<array{string, string}>        $findingRows Retained finding stage identifiers and severities.
     */
    private static function fromFindingRows(array $stepResults, iterable $findingRows): self
    {
        $countsBySeverity                   = self::emptySeverityCounts();
        [$countsByStep, $totalOmittedCount] = self::stepCounts($stepResults);
        $totalFindings                      = 0;

        foreach ($findingRows as [$stepId, $severity]) {
            self::countFinding($countsBySeverity, $countsByStep, $stepId, $severity);
            $totalFindings++;
        }

        return new self($totalFindings, $countsBySeverity, $countsByStep, $totalOmittedCount);
    }

    /** @param array<string, UblValidationStepResult> $stepResults */
    private static function rowsFromStepResults(array $stepResults): Generator
    {
        foreach ($stepResults as $stepId => $stepResult) {
            foreach ($stepResult->issues() as $issue) {
                yield [$stepId, $issue->severity()->value()];
            }
        }
    }

    /**
     * @param  array<string, UblValidationStepResult>                                                    $stepResults
     * @return array{array<string, array{error: int, warning: int, info: int, omitted_count: int}>, int}
     */
    private static function stepCounts(array $stepResults): array
    {
        $countsByStep      = [];
        $totalOmittedCount = 0;

        foreach ($stepResults as $stepId => $stepResult) {
            if (! is_string($stepId) || ! NonBlankString::isNotBlank($stepId)) {
                throw new InvalidInputException('Validation summary step identifiers must not be empty strings.');
            }

            if (! $stepResult instanceof UblValidationStepResult) {
                throw new InvalidInputException('Validation summary steps must be UblValidationStepResult instances.');
            }

            $countsByStep[$stepId] = self::emptyStepCounts($stepResult->omittedCount());
            $totalOmittedCount += $stepResult->omittedCount();
        }

        return [$countsByStep, $totalOmittedCount];
    }

    /**
     * @param array{error: int, warning: int, info: int}                                    $countsBySeverity
     * @param array<string, array{error: int, warning: int, info: int, omitted_count: int}> $countsByStep
     */
    private static function countFinding(
        array & $countsBySeverity,
        array & $countsByStep,
        string $stepId,
        string $severity
    ): void {
        if (! isset($countsBySeverity[$severity])) {
            throw new InvalidInputException('Validation summary finding severity is not supported.');
        }

        if (! isset($countsByStep[$stepId])) {
            $countsByStep[$stepId] = self::emptyStepCounts();
        }

        $countsBySeverity[$severity]++;
        $countsByStep[$stepId][$severity]++;
    }

    /** @return array{error: int, warning: int, info: int} */
    private static function emptySeverityCounts(): array
    {
        return [
            UblValidationSeverity::ERROR   => 0,
            UblValidationSeverity::WARNING => 0,
            UblValidationSeverity::INFO    => 0,
        ];
    }

    /** @return array{error: int, warning: int, info: int, omitted_count: int} */
    private static function emptyStepCounts(int $omittedCount = 0): array
    {
        return [
            UblValidationSeverity::ERROR   => 0,
            UblValidationSeverity::WARNING => 0,
            UblValidationSeverity::INFO    => 0,
            'omitted_count'                => $omittedCount,
        ];
    }
}
