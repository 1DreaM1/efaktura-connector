<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Ubl\Validation;

use DreaM1\EfakturaConnector\Core\Ubl\Validation\LimitedValidationIssues;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolSchematronValidationResult;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolValidationIssue;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationSeverity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks Peppol Schematron findings, omitted counts and validation of rule-result types. */
class PeppolSchematronValidationResultTest extends TestCase
{
    public function testWarningsDoNotMakeTheResultInvalid(): void
    {
        $warning = new PeppolValidationIssue(
            'BR-SYNTHETIC',
            'Synthetic warning.',
            '/Invoice',
            null,
            UblValidationSeverity::warning()
        );

        $result = new PeppolSchematronValidationResult(new LimitedValidationIssues(
            [$warning],
            0,
            PeppolValidationIssue::class
        ));

        $this->assertSame([$warning], $result->issues());
        $this->assertSame(0, $result->omittedCount());
        $this->assertFalse($result->hasErrors());
        $this->assertTrue($result->isValid());
    }

    public function testErrorsAndOmittedFindingsMakeTheResultInvalid(): void
    {
        $error       = new PeppolValidationIssue('BR-SYNTHETIC', 'Synthetic error.', '/Invoice', null);
        $errorResult = new PeppolSchematronValidationResult(new LimitedValidationIssues(
            [$error],
            0,
            PeppolValidationIssue::class
        ));

        $this->assertTrue($errorResult->hasErrors());
        $this->assertFalse($errorResult->isValid());

        $warning = new PeppolValidationIssue(
            'BR-SYNTHETIC',
            'Synthetic warning.',
            '/Invoice',
            null,
            UblValidationSeverity::warning()
        );
        $incompleteResult = new PeppolSchematronValidationResult(new LimitedValidationIssues(
            [$warning],
            1,
            PeppolValidationIssue::class
        ));

        $this->assertFalse($incompleteResult->hasErrors());
        $this->assertFalse($incompleteResult->isValid());
        $this->assertSame(1, $incompleteResult->omittedCount());
    }

    public function testRejectsFindingsThatWereLimitedForAnotherIssueType(): void
    {
        $findings = new LimitedValidationIssues([], 0);

        $this->expectException(InvalidArgumentException::class);
        new PeppolSchematronValidationResult($findings);
    }
}
