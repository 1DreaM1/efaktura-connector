<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Validation\Schematron;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\SaxonProcessExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\SaxonProcessExecutorTestControl;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\SchematronConcurrencyLimiterInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\SchematronConcurrencyPermitInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\SchematronProcessBatchRunner;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Tests\Support\RecordingConnectorMetrics;
use DreaM1\EfakturaConnector\Tests\Support\ReflectionAccess;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

require_once ResourcePath::join(
    __DIR__,
    '../../../../../Support/NamespaceOverrides/EFaktura/Validation/Schematron/SaxonProcessExecutorTestControl.php'
);

/** Tests process execution and output limits for Saxon validation. */
class SaxonProcessExecutorTest extends TestCase
{
    protected function setUp(): void
    {
        SaxonProcessExecutorTestControl::reset();
    }

    protected function tearDown(): void
    {
        SaxonProcessExecutorTestControl::reset();
    }

    public function testEveryCommandReadsTheSameInputAndReturnsItsOwnOutputInOrder(): void
    {
        $this->requireProcessSupport();
        $input = '<Invoice>synthetic</Invoice>';

        $reports = (new SaxonProcessExecutor())->execute(
            [
                [PHP_BINARY, '-r', 'usleep(200000); echo "first:", stream_get_contents(STDIN);'],
                [PHP_BINARY, '-r', 'echo "second:", stream_get_contents(STDIN);'],
            ],
            $input,
            sys_get_temp_dir()
        );

        $this->assertSame(['first:' . $input, 'second:' . $input], [$reports[0]->xml(), $reports[1]->xml()]);
    }

    public function testRecordsSuccessfulSchematronBatchDurationAndRulesetCount(): void
    {
        $this->requireProcessSupport();
        $metrics = new RecordingConnectorMetrics();

        (new SaxonProcessExecutor(
            SaxonProcessExecutor::DEFAULT_PROCESS_TIMEOUT_SECONDS,
            SaxonProcessExecutor::DEFAULT_MAX_CONCURRENT_VALIDATIONS,
            null,
            $metrics
        ))->execute(
            [
                [PHP_BINARY, '-r', 'echo "first report";'],
                [PHP_BINARY, '-r', 'echo "second report";'],
            ],
            '<Invoice/>',
            sys_get_temp_dir()
        );

        $this->assertCount(1, $metrics->schematronValidations);
        $this->assertSame(ConnectorMetricsInterface::SCHEMATRON_SUCCESS, $metrics->schematronValidations[0]['outcome']);
        $this->assertSame(2, $metrics->schematronValidations[0]['ruleset_count']);
        $this->assertGreaterThanOrEqual(0.0, $metrics->schematronValidations[0]['duration_seconds']);
    }

    public function testRecordsFailedSchematronBatchWhenProcessFails(): void
    {
        $this->requireProcessSupport();
        $metrics = new RecordingConnectorMetrics();

        try {
            (new SaxonProcessExecutor(
                SaxonProcessExecutor::DEFAULT_PROCESS_TIMEOUT_SECONDS,
                SaxonProcessExecutor::DEFAULT_MAX_CONCURRENT_VALIDATIONS,
                null,
                $metrics
            ))->execute([[PHP_BINARY, '-r', 'exit(7);']], '<Invoice/>', sys_get_temp_dir());
            $this->fail('Expected a non-zero process exit to be rejected.');
        } catch (LocalValidationInfrastructureException $exception) {
            $this->assertStringContainsString('process failed with exit code 7', $exception->getMessage());
        }

        $this->assertCount(1, $metrics->schematronValidations);
        $this->assertSame(ConnectorMetricsInterface::SCHEMATRON_FAILURE, $metrics->schematronValidations[0]['outcome']);
        $this->assertSame(1, $metrics->schematronValidations[0]['ruleset_count']);
    }

