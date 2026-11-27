<?php

namespace DreaM1\EfakturaConnector\Core\Configuration\Internal;

/** @internal Immutable group of local UBL and Schematron runtime limits. */
final class LocalValidationRuntimeSettings
{
    private int $processTimeoutSeconds;
    private int $maxConcurrentValidations;
    private int $maxIssues;
    private int $maxReportBytes;
    private int $maxErrorOutputBytes;
    private int $maxErrorDetailCharacters;
    private ?int $javaMaxHeapMegabytes;

    /**
     * Groups the validated process, concurrency and output limits for local validation.
     *
     * @param int $processTimeoutSeconds    Maximum runtime of one Schematron process, in seconds.
     * @param int $maxConcurrentValidations Maximum local Schematron processes allowed at once.
     * @param int $maxIssues                Maximum findings retained by each XSD or Schematron validator.
     * @param int $maxReportBytes           Maximum Schematron report size accepted, in bytes.
     * @param int $maxErrorOutputBytes      Maximum captured process error output, in bytes.
     * @param int $maxErrorDetailCharacters Maximum characters retained for one process error detail.
     */
    public function __construct(
        int $processTimeoutSeconds,
        int $maxConcurrentValidations,
        int $maxIssues,
        int $maxReportBytes,
        int $maxErrorOutputBytes,
        int $maxErrorDetailCharacters,
        ?int $javaMaxHeapMegabytes = null
    ) {
        $this->processTimeoutSeconds    = $processTimeoutSeconds;
        $this->maxConcurrentValidations = $maxConcurrentValidations;
        $this->maxIssues                = $maxIssues;
        $this->maxReportBytes           = $maxReportBytes;
        $this->maxErrorOutputBytes      = $maxErrorOutputBytes;
        $this->maxErrorDetailCharacters = $maxErrorDetailCharacters;
        $this->javaMaxHeapMegabytes     = $javaMaxHeapMegabytes;
    }

    /** Returns the local Schematron process timeout in seconds. */
    public function processTimeoutSeconds(): int
    {
        return $this->processTimeoutSeconds;
    }

    /** Returns the maximum number of concurrent local Schematron processes. */
    public function maxConcurrentValidations(): int
    {
        return $this->maxConcurrentValidations;
    }

    /** Returns the maximum number of findings retained per XSD or Schematron validator. */
    public function maxIssues(): int
    {
        return $this->maxIssues;
    }

    /** Returns the maximum accepted Schematron report size in bytes. */
    public function maxReportBytes(): int
    {
        return $this->maxReportBytes;
    }

    /** Returns the maximum captured process error output in bytes. */
    public function maxErrorOutputBytes(): int
    {
        return $this->maxErrorOutputBytes;
    }

    /** Returns the maximum characters retained for one process error detail. */
    public function maxErrorDetailCharacters(): int
    {
        return $this->maxErrorDetailCharacters;
    }

    /** Returns the Java heap limit for Schematron processes in megabytes, or null for the JVM default. */
    public function javaMaxHeapMegabytes(): ?int
    {
        return $this->javaMaxHeapMegabytes;
    }
}
