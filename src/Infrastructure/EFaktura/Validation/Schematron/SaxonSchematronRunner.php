<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron;

use DreaM1\EfakturaConnector\Core\Contracts\SchematronRunnerInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationProvenanceProviderInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\SchematronReport;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationProvenance;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationResources;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Executes bundled Schematron stylesheets with the configured Saxon HE Java runner. */
class SaxonSchematronRunner implements SchematronRunnerInterface, UblValidationProvenanceProviderInterface
{
    private string $resourceDirectory;
    private string $workingDirectory;
    private SaxonProcessExecutor $processExecutor;
    private ?string $configuredJavaBinary;
    private ?int $javaMaxHeapMegabytes;

    /**
     * @param  string                    $resourceDirectory    Directory containing validation resources.
     * @param  string                    $workingDirectory     Working directory used when launching the validation process.
     * @param  SaxonProcessExecutor|null $processExecutor      Executor that launches the Schematron process.
     * @param  string|null               $javaBinary           Java executable name or path for Schematron execution.
     * @param  int|null                  $javaMaxHeapMegabytes Heap limit (-Xmx) for each process in megabytes, or null for the JVM default.
     * @throws InvalidInputException     when the heap limit is not positive.
     */
    public function __construct(
        string $resourceDirectory,
        string $workingDirectory,
        ?SaxonProcessExecutor $processExecutor = null,
        ?string $javaBinary = null,
        ?int $javaMaxHeapMegabytes = null
    ) {
        if ($javaMaxHeapMegabytes !== null && $javaMaxHeapMegabytes < 1) {
            throw new InvalidInputException('The Java heap limit must be positive.');
        }

        $this->resourceDirectory    = $resourceDirectory;
        $this->workingDirectory     = $workingDirectory;
        $this->processExecutor      = $processExecutor ?? new SaxonProcessExecutor();
        $this->configuredJavaBinary = $javaBinary;
        $this->javaMaxHeapMegabytes = $javaMaxHeapMegabytes;
    }

    /**
     * Runs each stylesheet against the supplied XML and returns reports in input order.
     *
     * @param  string                                 $xml         UBL XML document passed to each stylesheet.
     * @param  string[]                               $stylesheets Paths of the Schematron stylesheets to execute.
     * @return SchematronReport[]                     Reports in the same order as the supplied stylesheet paths.
     * @throws LocalValidationInfrastructureException when validation resources or process execution are unavailable.
     */
    public function run(string $xml, array $stylesheets): array
    {
        if ($stylesheets === []) {
            return [];
        }

        $directoryName = 'saxon-he-' . ValidationResources::SAXON_VERSION;
        $directory     = ResourcePath::join($this->resourceDirectory, $directoryName);

        $saxon = ResourcePath::join($directory, $directoryName . '.jar');

        if (! is_file($saxon) || ! is_readable($saxon)) {
            throw new LocalValidationInfrastructureException(
                'Saxon resources for local Peppol validation are unavailable.'
            );
        }

        $classPath = $saxon . PATH_SEPARATOR . ResourcePath::join($directory, 'lib/*');
        $java      = $this->javaBinary();

        // Build one argument-vector command per stylesheet; the executor launches them as separate processes.
        $commands = array_map(
            fn (string $stylesheet): array => $this->commandForStylesheet($stylesheet, $java, $classPath),
            array_values($stylesheets)
        );

        return $this->processExecutor->execute($commands, $xml, $this->workingDirectory);
    }

    /**
     * @param  UblXmlDocument        $document Document associated with the reported runtime.
     * @return array<string, string>
     */
    public function provenance(UblXmlDocument $document): array
    {
        $directoryName = 'saxon-he-' . ValidationResources::SAXON_VERSION;
        $saxon         = ResourcePath::join(
            $this->resourceDirectory,
            $directoryName . '/' . $directoryName . '.jar'
        );
        $provenance = [
            'runtime.saxon_he'     => ValidationResources::SAXON_VERSION,
            'runtime.java_minimum' => (string) ValidationResources::MINIMUM_JAVA_VERSION,
        ];

        $javaVersion = $this->javaRuntimeVersion();

        if ($javaVersion !== null) {
            $provenance['runtime.java_version'] = $javaVersion;
        }

        return UblValidationProvenance::withFileHashes($provenance, [
            'artifact.saxon_he.jar_sha256' => $saxon,
        ]);
    }

    /** Returns the JDK release version when the selected binary can be tied to a local release file. */
    private function javaRuntimeVersion(): ?string
    {
        $javaBinary = $this->configuredJavaBinary;

        if ($javaBinary === null) {
            $javaHome = getenv('JAVA_HOME');

            if (! $javaHome) {
                return null;
            }

            $candidate = ResourcePath::join(
                $javaHome,
                'bin/' . (DIRECTORY_SEPARATOR === '\\' ? 'java.exe' : 'java')
            );

            if (! is_file($candidate)) {
                return null;
            }

            $javaBinary = $candidate;
        }

        $resolvedBinary = realpath($javaBinary);

        if ($resolvedBinary === false) {
            return null;
        }

        $binaryDirectory = dirname($resolvedBinary);

        if (strtolower(basename($binaryDirectory)) !== 'bin') {
            return null;
        }

        $releasePath = ResourcePath::join(dirname($binaryDirectory), 'release');
        $release     = @file_get_contents($releasePath);

        if (! is_string($release) || preg_match('/^JAVA_VERSION="([^"]+)"\r?$/m', $release, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Builds the Saxon argument list with external document and extension access disabled.
     *
     * @return list<string> Command-line arguments for one Saxon process.
     */
    private function commandForStylesheet(string $stylesheet, string $java, string $classPath): array
    {
        $heapOption = $this->javaMaxHeapMegabytes === null ? [] : ['-Xmx' . $this->javaMaxHeapMegabytes . 'm'];

        return array_merge([$java], $heapOption, [
            '-cp',
            $classPath,
            'net.sf.saxon.Transform',
            '-dtd:off',
            '-ext:off',
            '-s:-',
            '-xsl:' . $stylesheet,
        ]);
    }

    /** Prefers JAVA_HOME when it contains Java, then falls back to PATH lookup. */
    private function javaBinary(): string
    {
        if ($this->configuredJavaBinary !== null) {
            return $this->configuredJavaBinary;
        }

        $javaHome = getenv('JAVA_HOME');

        if ($javaHome) {
            $binary = ResourcePath::join(
                $javaHome,
                'bin/' . (DIRECTORY_SEPARATOR === '\\' ? 'java.exe' : 'java')
            );

            if (is_file($binary)) {
                return $binary;
            }
        }

        return 'java';
    }
}
