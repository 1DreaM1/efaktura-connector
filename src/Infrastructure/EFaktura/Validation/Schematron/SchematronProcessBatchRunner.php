<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\SchematronReport;
use DreaM1\EfakturaConnector\Core\Xml\XmlEncoding;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationMessageNormalizer;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationResources;
use DreaM1\EfakturaConnector\Support\NonBlankString;

/** @internal Supervises concurrent validation processes and their private temporary files. */
final class SchematronProcessBatchRunner
{
    private const MICROSECONDS_PER_SECOND                = 1000000;
    private const PROCESS_POLL_INTERVAL_MICROSECONDS     = 10000;
    private const PROCESS_TERMINATION_GRACE_MICROSECONDS = 250000;
    private const FORCE_TERMINATION_SIGNAL               = 9;
    private const PRIVATE_TEMP_FILE_MODE                 = 0600;
    private const PROCESS_FIELD_PROCESS                  = 'process';
    private const PROCESS_FIELD_REPORT                   = 'report';
    private const PROCESS_FIELD_ERROR                    = 'error';
    private const STATUS_FIELD_RUNNING                   = 'running';
    private const STATUS_FIELD_EXIT_CODE                 = 'exitcode';

    private int $processTimeoutSeconds;
    private int $maxReportBytes;
    /** @var int<0, max> */
    private int $maxErrorOutputBytes;
    private int $maxErrorDetailCharacters;

    public function __construct(
        int $processTimeoutSeconds,
        int $maxReportBytes,
        int $maxErrorOutputBytes,
        int $maxErrorDetailCharacters
    ) {
        $this->processTimeoutSeconds    = $processTimeoutSeconds;
        $this->maxReportBytes           = $maxReportBytes;
        $this->maxErrorOutputBytes      = max(0, $maxErrorOutputBytes);
        $this->maxErrorDetailCharacters = $maxErrorDetailCharacters;
    }

    /**
     * Runs all commands concurrently and returns reports in command order.
     *
     * @param  array<int, list<string>> $commands         Process argument vectors.
     * @param  string                   $xml              Input shared by every process.
     * @param  string                   $workingDirectory Existing process working directory.
     * @return SchematronReport[]
     */
    public function execute(array $commands, string $xml, string $workingDirectory): array
    {
        if ($commands === []) {
            return [];
        }

        $temporaryFiles = [];
        $processes      = [];

        try {
            $inputPath = $this->temporaryFile('efaktura-input-', $temporaryFiles);
            $written   = @file_put_contents($inputPath, $xml);

            if ($written !== strlen($xml)) {
                throw new LocalValidationInfrastructureException(
                    'Local Schematron validation process could not be prepared.'
                );
            }

            // Start every stylesheet before polling so the checks run concurrently.
            foreach ($commands as $index => $command) {
                $reportPath        = $this->temporaryFile('efaktura-svrl-', $temporaryFiles);
                $errorPath         = $this->temporaryFile('efaktura-error-', $temporaryFiles);
                $processes[$index] = [
                    self::PROCESS_FIELD_PROCESS => $this->start(
                        $command,
                        $inputPath,
                        $reportPath,
                        $errorPath,
                        $workingDirectory
                    ),
                    self::PROCESS_FIELD_REPORT => $reportPath,
                    self::PROCESS_FIELD_ERROR  => $errorPath,
                ];
            }

            return $this->awaitAll($processes, microtime(true) + $this->processTimeoutSeconds);
        } finally {
            $this->cleanup($processes, $temporaryFiles);
        }
    }

    /**
     * @param array<int, array{process: resource, report: string, error: string}> $processes
     * @param string[]                                                            $temporaryFiles
     */
    private function cleanup(array $processes, array $temporaryFiles): void
    {
        foreach ($processes as $process) {
            $this->stop($process[self::PROCESS_FIELD_PROCESS]);
        }

        foreach ($temporaryFiles as $path) {
            @unlink($path);
        }
    }

