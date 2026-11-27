<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Validation;

use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolBillingRule;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolBillingRuleIssue;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolValidationIssue;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblSchemaValidationIssue;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationIssueLimiter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests validation issue limits and omitted finding counts. */
class ValidationIssueLimiterTest extends TestCase
{
    public function testLimitsStructuredIssuesAndReportsTheOmittedCountSeparately(): void
    {
        $issues = [
            new UblSchemaValidationIssue(1, 1, 'Synthetic schema issue.'),
            new PeppolValidationIssue('BR-SYNTHETIC', 'Synthetic Schematron issue.', '/Invoice', null),
            new PeppolBillingRuleIssue(PeppolBillingRule::PAYMENT_DUE_DATE_OR_TERMS),
        ];
        for ($number = 4; $number <= 10; $number++) {
            $issues[] = new UblSchemaValidationIssue($number, 1, 'Synthetic issue ' . $number);
        }

        $limited = ValidationIssueLimiter::limit($issues);

        $this->assertCount(8, $limited->issues());
        $this->assertSame(2, $limited->omittedCount());
        $this->assertSame($issues[0], $limited->issues()[0]);
        $this->assertSame($issues[1], $limited->issues()[1]);
        $this->assertSame($issues[2], $limited->issues()[2]);
    }

    public function testKeepsShortIssueListsAndReportsNoOmissions(): void
    {
        $issues = [new UblSchemaValidationIssue(1, 1, 'Synthetic issue')];

        $limited = ValidationIssueLimiter::limit($issues);

        $this->assertSame($issues, $limited->issues());
        $this->assertSame(0, $limited->omittedCount());
    }

    public function testUsesConfiguredIssueLimit(): void
    {
        $issues = [];
        for ($number = 1; $number <= 5; $number++) {
            $issues[] = new UblSchemaValidationIssue($number, 1, 'Synthetic issue ' . $number);
        }

        $limited = ValidationIssueLimiter::limit($issues, 3);

        $this->assertCount(3, $limited->issues());
        $this->assertSame(2, $limited->omittedCount());
    }

    public function testCollectorDoesNotConstructIssuesAfterCapacityIsReached(): void
    {
        $collector        = new ValidationIssueLimiter(1, UblSchemaValidationIssue::class);
        $factoryCallCount = 0;
        $collector->addLazy(static function () use (&$factoryCallCount): UblSchemaValidationIssue {
            $factoryCallCount++;

            return new UblSchemaValidationIssue(1, 1, 'Retained issue.');
        });
        $collector->addLazy(static function () use (&$factoryCallCount): UblSchemaValidationIssue {
            $factoryCallCount++;

            return new UblSchemaValidationIssue(2, 1, 'Omitted issue.');
        });

        $result = $collector->result();

        $this->assertSame(1, $factoryCallCount);
        $this->assertCount(1, $result->issues());
        $this->assertSame(1, $result->omittedCount());
    }

    public function testCollectorValidatesIssueTypeAndOmittedCounts(): void
    {
        $limiter = new ValidationIssueLimiter(null, UblSchemaValidationIssue::class);
        $limiter->add(new UblSchemaValidationIssue(1, 1, 'Synthetic issue.'));
        $limiter->addOmitted(2);

        $this->assertSame(2, $limiter->result()->omittedCount());

        $this->expectException(InvalidArgumentException::class);
        $limiter->addOmitted(-1);
    }

    public function testCollectorRejectsIssuesOutsideItsConfiguredType(): void
    {
        $limiter = new ValidationIssueLimiter(null, UblSchemaValidationIssue::class);

        $this->expectException(InvalidArgumentException::class);
        $limiter->add(new PeppolValidationIssue('BR-SYNTHETIC', 'Synthetic issue.', '/Invoice', null));
    }

    public function testRejectsWrongIssueTypeAfterCapacityIsReachedWithoutCountingItAsOmitted(): void
    {
        $limiter = new ValidationIssueLimiter(1, UblSchemaValidationIssue::class);
        $limiter->add(new UblSchemaValidationIssue(1, 1, 'Retained issue.'));

        try {
            $limiter->add(new PeppolValidationIssue('BR-SYNTHETIC', 'Wrong issue type.', '/Invoice', null));
            $this->fail('Expected a mismatched validation issue type to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Validation issue does not match the configured issue type.',
                $exception->getMessage()
            );
        }

        $result = $limiter->result();
        $this->assertCount(1, $result->issues());
        $this->assertSame(0, $result->omittedCount());
    }
}
