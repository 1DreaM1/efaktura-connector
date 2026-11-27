<?php

namespace DreaM1\EfakturaConnector\Core\Configuration;

/**
 * Framework-independent resource limits for local UBL validation (Schematron execution and findings).
 * Intended for consumption by host applications; implementing it in a host class is not supported and new methods may be added in minor releases.
 */
interface LocalValidationRuntimeConfigurationInterface
{
    /** Maximum execution time for one local Schematron process, in seconds. */
    public function schematronProcessTimeoutSeconds(): int;

    /** Maximum number of local Schematron validation processes allowed to run concurrently. */
    public function schematronMaxConcurrentValidations(): int;

    /** Maximum number of findings retained by each local validation stage. */
    public function validationMaxIssues(): int;

    /** Maximum Schematron report size accepted from the local process, in bytes. */
    public function schematronMaxReportBytes(): int;

    /** Maximum captured process error output from Schematron execution, in bytes. */
    public function schematronMaxErrorOutputBytes(): int;

    /** Maximum characters retained for one Schematron process error detail. */
    public function schematronMaxErrorDetailCharacters(): int;

    /** Returns the Java heap limit for each Schematron process in megabytes, or null to use the JVM default. */
    public function schematronJavaMaxHeapMegabytes(): ?int;
}
