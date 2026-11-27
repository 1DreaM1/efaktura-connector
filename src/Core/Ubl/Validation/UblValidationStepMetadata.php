<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;

/** Execution metadata for one local UBL validation step. */
final class UblValidationStepMetadata
{
    private float $durationMilliseconds;
    private UblValidationStepStatus $status;
    /** @var array<string, string> */
    private array $ruleVersions;
    /** @var array<string, string> */
    private array $provenance;

    /**
     * Creates step metadata.
     *
     * @param  float                   $durationMilliseconds Elapsed operation duration, in milliseconds.
     * @param  UblValidationStepStatus $status               Outcome of this validation step.
     * @param  array<string, string>   $ruleVersions         Versions of the validation rule sets.
     * @param  array<string, string>   $provenance           Artifact fingerprints and runtime versions.
     * @throws InvalidInputException   when duration is negative or non-finite, or a rule/provenance entry is invalid.
     */
    public function __construct(
        float $durationMilliseconds,
        UblValidationStepStatus $status,
        array $ruleVersions = [],
        array $provenance = []
    ) {
        if (! is_finite($durationMilliseconds) || $durationMilliseconds < 0) {
            throw new InvalidInputException('Validation step duration must be a finite non-negative number.');
        }

        foreach ($ruleVersions as $ruleSet => $version) {
            if (! is_string($ruleSet) || ! NonBlankString::isNotBlank($ruleSet)) {
                throw new InvalidInputException('Validation rule-set identifiers must not be empty strings.');
            }

            if (! is_string($version) || ! NonBlankString::isNotBlank($version)) {
                throw new InvalidInputException('Validation rule-set versions must not be empty strings.');
            }
        }

        foreach ($provenance as $key => $value) {
            if (! is_string($key) || ! NonBlankString::isNotBlank($key)) {
                throw new InvalidInputException('Validation provenance identifiers must not be empty strings.');
            }

            if (! is_string($value) || ! NonBlankString::isNotBlank($value)) {
                throw new InvalidInputException('Validation provenance values must not be empty strings.');
            }
        }

        $this->durationMilliseconds = $durationMilliseconds;
        $this->status               = $status;
        $this->ruleVersions         = $ruleVersions;
        $this->provenance           = $provenance;
    }

    /** Returns the measured stage duration in milliseconds. */
    public function durationMilliseconds(): float
    {
        return $this->durationMilliseconds;
    }

    /** Returns the stage outcome. */
    public function status(): UblValidationStepStatus
    {
        return $this->status;
    }

    /** @return array<string, string> Rule-set versions used by the stage. */
    public function ruleVersions(): array
    {
        return $this->ruleVersions;
    }

    /** @return array<string, string> Artifact fingerprints and runtime versions used by this step. */
    public function provenance(): array
    {
        return $this->provenance;
    }

    /**
     * Returns the metadata in a serialization-friendly shape.
     *
     * @return array{duration_ms: float, status: string, rule_versions: array<string, string>, provenance?: array<string, string>}
     */
    public function toArray(): array
    {
        $result = [
            'duration_ms'   => $this->durationMilliseconds,
            'status'        => $this->status->value(),
            'rule_versions' => $this->ruleVersions,
        ];

        if ($this->provenance !== []) {
            $result['provenance'] = $this->provenance;
        }

        return $result;
    }
}
