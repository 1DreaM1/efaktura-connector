<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Validation;

use DreaM1\EfakturaConnector\Core\Contracts\PeppolSchematronValidationInterface;
use DreaM1\EfakturaConnector\Core\Contracts\UblSchemaValidationInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationProvenanceProviderInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationStageInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\SchemaValidationException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\UnsupportedDocumentTypeException;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\LimitedValidationIssues;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\LocalUblValidationResult;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolSchematronValidationResult;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolValidationIssue;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblSchemaValidationIssue;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationGuidance;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationIssueInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationSeverity;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationStepMetadata;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationStepResult;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationStepStatus;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\LocalUblValidator;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schema\UblSchemaValidationStage;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\CenSchematronValidationStage;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\PeppolSchematronValidationStage;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\UblValidationPipeline;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests the local UBL validation pipeline and its stage results. */
class LocalUblValidatorTest extends TestCase
{
    public function testCombinesSchemaAndSchematronIssues(): void
    {
        $document        = $this->document();
        $schemaIssue     = new UblSchemaValidationIssue(2, 4, 'Synthetic schema issue.');
        $schematronIssue = new PeppolValidationIssue('BR-SYNTHETIC', 'Synthetic rule issue.', '/Invoice/cbc:ID', null);

        $schemaValidator = $this->createMock(UblSchemaValidationInterface::class);
        $schemaValidator->expects($this->once())
            ->method('validateDocument')
            ->with($this->identicalTo($document))
            ->willThrowException(new SchemaValidationException('2.1', [$schemaIssue], 2));

        $schematronValidator = $this->createMock(PeppolSchematronValidationInterface::class);
        $schematronValidator->expects($this->once())
            ->method('validateDocument')
            ->with($this->identicalTo($document))
            ->willReturn($this->peppolResult([$schematronIssue], 1));

        $result = $this->validator($schemaValidator, $schematronValidator)->validateDocument($document);

        $this->assertInstanceOf(LocalUblValidationResult::class, $result);
        $this->assertFalse($result->isValid());
        $this->assertCount(2, $result->issues());
        $this->assertSame(UblSchemaValidationStage::ID, $result->issues()[0]->stageId());
        $this->assertSame($schemaIssue, $result->issues()[0]->sourceIssue());
        $this->assertSame(PeppolSchematronValidationStage::ID, $result->issues()[1]->stageId());
        $this->assertSame($schematronIssue, $result->issues()[1]->sourceIssue());
        $this->assertSame(2, $result->omittedCountForStep(UblSchemaValidationStage::ID));
        $this->assertSame(1, $result->omittedCountForStep(PeppolSchematronValidationStage::ID));
        $this->assertSame(
            UblValidationStepStatus::FAILED,
            $result->stepResults()[UblSchemaValidationStage::ID]->metadata()->status()->value()
        );
    }

    public function testReturnsAValidResultWhenBothStagesPass(): void
    {
        $document            = $this->document();
        $schemaValidator     = $this->createMock(UblSchemaValidationInterface::class);
        $schematronValidator = $this->createMock(PeppolSchematronValidationInterface::class);
        $schemaValidator->expects($this->once())->method('validateDocument')->with($document);
        $schematronValidator->expects($this->once())
            ->method('validateDocument')
            ->with($document)
            ->willReturn(new PeppolSchematronValidationResult());

        $result = $this->validator($schemaValidator, $schematronValidator)->validateDocument($document);

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->issues());
        $this->assertSame(
            [UblSchemaValidationStage::ID, PeppolSchematronValidationStage::ID],
            array_keys($result->stepResults())
        );

