<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

/** Common contract for local UBL validation findings, including severity and serialization. */
interface UblValidationIssueInterface
{
    /**
     * Returns the severity of this issue.
     *
     * @return UblValidationSeverity
     */
    public function severity(): UblValidationSeverity;

    /**
     * Returns the finding as an array for serialization or storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Converts this issue to the common reporting model for its validation stage.
     *
     * @param  string               $stageId Identifier of the validation stage reporting findings.
     * @return UblValidationFinding
     */
    public function toFinding(string $stageId): UblValidationFinding;
}
