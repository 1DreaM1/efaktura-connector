<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Ubl\Validation;

use DreaM1\EfakturaConnector\Core\Ubl\Validation\LimitedValidationIssues;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolValidationIssue;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationSeverity;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationStepResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

/** Covers validation-step status, findings, metadata and omitted-issue handling. */
class UblValidationStepResultTest extends TestCase
{
    public function testRejectsUnsupportedSeverityValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UblValidationSeverity::fromString('critical');
    }

    public function testRejectsValuesThatDoNotImplementTheIssueContract(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LimitedValidationIssues([new stdClass()], 0);
    }

    public function testNormalizesIssueKeysAfterValidation(): void
    {
        $issue  = new PeppolValidationIssue('BR-SYNTHETIC', 'Synthetic issue.', '/Invoice', null);
        $result = new UblValidationStepResult(new LimitedValidationIssues([5 => $issue], 0));

        $this->assertSame([$issue], $result->issues());
    }

    public function testRejectsNegativeOmittedCountConsistently(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LimitedValidationIssues([], -1);
    }
}
