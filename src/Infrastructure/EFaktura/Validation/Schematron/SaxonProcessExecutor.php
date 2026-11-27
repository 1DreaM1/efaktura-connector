<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron;

use DreaM1\EfakturaConnector\Core\Configuration\ConnectorRuntimeConfiguration;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\SchematronReport;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\SafeConnectorMetrics;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Acquires validation capacity and records outcomes for bounded Saxon process batches. */
final class SaxonProcessExecutor
{
    public const DEFAULT_PROCESS_TIMEOUT_SECONDS     = ConnectorRuntimeConfiguration::DEFAULT_SCHEMATRON_PROCESS_TIMEOUT_SECONDS;
    public const DEFAULT_MAX_CONCURRENT_VALIDATIONS  = ConnectorRuntimeConfiguration::DEFAULT_SCHEMATRON_MAX_CONCURRENT_VALIDATIONS;
    public const DEFAULT_MAX_REPORT_SIZE_BYTES       = ConnectorRuntimeConfiguration::DEFAULT_SCHEMATRON_MAX_REPORT_BYTES;
    public const DEFAULT_MAX_ERROR_OUTPUT_BYTES      = ConnectorRuntimeConfiguration::DEFAULT_SCHEMATRON_MAX_ERROR_OUTPUT_BYTES;
    public const DEFAULT_MAX_ERROR_DETAIL_CHARACTERS = ConnectorRuntimeConfiguration::DEFAULT_SCHEMATRON_MAX_ERROR_DETAIL_CHARACTERS;
    public const MAX_REPORT_SIZE_BYTES               = ConnectorRuntimeConfiguration::MAX_CONFIGURED_SCHEMATRON_REPORT_BYTES;
    public const MAX_ERROR_OUTPUT_BYTES              = ConnectorRuntimeConfiguration::MAX_CONFIGURED_SCHEMATRON_ERROR_OUTPUT_BYTES;
    public const MAX_ERROR_DETAIL_CHARACTERS         = ConnectorRuntimeConfiguration::MAX_CONFIGURED_SCHEMATRON_ERROR_DETAIL_CHARACTERS;

    private int $processTimeoutSeconds;
    private SchematronConcurrencyLimiterInterface $concurrencyLimiter;
    private ?ConnectorMetricsInterface $metrics;
    private SchematronProcessBatchRunner $batchRunner;

    /**
     * @throws InvalidInputException when a timeout, concurrency or output limit is outside the supported range.
     */
    public function __construct(
        int $processTimeoutSeconds = self::DEFAULT_PROCESS_TIMEOUT_SECONDS,
        int $maxConcurrentValidations = self::DEFAULT_MAX_CONCURRENT_VALIDATIONS,
        ?SchematronConcurrencyLimiterInterface $concurrencyLimiter = null,
        ?ConnectorMetricsInterface $metrics = null,
        int $maxReportBytes = self::DEFAULT_MAX_REPORT_SIZE_BYTES,
        int $maxErrorOutputBytes = self::DEFAULT_MAX_ERROR_OUTPUT_BYTES,
        int $maxErrorDetailCharacters = self::DEFAULT_MAX_ERROR_DETAIL_CHARACTERS
    ) {
        if ($processTimeoutSeconds < 1) {
            throw new InvalidInputException('Process timeout must be greater than zero.');
        }

        if ($maxConcurrentValidations < 1) {
            throw new InvalidInputException('Maximum concurrent Schematron validations must be positive.');
        }

        if (
            $maxReportBytes < 1
            || $maxReportBytes > self::MAX_REPORT_SIZE_BYTES
            || $maxErrorOutputBytes < 1
            || $maxErrorOutputBytes > self::MAX_ERROR_OUTPUT_BYTES
            || $maxErrorDetailCharacters < 1
            || $maxErrorDetailCharacters > self::MAX_ERROR_DETAIL_CHARACTERS
        ) {
            throw new InvalidInputException('Schematron output limits are outside the supported range.');
        }

        $this->processTimeoutSeconds = $processTimeoutSeconds;
        $this->concurrencyLimiter    = $concurrencyLimiter
            ?? new FileSchematronConcurrencyLimiter($maxConcurrentValidations);
        $this->metrics     = $metrics;
        $this->batchRunner = new SchematronProcessBatchRunner(
            $processTimeoutSeconds,
            $maxReportBytes,
            $maxErrorOutputBytes,
            $maxErrorDetailCharacters
        );
    }

    /**
     * Runs commands concurrently while holding one validation slot for the full process lifecycle.
     *
     * @param  array<int, list<string>>               $commands         Process command argument lists.
     * @param  string                                 $xml              UBL XML passed to each process on standard input.
     * @param  string                                 $workingDirectory Existing working directory for the processes.
     * @return SchematronReport[]                     Reports in the same order as the commands.
     * @throws LocalValidationInfrastructureException when process execution cannot be completed safely.
     */
    public function execute(array $commands, string $xml, string $workingDirectory): array
    {
        if ($commands === []) {
            return [];
        }

        $startedAt = microtime(true);
        $outcome   = ConnectorMetricsInterface::SCHEMATRON_FAILURE;
        $permit    = null;

        try {
            if (! function_exists('proc_open')) {
                throw new LocalValidationInfrastructureException(
                    'Local Schematron validation requires the PHP proc_open function.'
                );
            }

            if (! is_dir($workingDirectory) || ! is_readable($workingDirectory)) {
                throw new LocalValidationInfrastructureException(
                    'Local Schematron validation working directory is unavailable.'
                );
            }

            $permit  = $this->concurrencyLimiter->acquire($workingDirectory, $this->processTimeoutSeconds);
            $reports = $this->batchRunner->execute($commands, $xml, $workingDirectory);
            $outcome = ConnectorMetricsInterface::SCHEMATRON_SUCCESS;

            return $reports;
        } finally {
            try {
                if ($permit !== null) {
                    $permit->release();
                }
            } finally {
                SafeConnectorMetrics::schematronValidation(
                    $this->metrics,
                    max(0.0, microtime(true) - $startedAt),
                    $outcome,
                    count($commands)
                );
            }
        }
    }
}