    public function testIncludesBoundedErrorOutputWhenAProcessFails(): void
    {
        $this->requireProcessSupport();

        try {
            (new SaxonProcessExecutor())->execute(
                [[PHP_BINARY, '-r', 'fwrite(STDERR, "synthetic transformation failure " . str_repeat("x", 2000)); exit(7);']],
                '<Invoice/>',
                sys_get_temp_dir()
            );
            $this->fail('Expected a non-zero process exit to be rejected.');
        } catch (LocalValidationInfrastructureException $exception) {
            $this->assertStringContainsString('process failed with exit code 7', $exception->getMessage());
            $this->assertStringContainsString('synthetic transformation failure', $exception->getMessage());
            $this->assertLessThan(700, strlen($exception->getMessage()));
            $this->assertStringEndsWith('…', $exception->getMessage());

            $detailStart = strpos($exception->getMessage(), 'Saxon: ');
            $this->assertNotFalse($detailStart);
            $detail = substr($exception->getMessage(), $detailStart + strlen('Saxon: '));
            $this->assertSame(
                SaxonProcessExecutor::DEFAULT_MAX_ERROR_DETAIL_CHARACTERS,
                mb_strlen($detail, 'UTF-8')
            );
        }
    }

    public function testSanitizesAndTruncatesErrorDetailsWhenAProcessFails(): void
    {
        $this->requireProcessSupport();
        $error = "synthetic\0\nfailure " . str_repeat('x', 600);
        // Base64 keeps NUL and newline bytes out of the child command while preserving them in stderr.
        $encodedError = base64_encode($error);

        try {
            (new SaxonProcessExecutor())->execute(
                [[PHP_BINARY, '-r', 'fwrite(STDERR, base64_decode(' . var_export($encodedError, true) . ')); exit(7);']],
                '<Invoice/>',
                sys_get_temp_dir()
            );
            $this->fail('Expected a non-zero process exit to be rejected.');
        } catch (LocalValidationInfrastructureException $exception) {
            $message     = $exception->getMessage();
            $detailStart = strpos($message, 'Saxon: ');
            $this->assertNotFalse($detailStart);
            $detail = substr($message, $detailStart + strlen('Saxon: '));

            $this->assertStringStartsWith('synthetic failure ', $detail);
            $this->assertSame(
                SaxonProcessExecutor::DEFAULT_MAX_ERROR_DETAIL_CHARACTERS,
                mb_strlen($detail, 'UTF-8')
            );
            $this->assertStringNotContainsString("\0", $detail);
            $this->assertStringNotContainsString("\n", $detail);
        }
    }

    public function testTruncatesUnicodeErrorDetailsOnCharacterBoundaries(): void
    {
        $this->requireProcessSupport();
        $error        = 'Unicode failure: ' . str_repeat('ž', 20);
        $encodedError = base64_encode($error);
        $executor     = new SaxonProcessExecutor(
            SaxonProcessExecutor::DEFAULT_PROCESS_TIMEOUT_SECONDS,
            SaxonProcessExecutor::DEFAULT_MAX_CONCURRENT_VALIDATIONS,
            null,
            null,
            SaxonProcessExecutor::DEFAULT_MAX_REPORT_SIZE_BYTES,
            SaxonProcessExecutor::DEFAULT_MAX_ERROR_OUTPUT_BYTES,
            12
        );

        try {
            $executor->execute(
                [[PHP_BINARY, '-r', 'fwrite(STDERR, base64_decode(' . var_export($encodedError, true) . ')); exit(7);']],
                '<Invoice/>',
                sys_get_temp_dir()
            );
            $this->fail('Expected a non-zero process exit to be rejected.');
        } catch (LocalValidationInfrastructureException $exception) {
            $message     = $exception->getMessage();
            $detailStart = strpos($message, 'Saxon: ');
            $this->assertNotFalse($detailStart);
            $detail = substr($message, $detailStart + strlen('Saxon: '));

            $this->assertTrue(mb_check_encoding($detail, 'UTF-8'));
            $this->assertSame(12, mb_strlen($detail, 'UTF-8'));
            $this->assertSame('Unicode fai…', $detail);
        }
    }

