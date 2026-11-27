<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationResources;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * @internal Locates a Java executable and verifies its version for local Schematron validation.
 *
 * Only `java -version` is executed. Nothing is sent to the provider.
 */
final class JavaRuntimeProbe
{
    /** Default maximum time allowed for the Java version probe, in seconds. */
    private const VERSION_TIMEOUT_SECONDS = 5;
    /** Maximum total stdout and stderr bytes retained from the Java version process. */
    private const VERSION_MAX_OUTPUT_BYTES = 4096;
    /** Delay between checks that the Java version process has exited, in microseconds. */
    private const VERSION_POLL_INTERVAL_MICROSECONDS = 50000;

    private int $versionTimeoutSeconds;

    /**
     * Creates a probe with a bounded version check.
     *
     * @param  int                   $versionTimeoutSeconds Maximum time allowed for `java -version`, in seconds.
     * @throws InvalidInputException when the timeout is not positive.
     */
    public function __construct(int $versionTimeoutSeconds = self::VERSION_TIMEOUT_SECONDS)
    {
        if ($versionTimeoutSeconds < 1) {
            throw new InvalidInputException('The Java version check timeout must be positive.');
        }

        $this->versionTimeoutSeconds = $versionTimeoutSeconds;
    }

    /**
     * Resolves an explicitly configured Java path, JAVA_HOME, or a PATH entry.
     *
     * @param  string|null $configuredBinary Java executable name or path, or null for automatic discovery.
     * @return string|null Path of an existing executable, or null when none was found.
     */
    public function find(?string $configuredBinary): ?string
    {
        $candidates = [];
        $windows    = DIRECTORY_SEPARATOR === '\\';
        $binaryName = $configuredBinary ?? ($windows ? 'java.exe' : 'java');
        $hasPath    = strpos($binaryName, '/') !== false || strpos($binaryName, '\\') !== false;

        if ($hasPath) {
            $candidates[] = $binaryName;
        } else {
            if ($configuredBinary === null) {
                $javaHome = getenv('JAVA_HOME');

                if (is_string($javaHome) && $javaHome !== '') {
                    $candidates[] = rtrim($javaHome, '/\\') . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR
                        . $binaryName;
                }
            }

            if ($windows && pathinfo($binaryName, PATHINFO_EXTENSION) === '') {
                $binaryName .= '.exe';
            }

            $path = getenv('PATH');

            foreach (explode(PATH_SEPARATOR, is_string($path) ? $path : '') as $directory) {
                if ($directory !== '') {
                    $candidates[] = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $binaryName;
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && ($windows || is_executable($candidate))) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Executes `java -version` and rejects runtimes older than the supported minimum.
     *
     * @param  string                                 $binary Path of the Java executable to probe.
     * @return int                                    Detected major Java version.
     * @throws LocalValidationInfrastructureException when Java cannot run, times out or is too old.
     */
    public function assertSupportedVersion(string $binary): int
    {
        // proc_open() reports a missing explicit path differently across PHP versions (a failure before PHP 8.3,
        // a child exit code afterwards), so reject it up front with one consistent message.
        if (strpbrk($binary, '/\\') !== false && ! is_file($binary)) {
            throw new LocalValidationInfrastructureException('The Java executable could not be started.');
        }

        $temporaryFiles = [];
        $process        = null;

        try {
            // File-backed streams keep the timeout enforceable: pipes from proc_open cannot be read without
            // blocking on Windows, so a hung runtime would otherwise stall PHP past the deadline.
            $stdoutPath = $this->temporaryFile($temporaryFiles);
            $stderrPath = $this->temporaryFile($temporaryFiles);
            $pipes      = [];
            $process    = @proc_open(
                [$binary, '-version'],
                [
                    0 => ['file', DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null', 'r'],
                    1 => ['file', $stdoutPath, 'w'],
                    2 => ['file', $stderrPath, 'w'],
                ],
                $pipes,
                null,
                null,
                ['bypass_shell' => true]
            );

            if (! is_resource($process)) {
                throw new LocalValidationInfrastructureException('The Java executable could not be started.');
            }

            $exitCode = $this->awaitExit($process);
            // Read only after the process has exited so no trailing output is lost.
            $output = $this->readLimited($stdoutPath) . $this->readLimited($stderrPath);
        } finally {
            if (is_resource($process)) {
                @proc_terminate($process);
                @proc_close($process);
            }

            foreach ($temporaryFiles as $path) {
                @unlink($path);
            }
        }

        $majorVersion = $exitCode === 0 ? self::parseMajorVersion($output) : null;

        if ($majorVersion === null) {
            throw new LocalValidationInfrastructureException('The Java executable did not return a readable version.');
        }

        if ($majorVersion < ValidationResources::MINIMUM_JAVA_VERSION) {
            throw new LocalValidationInfrastructureException(sprintf(
                'Local Schematron validation requires Java %d or newer.',
                ValidationResources::MINIMUM_JAVA_VERSION
            ));
        }

        return $majorVersion;
    }

    /**
     * Waits for the process to exit and returns its exit code.
     *
     * @param  resource                               $process Running `java -version` process.
     * @throws LocalValidationInfrastructureException when the process outlives the version timeout.
     */
    private function awaitExit($process): int
    {
        $deadline = microtime(true) + $this->versionTimeoutSeconds;

        do {
            $status = @proc_get_status($process);

            if (! is_array($status)) {
                throw new LocalValidationInfrastructureException('The Java version check status is unavailable.');
            }

            if (! $status['running']) {
                return (int) $status['exitcode'];
            }

            if (microtime(true) >= $deadline) {
                throw new LocalValidationInfrastructureException('The Java version check timed out.');
            }

            usleep(self::VERSION_POLL_INTERVAL_MICROSECONDS);
        } while (true);
    }

    /** Reads at most the retained number of bytes from a captured output file. */
    private function readLimited(string $path): string
    {
        $content = @file_get_contents($path, false, null, 0, self::VERSION_MAX_OUTPUT_BYTES);

        return $content === false ? '' : $content;
    }

    /**
     * Creates a private temporary file and records it for removal.
     *
     * @param  string[]                               $temporaryFiles Paths removed after the probe.
     * @throws LocalValidationInfrastructureException when no temporary file can be created.
     */
    private function temporaryFile(array &$temporaryFiles): string
    {
        $path = tempnam(sys_get_temp_dir(), 'efaktura-java-');

        if ($path === false) {
            throw new LocalValidationInfrastructureException('The Java version check could not be prepared.');
        }

        @chmod($path, 0600);
        $temporaryFiles[] = $path;

        return $path;
    }

    /** Extracts the major version from `java -version` output; "1.8" style versions map to 8. */
    private static function parseMajorVersion(string $output): ?int
    {
        if (
            ! preg_match(
                '/(?:openjdk|java)(?:\s+version)?\s+["\']?([0-9]+)(?:\.([0-9]+))?/i',
                $output,
                $matches
            )
        ) {
            return null;
        }

        return (int) $matches[1] === 1 ? (int) ($matches[2] ?? 0) : (int) $matches[1];
    }
}
