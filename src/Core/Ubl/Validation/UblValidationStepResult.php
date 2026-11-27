<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

/** Findings and execution outcome from one local UBL validation stage. */
final class UblValidationStepResult
{
    /** @var LimitedValidationIssues<UblValidationIssueInterface> */
    private LimitedValidationIssues $findings;
    private ?UblValidationStepMetadata $metadata;
    private ?string $infrastructureError;

    /**
     * Creates a result from a stage's retained and omitted findings.
     *
     * @param LimitedValidationIssues<UblValidationIssueInterface>|null $findings            Retained and omitted findings.
     * @param UblValidationStepMetadata|null                            $metadata            Metadata whose status is normalized to this result's status.
     * @param string|null                                               $infrastructureError Technical error preventing this step from running.
     */
    public function __construct(
        ?LimitedValidationIssues $findings = null,
        ?UblValidationStepMetadata $metadata = null,
        ?string $infrastructureError = null
    ) {
        $this->findings            = $findings ?? new LimitedValidationIssues([], 0);
        $this->infrastructureError = $infrastructureError;
        $this->metadata            = null;

        if ($metadata !== null) {
            $status         = $this->status();
            $this->metadata = $metadata->status()->equals($status)
                ? $metadata
                : new UblValidationStepMetadata(
                    $metadata->durationMilliseconds(),
                    $status,
                    $metadata->ruleVersions(),
                    $metadata->provenance()
                );
        }
    }

    /**
     * Creates a result for a step that could not run because of an infrastructure failure.
     *
     * @param  string $message Technical failure message.
     * @return self
     */
    public static function infrastructureFailure(string $message): self
    {
        return new self(null, null, $message);
    }

    /**
     * Returns the document-validation or infrastructure outcome for this stage.
     *
     * @return UblValidationStepStatus
     */
    public function status(): UblValidationStepStatus
    {
        if ($this->infrastructureError !== null) {
            return UblValidationStepStatus::infrastructureError();
        }

        foreach ($this->findings->issues() as $issue) {
            if ($issue->severity()->isError()) {
                return UblValidationStepStatus::failed();
            }
        }

        return $this->findings->omittedCount() > 0
            ? UblValidationStepStatus::incomplete()
            : UblValidationStepStatus::passed();
    }

    /** Whether this stage reported no errors or omitted findings. */
    public function isValid(): bool
    {
        return $this->status()->isPassed();
    }

    /**
     * Returns the retained findings.
     *
     * @return UblValidationIssueInterface[]
     */
    public function issues(): array
    {
        return $this->findings->issues();
    }

    /**
     * Returns retained and omitted findings as one value for downstream processing.
     *
     * @return LimitedValidationIssues<UblValidationIssueInterface>
     */
    public function findings(): LimitedValidationIssues
    {
        return $this->findings;
    }

    /**
     * Returns the number of findings omitted from this result.
     *
     * @return int
     */
    public function omittedCount(): int
    {
        return $this->findings->omittedCount();
    }

    /**
     * Returns the technical error when the step could not run.
     *
     * @return string|null
     */
    public function infrastructureError(): ?string
    {
        return $this->infrastructureError;
    }

    /**
     * Returns execution metadata when this result was produced by the validation pipeline.
     *
     * @return UblValidationStepMetadata|null
     */
    public function metadata(): ?UblValidationStepMetadata
    {
        return $this->metadata;
    }

    /**
     * Returns a copy of this report with pipeline execution metadata attached.
     *
     * @param  UblValidationStepMetadata $metadata Provider metadata associated with the document.
     * @return self
     */
    public function withMetadata(UblValidationStepMetadata $metadata): self
    {
        return new self($this->findings, $metadata, $this->infrastructureError);
    }
}