    /**
     * Starts a child with file-backed streams and no shell interpolation.
     *
     * @param  list<string> $command Executable and arguments.
     * @return resource
     */
    private function start(
        array $command,
        string $inputPath,
        string $reportPath,
        string $errorPath,
        string $workingDirectory
    ) {
        $descriptors = [
            0 => ['file', $inputPath, 'r'],
            1 => ['file', $reportPath, 'w'],
            2 => ['file', $errorPath, 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes, $workingDirectory, null, ['bypass_shell' => true]);

        if (! is_resource($process)) {
            throw new LocalValidationInfrastructureException(sprintf(
                'Local Schematron validation could not start the process. '
                . 'Check that Java %d or later is available, PHP proc_open is enabled, '
                . 'and the working directory is accessible.',
                ValidationResources::MINIMUM_JAVA_VERSION
            ));
        }

        return $process;
    }

    /**
     * @param  array<int, array{process: resource, report: string, error: string}> $processes
     * @return SchematronReport[]
     */
    private function awaitAll(array $processes, float $deadline): array
    {
        $reports = [];

        while (count($reports) < count($processes)) {
            if (microtime(true) >= $deadline) {
                throw new LocalValidationInfrastructureException('Local Schematron validation timed out.');
            }

            foreach ($processes as $index => $process) {
                if (! isset($reports[$index])) {
                    $report = $this->finishedReport(
                        $process[self::PROCESS_FIELD_PROCESS],
                        $process[self::PROCESS_FIELD_REPORT],
                        $process[self::PROCESS_FIELD_ERROR]
                    );

                    if ($report !== null) {
                        $reports[$index] = $report;
                    }
                }
            }

            if (count($reports) < count($processes)) {
                usleep(self::PROCESS_POLL_INTERVAL_MICROSECONDS);
            }
        }

        ksort($reports);

        return array_values($reports);
    }

    /** @param resource $process */
    private function finishedReport($process, string $reportPath, string $errorPath): ?SchematronReport
    {
        $this->assertFileWithinLimit(
            $reportPath,
            $this->maxReportBytes,
            'Local Schematron validation report exceeded the safe size limit.'
        );
        $this->assertFileWithinLimit(
            $errorPath,
            $this->maxErrorOutputBytes,
            'Local Schematron validation process error output exceeded the safe size limit.'
        );

        $status = @proc_get_status($process);

        if (! is_array($status)) {
            throw new LocalValidationInfrastructureException('Local Schematron validation process status is unavailable.');
        }

        if ($status[self::STATUS_FIELD_RUNNING]) {
            return null;
        }

        // Recheck after exit so a final child write cannot exceed the limit before the file is read.
        $this->assertFileWithinLimit(
            $reportPath,
            $this->maxReportBytes,
            'Local Schematron validation report exceeded the safe size limit.'
        );
        $this->assertFileWithinLimit(
            $errorPath,
            $this->maxErrorOutputBytes,
            'Local Schematron validation process error output exceeded the safe size limit.'
        );

        $report = @file_get_contents($reportPath);

        if ($report === false) {
            throw new LocalValidationInfrastructureException('Local Schematron validation returned an invalid report.');
        }

        if ($status[self::STATUS_FIELD_EXIT_CODE] !== 0) {
            $message = sprintf(
                'Local Schematron validation process failed with exit code %d.',
                $status[self::STATUS_FIELD_EXIT_CODE]
            );
            $detail = $this->processErrorDetail($errorPath);

            if ($detail !== '') {
                $message .= ' Saxon: ' . $detail;
            }

            throw new LocalValidationInfrastructureException($message);
        }

        if (! NonBlankString::isNotBlank($report)) {
            throw new LocalValidationInfrastructureException('Local Schematron validation returned an empty report.');
        }

        return new SchematronReport($report);
    }

    private function assertFileWithinLimit(string $path, int $maxBytes, string $message): void
    {
        clearstatcache(true, $path);
        $size = @filesize($path);

        if ($size === false || $size > $maxBytes) {
            throw new LocalValidationInfrastructureException($message);
        }
    }

    /** @param resource $process */
    private function stop($process): void
    {
        if (! is_resource($process)) {
            return;
        }

        $status = @proc_get_status($process);

        if (! is_array($status)) {
            @proc_terminate($process, self::FORCE_TERMINATION_SIGNAL);
        } elseif ($status[self::STATUS_FIELD_RUNNING]) {
            @proc_terminate($process);
            $terminationDeadline = microtime(true)
                + self::PROCESS_TERMINATION_GRACE_MICROSECONDS / self::MICROSECONDS_PER_SECOND;
            do {
                usleep(self::PROCESS_POLL_INTERVAL_MICROSECONDS);
                $status = @proc_get_status($process);
            } while (
                is_array($status)
                && $status[self::STATUS_FIELD_RUNNING]
                && microtime(true) < $terminationDeadline
            );

            if (! is_array($status) || $status[self::STATUS_FIELD_RUNNING]) {
                @proc_terminate($process, self::FORCE_TERMINATION_SIGNAL);
            }
        }

        @proc_close($process);
    }

    /**
     * Creates a private temporary file and records it for cleanup.
     *
     * @param string[] $temporaryFiles Paths removed after the run.
     */
    private function temporaryFile(string $prefix, array &$temporaryFiles): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);

        if ($path === false) {
            throw new LocalValidationInfrastructureException('Local Schematron validation process could not be prepared.');
        }

        @chmod($path, self::PRIVATE_TEMP_FILE_MODE);
        $temporaryFiles[] = $path;

        return $path;
    }

    private function processErrorDetail(string $errorPath): string
    {
        $error = @file_get_contents($errorPath, false, null, 0, $this->maxErrorOutputBytes);

        if ($error === false) {
            return '';
        }

        $error = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $error);

        if ($error === null) {
            return '';
        }

        $error = ValidationMessageNormalizer::normalize($error);
        $error = mb_scrub($error, XmlEncoding::UTF_8);

        if (mb_strlen($error, XmlEncoding::UTF_8) > $this->maxErrorDetailCharacters) {
            $error = mb_substr($error, 0, $this->maxErrorDetailCharacters - 1, XmlEncoding::UTF_8) . '…';
        }

        return $error;
    }
}
