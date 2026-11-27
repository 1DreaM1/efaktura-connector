<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

use DreaM1\EfakturaConnector\Support\NonBlankString;

/** Normalized finding reported by a local UBL validation stage. */
final class UblValidationFinding
{
    private string $stageId;
    private UblValidationIssueInterface $sourceIssue;
    private ?string $code;
    private string $message;
    private UblValidationLocation $location;
    private UblValidationSeverity $severity;
    /** @var array<string, mixed> Stage-specific values that do not fit the common location fields. */
    private array $context;
    private ?UblValidationGuidance $guidance;

    /**
     * Creates a normalized validation finding.
     *
     * @param string                      $stageId     Identifier of the validation stage reporting findings.
     * @param UblValidationIssueInterface $sourceIssue Validation finding from the underlying source.
     * @param string|null                 $code        Code value to validate, map, or report.
     * @param string                      $message     Message or diagnostic text associated with the result.
     * @param UblValidationLocation       $location    Path, line and column reported by the validation stage.
     * @param array<string, mixed>        $context     Additional structured values supplied by the validation stage.
     * @param UblValidationSeverity|null  $severity    Severity assigned to the validation finding.
     * @param UblValidationGuidance|null  $guidance    Optional rule reference and correction hint.
     */
    public function __construct(
        string $stageId,
        UblValidationIssueInterface $sourceIssue,
        ?string $code,
        string $message,
        UblValidationLocation $location,
        array $context = [],
        ?UblValidationSeverity $severity = null,
        ?UblValidationGuidance $guidance = null
    ) {
        NonBlankString::assertNotBlank($stageId, 'Validation finding stage identifier must not be empty.');

        $this->stageId     = $stageId;
        $this->sourceIssue = $sourceIssue;
        $this->code        = $code;
        $this->message     = $message;
        $this->location    = $location;
        $this->severity    = $severity ?? UblValidationSeverity::error();
        $this->context     = $context;
        $this->guidance    = $guidance;
    }

    /**
     * Returns the identifier of the validation stage that reported this finding.
     *
     * @return string
     */
    public function stageId(): string
    {
        return $this->stageId;
    }

    /**
     * Returns the original typed issue represented by this normalized finding.
     *
     * @return UblValidationIssueInterface
     */
    public function sourceIssue(): UblValidationIssueInterface
    {
        return $this->sourceIssue;
    }

    /**
     * Returns the rule or error code when the stage provides one.
     *
     * @return string|null
     */
    public function code(): ?string
    {
        return $this->code;
    }

    /**
     * Returns the finding message.
     *
     * @return string
     */
    public function message(): string
    {
        return $this->message;
    }

    /**
     * Returns the severity of this finding.
     *
     * @return UblValidationSeverity
     */
    public function severity(): UblValidationSeverity
    {
        return $this->severity;
    }

    /**
     * Returns the common location shape.
     *
     * @return array{path: string|null, line: int|null, column: int|null}
     */
    public function location(): array
    {
        return $this->location->toArray();
    }

    /** Returns where the finding was reported as a value object. */
    public function locationDetails(): UblValidationLocation
    {
        return $this->location;
    }

    /**
     * Returns stage-specific context that does not fit the common location fields.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /** Returns an optional rule reference and correction hint. */
    public function guidance(): ?UblValidationGuidance
    {
        return $this->guidance;
    }

    /**
     * Returns the finding in the normalized serialization shape.
     *
     * @return array{stage: string, code: string|null, message: string, severity: string, location: array{path: string|null, line: int|null, column: int|null}, context: array<string, mixed>, guidance?: array{reference_url: string|null, recommended_action: string|null}}
     */
    public function toArray(): array
    {
        $result = [
            'stage'    => $this->stageId,
            'code'     => $this->code,
            'message'  => $this->message,
            'severity' => $this->severity->value(),
            'location' => $this->location(),
            'context'  => $this->context,
        ];

        if ($this->guidance !== null) {
            $result['guidance'] = $this->guidance->toArray();
        }

        return $result;
    }
}