        foreach ($result->stepResults() as $stepResult) {
            $this->assertNotNull($stepResult->metadata());
            $this->assertSame(UblValidationStepStatus::PASSED, $stepResult->metadata()->status()->value());
            $this->assertGreaterThanOrEqual(0, $stepResult->metadata()->durationMilliseconds());
        }
    }

    public function testIncludesCustomRuleVersionsInStepMetadata(): void
    {
        $stage = new class () implements UblValidationStageInterface {
            /** Returns the identifier for this test validation stage. */
            public function id(): string
            {
                return 'custom_versioned';
            }

            /**
             * Returns no findings for the supplied test document.
             *
             * @param  UblXmlDocument          $document Document supplied by the validation pipeline.
             * @return UblValidationStepResult Empty stage result.
             */
            public function validate(UblXmlDocument $document): UblValidationStepResult
            {
                return new UblValidationStepResult();
            }

            /**
             * Returns the synthetic rule version exposed by this test stage.
             *
             * @return array<string, string> Test rule identifiers mapped to versions.
             */
            public function ruleVersions(): array
            {
                return ['custom_rules' => '1.2.3'];
            }
        };
        $result   = (new LocalUblValidator([$stage]))->validateDocument($this->document());
        $metadata = $result->stepResults()['custom_versioned']->metadata();

        $this->assertNotNull($metadata);
        $this->assertSame(['custom_rules' => '1.2.3'], $metadata->ruleVersions());
        $this->assertSame([
            'duration_ms'   => $metadata->durationMilliseconds(),
            'status'        => UblValidationStepStatus::PASSED,
            'rule_versions' => ['custom_rules' => '1.2.3'],
        ], $metadata->toArray());
    }

    public function testMarksStepIncompleteWhenFindingsWereOmitted(): void
    {
        $stage = new class () implements UblValidationStageInterface {
            /** Returns the identifier for this test validation stage. */
            public function id(): string
            {
                return 'limited';
            }

            /** Returns no rule versions for this synthetic stage. */
            public function ruleVersions(): array
            {
                return [];
            }

            /**
             * Returns a result with one omitted finding for the supplied test document.
             *
             * @param  UblXmlDocument          $document Document supplied by the validation pipeline.
             * @return UblValidationStepResult Incomplete stage result.
             */
            public function validate(UblXmlDocument $document): UblValidationStepResult
            {
                return new UblValidationStepResult(new LimitedValidationIssues([], 1));
            }
        };
        $result = (new LocalUblValidator([$stage]))->validateDocument($this->document());

        $this->assertFalse($result->isValid());
        $this->assertSame(
            UblValidationStepStatus::INCOMPLETE,
            $result->stepResults()['limited']->metadata()->status()->value()
        );
    }

    public function testNormalizesMismatchedMetadataStatusToStepResult(): void
    {
        $findings = new LimitedValidationIssues([
            new UblSchemaValidationIssue(2, 4, 'Synthetic schema error.'),
        ], 0);
        $metadata = new UblValidationStepMetadata(
            12.5,
            UblValidationStepStatus::passed(),
            ['schema' => '2.1'],
            ['xsd' => 'sha256:synthetic']
        );
        $results = [
            new UblValidationStepResult($findings, $metadata),
            (new UblValidationStepResult($findings))->withMetadata($metadata),
        ];

        foreach ($results as $result) {
            $this->assertSame(UblValidationStepStatus::FAILED, $result->status()->value());
            $this->assertNotNull($result->metadata());
            $this->assertSame(UblValidationStepStatus::FAILED, $result->metadata()->status()->value());
            $this->assertSame(12.5, $result->metadata()->durationMilliseconds());
            $this->assertSame(['schema' => '2.1'], $result->metadata()->ruleVersions());
            $this->assertSame(['xsd' => 'sha256:synthetic'], $result->metadata()->provenance());
            $this->assertSame(UblValidationStepStatus::FAILED, $result->metadata()->toArray()['status']);
        }

        $this->assertSame(UblValidationStepStatus::PASSED, $metadata->status()->value());
    }

    public function testWarningAndInfoFindingsDoNotInvalidateResult(): void
    {
        $warning = new PeppolValidationIssue(
            'WARN-1',
            'Synthetic warning.',
            '/Invoice',
            null,
            UblValidationSeverity::warning()
        );
        $info = new PeppolValidationIssue(
            'INFO-1',
            'Synthetic information.',
            '/Invoice',
            null,
            UblValidationSeverity::info()
        );
        $result = LocalUblValidationResult::fromStepResults([
            'peppol_schematron' => $this->stepResult([$warning, $info]),
        ]);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->stepResults()['peppol_schematron']->isValid());
        $this->assertSame(
            UblValidationStepStatus::PASSED,
            $result->stepResults()['peppol_schematron']->status()->value()
        );
        $this->assertSame(
            [UblValidationSeverity::WARNING, UblValidationSeverity::INFO],
            array_map(
                static fn ($finding): string => $finding->severity()->value(),
                $result->issues()
            )
        );
        $this->assertSame(
            [UblValidationSeverity::WARNING, UblValidationSeverity::INFO],
            array_column(array_map(
                static fn ($finding): array => $finding->toArray(),
                $result->issues()
            ), 'severity')
        );
    }

    public function testSummarizesFindingsBySeverityAndStep(): void
    {
        $error   = new UblSchemaValidationIssue(2, 4, 'Synthetic schema error.');
        $warning = new PeppolValidationIssue(
            'WARN-1',
            'Synthetic warning.',
            '/Invoice',
            null,
            UblValidationSeverity::warning()
        );
        $info = new PeppolValidationIssue(
            'INFO-1',
            'Synthetic information.',
            '/Invoice',
            null,
            UblValidationSeverity::info()
        );
        $result = LocalUblValidationResult::fromStepResults([
            'schema'            => $this->stepResult([$error], 2),
            'peppol_schematron' => $this->stepResult([$warning, $info], 1),
            'custom_empty'      => new UblValidationStepResult(),
        ]);
        $summary = $result->summary();

        $this->assertSame(3, $summary->totalFindings());
        $this->assertSame([
            'error'   => 1,
            'warning' => 1,
            'info'    => 1,
        ], $summary->countsBySeverity());
        $this->assertSame([
            'schema'            => ['error' => 1, 'warning' => 0, 'info' => 0, 'omitted_count' => 2],
            'peppol_schematron' => ['error' => 0, 'warning' => 1, 'info' => 1, 'omitted_count' => 1],
            'custom_empty'      => ['error' => 0, 'warning' => 0, 'info' => 0, 'omitted_count' => 0],
        ], $summary->countsByStep());
        $this->assertSame(3, $summary->omittedCount());
        $this->assertSame([
            'total_findings' => 3,
            'by_severity'    => ['error' => 1, 'warning' => 1, 'info' => 1],
            'by_step'        => [
                'schema'            => ['error' => 1, 'warning' => 0, 'info' => 0, 'omitted_count' => 2],
                'peppol_schematron' => ['error' => 0, 'warning' => 1, 'info' => 1, 'omitted_count' => 1],
                'custom_empty'      => ['error' => 0, 'warning' => 0, 'info' => 0, 'omitted_count' => 0],
            ],
            'omitted_count' => 3,
        ], $summary->toArray());
    }

    public function testRejectsIntegerLikeStageIdentifiers(): void
    {
        $stage = new class () implements UblValidationStageInterface {
            /** Returns a deliberately numeric identifier to exercise pipeline validation. */
            public function id(): string
            {
                return '123';
            }

            /** Returns no rule versions for this synthetic stage. */
            public function ruleVersions(): array
            {
                return [];
            }

            /**
             * Returns no findings for the supplied test document.
             *
             * @param  UblXmlDocument          $document Document supplied by the validation pipeline.
             * @return UblValidationStepResult Empty stage result.
             */
            public function validate(UblXmlDocument $document): UblValidationStepResult
            {
                return new UblValidationStepResult();
            }
        };

        $this->expectException(InvalidArgumentException::class);
        new UblValidationPipeline([$stage]);
    }

    public function testRejectsCombiningCenOnlyAndFullPeppolSchematronStages(): void
    {
        $makeStage = static function (string $id): UblValidationStageInterface {
            return new class ($id) implements UblValidationStageInterface {
                private string $id;

                /** Stores the identifier this synthetic stage reports. */
                public function __construct(string $id)
                {
                    $this->id = $id;
                }

                /** Returns the configured identifier. */
                public function id(): string
                {
                    return $this->id;
                }

                /** Returns no rule versions for this synthetic stage. */
                public function ruleVersions(): array
                {
                    return [];
                }

                /**
                 * Returns no findings for the supplied test document.
                 *
                 * @param  UblXmlDocument          $document Document supplied by the validation pipeline.
                 * @return UblValidationStepResult Empty stage result.
                 */
                public function validate(UblXmlDocument $document): UblValidationStepResult
                {
                    return new UblValidationStepResult();
                }
            };
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not combine');
        new UblValidationPipeline([
            $makeStage(CenSchematronValidationStage::ID),
            $makeStage(PeppolSchematronValidationStage::ID),
        ]);
    }

    public function testRunsCustomStagesAndIncludesTheirFindings(): void
    {
        $issue = new PeppolValidationIssue('CUSTOM-1', 'Synthetic custom issue.', '/Invoice', null);
        $stage = new class ($issue) implements UblValidationStageInterface {
            private PeppolValidationIssue $issue;

            /**
             * Creates a stage that reports the supplied test finding.
             *
             * @param PeppolValidationIssue $issue Finding returned by this stage.
             */
            public function __construct(PeppolValidationIssue $issue)
            {
                $this->issue = $issue;
            }

            /** Returns the identifier for this custom test stage. */
            public function id(): string
            {
                return 'custom_quality_check';
            }

            /** Returns no rule versions for this synthetic stage. */
            public function ruleVersions(): array
            {
                return [];
            }

            /**
             * Returns the configured finding for the supplied test document.
             *
             * @param  UblXmlDocument          $document Document supplied by the validation pipeline.
             * @return UblValidationStepResult Stage result containing the configured finding.
             */
            public function validate(UblXmlDocument $document): UblValidationStepResult
            {
                return new UblValidationStepResult(new LimitedValidationIssues([$this->issue], 0));
            }
        };

        $result = (new LocalUblValidator([$stage]))->validateDocument($this->document());

        $this->assertFalse($result->isValid());
        $this->assertSame('custom_quality_check', $result->issues()[0]->stageId());
        $this->assertSame($issue, $result->issuesForStep('custom_quality_check')[0]);
        $this->assertSame(0, $result->omittedCountForStep('custom_quality_check'));
    }

    public function testCarriesRuleGuidanceAndProvenanceThroughTheFullPipeline(): void
    {
        $guidance = new UblValidationGuidance(
            'https://test-docs.peppol.eu/poacc/billing/3.0/2026-Q4-MR/rules/ubl-tc434/BR-02/',
            'Provide the invoice or credit note identifier in cbc:ID.'
        );
        $issue = new PeppolValidationIssue(
            'BR-02',
            'An invoice identifier is required.',
            '/Invoice/cbc:ID',
            null,
            UblValidationSeverity::warning(),
            $guidance
        );
        $stage = new class ($issue) implements UblValidationStageInterface, UblValidationProvenanceProviderInterface {
            private PeppolValidationIssue $issue;

            /** @param PeppolValidationIssue $issue Finding returned by this validation-stage fixture. */
            public function __construct(PeppolValidationIssue $issue)
            {
                $this->issue = $issue;
            }

            /** {@inheritdoc} */
            public function id(): string
            {
                return 'guided_schematron';
            }

            /** {@inheritdoc} */
            public function ruleVersions(): array
            {
                return ['peppol_rules' => '2026-Q4-MR'];
            }

            /** {@inheritdoc} */
            public function provenance(UblXmlDocument $document): array
            {
                return ['stylesheet_sha256' => str_repeat('a', 64), 'runtime' => 'Saxon-HE 12.10'];
            }

            /** {@inheritdoc} */
            public function validate(UblXmlDocument $document): UblValidationStepResult
            {
                return new UblValidationStepResult(new LimitedValidationIssues([$this->issue], 0));
            }
        };

        $result   = (new LocalUblValidator([$stage]))->validateDocument($this->document());
        $finding  = $result->issues()[0];
        $metadata = $result->stepResults()['guided_schematron']->metadata();

        $this->assertTrue($result->isValid());
        $this->assertSame($guidance->toArray(), $finding->toArray()['guidance']);
        $this->assertSame(['peppol_rules' => '2026-Q4-MR'], $metadata->ruleVersions());
        $this->assertSame([
            'stylesheet_sha256' => str_repeat('a', 64),
            'runtime'           => 'Saxon-HE 12.10',
        ], $metadata->provenance());
        $this->assertSame($metadata->provenance(), $metadata->toArray()['provenance']);
    }

    public function testSchemaInfrastructureFailureIsRecordedAndSchematronStillRuns(): void
    {
        $document        = $this->document();
        $schemaValidator = $this->createMock(UblSchemaValidationInterface::class);
        $schemaValidator->expects($this->once())
            ->method('validateDocument')
            ->willThrowException(new LocalValidationInfrastructureException('Synthetic XSD setup failure.'));

        $schematronValidator = $this->createMock(PeppolSchematronValidationInterface::class);
        $schematronValidator->expects($this->once())
            ->method('validateDocument')
            ->with($this->identicalTo($document))
            ->willReturn(new PeppolSchematronValidationResult());

        $result = $this->validator($schemaValidator, $schematronValidator)->validateDocument($document);

        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isComplete());
        $this->assertTrue($result->hasInfrastructureFailures());
        $this->assertSame(
            [UblSchemaValidationStage::ID => 'Synthetic XSD setup failure.'],
            $result->infrastructureFailures()
        );
        $this->assertSame(
            UblValidationStepStatus::INFRASTRUCTURE_ERROR,
            $result->stepResults()[UblSchemaValidationStage::ID]->metadata()->status()->value()
        );
    }

    public function testUnsupportedDocumentTypeStopsBeforeSchematronRuns(): void
    {
        $document        = $this->document();
        $schemaValidator = $this->createMock(UblSchemaValidationInterface::class);
        $schemaValidator->expects($this->once())
            ->method('validateDocument')
            ->willThrowException(new UnsupportedDocumentTypeException());

        $schematronValidator = $this->createMock(PeppolSchematronValidationInterface::class);
        $schematronValidator->expects($this->never())->method('validateDocument');

        $this->expectException(UnsupportedDocumentTypeException::class);
        $this->validator($schemaValidator, $schematronValidator)->validateDocument($document);
    }

    public function testSchematronInfrastructureFailureIsRecordedAfterSchemaIssuesAreFound(): void
    {
        $document        = $this->document();
        $schemaValidator = $this->createMock(UblSchemaValidationInterface::class);
        $schemaValidator->expects($this->once())
            ->method('validateDocument')
            ->willThrowException(new SchemaValidationException(
                '2.1',
                [new UblSchemaValidationIssue(2, 4, 'Synthetic schema issue.')]
            ));

        $schematronValidator = $this->createMock(PeppolSchematronValidationInterface::class);
        $schematronValidator->expects($this->once())
            ->method('validateDocument')
            ->willThrowException(new LocalValidationInfrastructureException('Synthetic Saxon failure.'));

        $result = $this->validator($schemaValidator, $schematronValidator)->validateDocument($document);

        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isComplete());
        $this->assertTrue($result->hasInfrastructureFailures());
        $this->assertSame(
            [PeppolSchematronValidationStage::ID => 'Synthetic Saxon failure.'],
            $result->infrastructureFailures()
        );
        $this->assertCount(1, $result->issues());
        $this->assertSame(UblSchemaValidationStage::ID, $result->issues()[0]->stageId());
        $this->assertSame(
            UblValidationStepStatus::INFRASTRUCTURE_ERROR,
            $result->stepResults()[PeppolSchematronValidationStage::ID]->metadata()->status()->value()
        );
    }

    /** Builds a local validator with the supplied schema and Schematron mocks. */
    private function validator(
        UblSchemaValidationInterface $schemaValidator,
        PeppolSchematronValidationInterface $schematronValidator
    ): LocalUblValidator {
        $schemaValidator->method('ruleVersions')->willReturn([]);
        $schematronValidator->method('ruleVersions')->willReturn([]);

        return new LocalUblValidator([
            new UblSchemaValidationStage($schemaValidator),
            new PeppolSchematronValidationStage($schematronValidator),
        ]);
    }

    private function document(): UblXmlDocument
    {
        $xml = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" />';

        return UblXmlDocument::fromXml($xml);
    }

    /** @param PeppolValidationIssue[] $issues */
    private function peppolResult(array $issues = [], int $omittedCount = 0): PeppolSchematronValidationResult
    {
        return new PeppolSchematronValidationResult(new LimitedValidationIssues(
            $issues,
            $omittedCount,
            PeppolValidationIssue::class
        ));
    }

    /** @param UblValidationIssueInterface[] $issues */
    private function stepResult(array $issues = [], int $omittedCount = 0): UblValidationStepResult
    {
        return new UblValidationStepResult(new LimitedValidationIssues($issues, $omittedCount));
    }
}