    public function testRejectsErrorOutputLargerThanTheConfiguredLimit(): void
    {
        $this->requireProcessSupport();

        $this->expectException(LocalValidationInfrastructureException::class);
        $this->expectExceptionMessage('process error output exceeded the safe size limit');

        (new SaxonProcessExecutor())->execute(
            [[PHP_BINARY, '-r', 'fwrite(STDERR, str_repeat("x", 65537)); exit(7);']],
            '<Invoice/>',
            sys_get_temp_dir()
        );
    }

    public function testRejectsAnEmptyReportFromASuccessfulProcess(): void
    {
        $this->requireProcessSupport();

        $this->expectException(LocalValidationInfrastructureException::class);
        $this->expectExceptionMessage('returned an empty report');

        (new SaxonProcessExecutor())->execute(
            [[PHP_BINARY, '-r', 'exit(0);']],
            '<Invoice/>',
            sys_get_temp_dir()
        );
    }

    public function testRejectsAnUnavailableWorkingDirectoryBeforeStartingAProcess(): void
    {
        $this->requireProcessSupport();
        $workingDirectory = ResourcePath::join(sys_get_temp_dir(), 'efaktura-missing-' . uniqid());

        $this->expectException(LocalValidationInfrastructureException::class);
        $this->expectExceptionMessage('working directory is unavailable');

        (new SaxonProcessExecutor())->execute(
            [[PHP_BINARY, '-r', 'echo "should not run";']],
            '<Invoice/>',
            $workingDirectory
        );
    }

    public function testReturnsNoReportsForAnEmptyCommandList(): void
    {
        $reports = (new SaxonProcessExecutor())->execute([], '<Invoice/>', 'missing-working-directory');

        $this->assertSame([], $reports);
    }

    public function testProcessTimeoutAppliesToSlowConsumerWithLargeInput(): void
    {
        $this->requireProcessSupport();
        $startedAt = hrtime(true);

        // The child deliberately never reads its 1 MiB stdin; the shared deadline must bound this stalled process.
        try {
            (new SaxonProcessExecutor(1))->execute(
                [[PHP_BINARY, '-r', 'usleep(4000000);']],
                str_repeat('x', 1024 * 1024),
                sys_get_temp_dir()
            );
            $this->fail('Expected the slow process to respect its timeout.');
        } catch (LocalValidationInfrastructureException $exception) {
            $elapsedSeconds = (hrtime(true) - $startedAt) / 1000000000;

            $this->assertGreaterThanOrEqual(0.8, $elapsedSeconds);
            $this->assertLessThan(3, $elapsedSeconds);
        }
    }

    public function testConcurrencyPermitIsReleasedWhenAProcessFails(): void
    {
        $this->requireProcessSupport();
        $permit = $this->createMock(SchematronConcurrencyPermitInterface::class);
        $permit->expects($this->once())->method('release');
        $limiter = $this->createMock(SchematronConcurrencyLimiterInterface::class);
        $limiter->expects($this->once())->method('acquire')
            ->with(sys_get_temp_dir(), 5)
            ->willReturn($permit);

        $this->expectException(LocalValidationInfrastructureException::class);
        $this->expectExceptionMessage('process failed with exit code 7');

        (new SaxonProcessExecutor(5, 1, $limiter))->execute(
            [[PHP_BINARY, '-r', 'exit(7);']],
            '<Invoice/>',
            sys_get_temp_dir()
        );
    }

    public function testRejectsNonPositiveProcessTimeout(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SaxonProcessExecutor(0);
    }

    public function testOutputLimitsCannotExceedTheSupportedSafetyCaps(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SaxonProcessExecutor(
            60,
            2,
            null,
            null,
            SaxonProcessExecutor::MAX_REPORT_SIZE_BYTES + 1
        );
    }

