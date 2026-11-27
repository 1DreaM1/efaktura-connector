<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

/** A Peppol Schematron finding with a rule identifier, message and affected XML path. */
final class PeppolValidationIssue implements UblValidationIssueInterface
{
    private string $ruleId;
    private string $message;
    private string $location;
    private ?PeppolValidationLine $line;
    private UblValidationSeverity $severity;
    private ?UblValidationGuidance $guidance;

    /**
     * @param string                     $ruleId   Identifier of the validation rule.
     * @param string                     $message  Message or diagnostic text associated with the result.
     * @param string                     $location Resource location or validation location associated with the value.
     * @param PeppolValidationLine|null  $line     Invoice or credit note line associated with the finding.
     * @param UblValidationSeverity|null $severity Severity assigned to the validation finding.
     * @param UblValidationGuidance|null $guidance Optional rule reference and correction hint.
     */
    public function __construct(
        string $ruleId,
        string $message,
        string $location,
        ?PeppolValidationLine $line,
        ?UblValidationSeverity $severity = null,
        ?UblValidationGuidance $guidance = null
    ) {
        $this->ruleId   = $ruleId;
        $this->message  = $message;
        $this->location = $location;
        $this->line     = $line;
        $this->severity = $severity ?? UblValidationSeverity::error();
        $this->guidance = $guidance;
    }

    /**
     * Returns the message.
     *
     * @return string
     */
    public function rawMessage(): string
    {
        return $this->message;
    }

    /**
     * Returns the severity reported by the Schematron rule.
     *
     * @return UblValidationSeverity
     */
    public function severity(): UblValidationSeverity
    {
        return $this->severity;
    }

    /** Returns an optional rule reference and correction hint. */
    public function guidance(): ?UblValidationGuidance
    {
        return $this->guidance;
    }

    /**
     * Returns the Schematron finding as a stable serialization shape.
     *
     * @return array{rule_id: string, message: string, severity: string, location: string, line: array<string, mixed>|null}
     */
    public function toArray(): array
    {
        return [
            'rule_id'  => $this->ruleId,
            'message'  => $this->message,
            'severity' => $this->severity->value(),
            'location' => $this->location,
            'line'     => $this->line ? $this->line->toArray() : null,
        ];
    }

    /**
     * Converts this issue into a finding attributed to a validation stage.
     *
     * @param  string               $stageId Identifier of the validation stage reporting findings.
     * @return UblValidationFinding
     */
    public function toFinding(string $stageId): UblValidationFinding
    {
        $context = $this->line ? ['invoice_line' => $this->line->toArray()] : [];

        return new UblValidationFinding(
            $stageId,
            $this,
            $this->ruleId,
            $this->message,
            new UblValidationLocation($this->location),
            $context,
            $this->severity,
            $this->guidance
        );
    }
}
