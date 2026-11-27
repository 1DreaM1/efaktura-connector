<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\StringValue;

/** Document-validation or infrastructure outcome of one UBL validation step. */
final class UblValidationStepStatus
{
    use StringValue;

    /** Status for a step completed without errors or omitted findings. */
    public const PASSED = 'passed';
    /** Status for a completed step that found document validation errors. */
    public const FAILED = 'failed';
    /** Status for a step whose results are incomplete. */
    public const INCOMPLETE = 'incomplete';
    /** Status for a step that could not complete because of an infrastructure problem. */
    public const INFRASTRUCTURE_ERROR = 'infrastructure_error';

    /** Creates a passed validation step status. */
    public static function passed(): self
    {
        return new self(self::PASSED);
    }

    /** Creates a failed validation step status. */
    public static function failed(): self
    {
        return new self(self::FAILED);
    }

    /** Creates an incomplete validation step status. */
    public static function incomplete(): self
    {
        return new self(self::INCOMPLETE);
    }

    /** Creates a status for a step that could not run because of an infrastructure problem. */
    public static function infrastructureError(): self
    {
        return new self(self::INFRASTRUCTURE_ERROR);
    }

    /** Whether this step completed without document errors or omitted findings. */
    public function isPassed(): bool
    {
        return $this->value === self::PASSED;
    }

    protected static function normalize(string $value): string
    {
        if (! in_array($value, [self::PASSED, self::FAILED, self::INCOMPLETE, self::INFRASTRUCTURE_ERROR], true)) {
            throw new InvalidInputException('Validation step status is not supported.');
        }

        return $value;
    }
}
