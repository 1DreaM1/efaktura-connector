<?php

namespace DreaM1\EfakturaConnector\Tests\Support\Validation;

use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationStageInterface;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationStepResult;

/** No-op stage used to verify Laravel's configured validation-stage registration. */
final class ConfiguredValidationStage implements UblValidationStageInterface
{
    public const ID = 'configured_test';

    /** Returns the stable identifier used by the configured-stage test fixture. */
    public function id(): string
    {
        return self::ID;
    }

    /**
     * Returns no rule versions for this no-op test stage.
     *
     * @return array<string, string>
     */
    public function ruleVersions(): array
    {
        return [];
    }

    /**
     * Returns an empty result for the supplied fixture document.
     *
     * @param  UblXmlDocument          $document Document supplied by the validation pipeline.
     * @return UblValidationStepResult Empty validation step result.
     */
    public function validate(UblXmlDocument $document): UblValidationStepResult
    {
        return new UblValidationStepResult();
    }
}
