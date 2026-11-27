<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

/** One libxml validation issue, with message and source location when reported. */
final class UblSchemaValidationIssue implements UblValidationIssueInterface
{
    private int $line;
    private int $column;
    private string $message;

    /**
     * @param int    $line    One-based XML source line reported by libxml.
     * @param int    $column  Column position associated with the XML or validation value.
     * @param string $message Message or diagnostic text associated with the result.
     */
    public function __construct(int $line, int $column, string $message)
    {
        $this->line    = $line;
        $this->column  = $column;
        $this->message = $message;
    }

    /**
     * Returns the severity of schema validation issues.
     *
     * @return UblValidationSeverity
     */
    public function severity(): UblValidationSeverity
    {
        return UblValidationSeverity::error();
    }

    /**
     * Returns the schema issue as a stable serialization shape.
     *
     * @return array{line: int, column: int, message: string, severity: string}
     */
    public function toArray(): array
    {
        return [
            'line'     => $this->line,
            'column'   => $this->column,
            'message'  => $this->message,
            'severity' => $this->severity()->value(),
        ];
    }

    /**
     * Converts this schema issue into a finding attributed to a validation stage.
     *
     * @param  string               $stageId Identifier of the validation stage reporting findings.
     * @return UblValidationFinding
     */
    public function toFinding(string $stageId): UblValidationFinding
    {
        return new UblValidationFinding(
            $stageId,
            $this,
            null,
            $this->message,
            new UblValidationLocation(null, $this->line, $this->column),
            [],
            $this->severity()
        );
    }
}
