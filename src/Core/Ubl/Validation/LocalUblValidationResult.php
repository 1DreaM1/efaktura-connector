<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;

/** Result from a configured set of local UBL validation stages. */
final class LocalUblValidationResult
{
    /** @var array<string, UblValidationStepResult> */
    private array $stepResults;
    private UblValidationSummary $summary;

    /**
     * Creates a result from generic validation step reports.
     *
     * @param array<string, UblValidationStepResult> $stepResults Results returned by the individual validation steps.
     */
    private function __construct(array $stepResults)
    {
        if ($stepResults === []) {
            throw new InvalidInputException('Validation result must contain at least one step result.');
        }

        $this->stepResults = [];

        // Preserve each stage report; normalized findings are created only when requested.
        foreach ($stepResults as $stepId => $stepResult) {
            if (! is_string($stepId) || ! NonBlankString::isNotBlank($stepId)) {
                throw new InvalidInputException('Validation result step identifiers must not be empty strings.');
            }

            if (! $stepResult instanceof UblValidationStepResult) {
                throw new InvalidInputException('Validation result steps must be UblValidationStepResult instances.');
            }

            $this->stepResults[$stepId] = $stepResult;
        }

        $this->summary = UblValidationSummary::fromStepResults($this->stepResults);
    }

    /**
     * Creates a result from the reports produced by the configured validation steps.
     *
     * @param  array<string, UblValidationStepResult> $stepResults Results returned by the individual validation steps.
     * @return self
     * @throws InvalidInputException                  when a report is invalid or no reports are supplied
     */
    public static function fromStepResults(array $stepResults): self
    {
        return new self($stepResults);
    }

    /** Whether every configured validation step passed without errors, omissions, or infrastructure failures. */
    public function isValid(): bool
    {
        foreach ($this->stepResults as $stepResult) {
            if (! $stepResult->isValid()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether every configured step completed without omitted findings or infrastructure failures.
     *
     * @return bool
     */
    public function isComplete(): bool
    {
        foreach ($this->stepResults as $stepResult) {
            $status = $stepResult->status()->value();

            if (
                $status === UblValidationStepStatus::INCOMPLETE
                || $status === UblValidationStepStatus::INFRASTRUCTURE_ERROR
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether at least one validation step could not run because of an infrastructure problem.
     *
     * @return bool
     */
    public function hasInfrastructureFailures(): bool
    {
        foreach ($this->stepResults as $stepResult) {
            if ($stepResult->status()->value() === UblValidationStepStatus::INFRASTRUCTURE_ERROR) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> Technical failures keyed by validation step identifier. */
    public function infrastructureFailures(): array
    {
        $failures = [];

        foreach ($this->stepResults as $stepId => $stepResult) {
            $message = $stepResult->infrastructureError();

            if ($message !== null) {
                $failures[$stepId] = $message;
            }
        }

        return $failures;
    }

    /**
     * Returns all retained findings in one normalized list.
     *
     * @return UblValidationFinding[]
     */
    public function issues(): array
    {
        $findings = [];

        foreach ($this->stepResults as $stepId => $stepResult) {
            foreach ($stepResult->issues() as $issue) {
                $findings[] = $issue->toFinding($stepId);
            }
        }

        return $findings;
    }

    /**
     * Returns reports keyed by validation step identifier.
     *
     * @return array<string, UblValidationStepResult>
     */
    public function stepResults(): array
    {
        return $this->stepResults;
    }

    /**
     * Returns aggregate finding counts for the validation result.
     *
     * @return UblValidationSummary
     */
    public function summary(): UblValidationSummary
    {
        return $this->summary;
    }

    /**
     * Returns the retained raw issues for one validation step.
     *
     * @param  string                        $stepId Identifier of the validation step reporting findings.
     * @return UblValidationIssueInterface[]
     */
    public function issuesForStep(string $stepId): array
    {
        $stepResult = $this->stepResults[$stepId] ?? null;

        return $stepResult === null ? [] : $stepResult->issues();
    }

    /**
     * Returns the number of findings omitted by one validation step.
     *
     * @param  string $stepId Identifier of the validation step reporting findings.
     * @return int
     */
    public function omittedCountForStep(string $stepId): int
    {
        $stepResult = $this->stepResults[$stepId] ?? null;

        return $stepResult === null ? 0 : $stepResult->omittedCount();
    }
}