    public function testCleanupIgnoresANonResourceProcessHandle(): void
    {
        $runner = new SchematronProcessBatchRunner(60, 32 * 1024 * 1024, 64 * 1024, 512);
        $stop   = new ReflectionMethod($runner, 'stop');
        ReflectionAccess::makeAccessible($stop);
        $stop->invoke($runner, false);

        $this->addToAssertionCount(1);
    }

    public function testReportsFailureWhenTheOperatingSystemCannotStartTheProcess(): void
    {
        SaxonProcessExecutorTestControl::$failProcessStart = true;

        $this->expectException(LocalValidationInfrastructureException::class);
        $this->expectExceptionMessage('could not start the process');

        (new SaxonProcessExecutor())->execute([['synthetic-java']], '<Invoice/>', sys_get_temp_dir());
    }

    public function testReportsWhenPhpDoesNotProvideProcessSupport(): void
    {
        SaxonProcessExecutorTestControl::$disableProcessFunction = true;

        $this->expectException(LocalValidationInfrastructureException::class);
        $this->expectExceptionMessage('requires the PHP proc_open function');

        (new SaxonProcessExecutor())->execute([['unused']], '<Invoice/>', sys_get_temp_dir());
    }

    public function testReportsTemporaryFileCreationAndShortWriteFailures(): void
    {
        SaxonProcessExecutorTestControl::$failTemporaryFile = true;
        try {
            (new SaxonProcessExecutor())->execute([['unused']], '<Invoice/>', sys_get_temp_dir());
            $this->fail('A temporary file creation failure must be reported.');
        } catch (LocalValidationInfrastructureException $exception) {
            $this->assertStringContainsString('could not be prepared', $exception->getMessage());
        }

        SaxonProcessExecutorTestControl::reset();
        SaxonProcessExecutorTestControl::$shortInputWrite = true;
        $this->expectException(LocalValidationInfrastructureException::class);
        $this->expectExceptionMessage('could not be prepared');

        (new SaxonProcessExecutor())->execute([['unused']], '<Invoice/>', sys_get_temp_dir());
    }

    public function testReportsAnUnavailableProcessStatus(): void
    {
        $this->requireProcessSupport();
        SaxonProcessExecutorTestControl::$hideProcessStatus = true;

        $this->expectException(LocalValidationInfrastructureException::class);
        $this->expectExceptionMessage('process status is unavailable');

        (new SaxonProcessExecutor())->execute(
            [[PHP_BINARY, '-r', 'echo "synthetic report";']],
            '<Invoice/>',
            sys_get_temp_dir()
        );
    }

    public function testReportsUnreadableProcessReportsAndContinuesWithoutUnreadableErrorDetails(): void
    {
        $this->requireProcessSupport();
        SaxonProcessExecutorTestControl::$hideReport = true;
        try {
            (new SaxonProcessExecutor())->execute(
                [[PHP_BINARY, '-r', 'echo "synthetic report";']],
                '<Invoice/>',
                sys_get_temp_dir()
            );
            $this->fail('An unreadable report must be rejected.');
        } catch (LocalValidationInfrastructureException $exception) {
            $this->assertStringContainsString('returned an invalid report', $exception->getMessage());
        }

        SaxonProcessExecutorTestControl::reset();
        SaxonProcessExecutorTestControl::$hideProcessError = true;
        try {
            (new SaxonProcessExecutor())->execute(
                [[PHP_BINARY, '-r', 'fwrite(STDERR, "hidden detail"); exit(9);']],
                '<Invoice/>',
                sys_get_temp_dir()
            );
            $this->fail('A non-zero process exit must still be reported.');
        } catch (LocalValidationInfrastructureException $exception) {
            $this->assertStringContainsString('process failed with exit code 9', $exception->getMessage());
            $this->assertStringNotContainsString('Saxon:', $exception->getMessage());
        }
    }

    private function requireProcessSupport(): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('PHP proc_open is unavailable.');
        }
    }
}
