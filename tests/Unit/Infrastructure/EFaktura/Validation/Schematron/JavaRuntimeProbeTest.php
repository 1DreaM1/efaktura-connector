<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Validation\Schematron;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\JavaRuntimeProbe;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationResources;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use PHPUnit\Framework\TestCase;

/** Tests discovery and version verification of the local Java runtime. */
class JavaRuntimeProbeTest extends TestCase
{
    public function testFindReturnsAnExplicitExecutablePath(): void
    {
        $this->assertSame(PHP_BINARY, (new JavaRuntimeProbe())->find(PHP_BINARY));
    }

    public function testFindReturnsNullForAMissingExecutablePath(): void
    {
        $missing = ResourcePath::join(sys_get_temp_dir(), 'efaktura-missing-java-' . bin2hex(random_bytes(8)) . '/bin/java');

        $this->assertNull((new JavaRuntimeProbe())->find($missing));
    }

    public function testFindReturnsNullForAnUnknownBareExecutableName(): void
    {
        $this->assertNull((new JavaRuntimeProbe())->find('efaktura-no-such-java-' . bin2hex(random_bytes(8))));
    }

    public function testVersionCheckRejectsAnExecutableWithoutJavaVersionOutput(): void
    {
        $this->expectException(LocalValidationInfrastructureException::class);
        $this->expectExceptionMessage('did not return a readable version');

        (new JavaRuntimeProbe())->assertSupportedVersion(PHP_BINARY);
    }

    public function testVersionCheckAcceptsTheDiscoveredJavaRuntime(): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open is unavailable.');
        }

        $probe  = new JavaRuntimeProbe();
        $binary = $probe->find(null);

        if ($binary === null) {
            $this->markTestSkipped('No Java executable is available.');
        }

        try {
            $version = $probe->assertSupportedVersion($binary);
        } catch (LocalValidationInfrastructureException $exception) {
            $this->markTestSkipped('The discovered Java runtime is not usable: ' . $exception->getMessage());
        }

        $this->assertGreaterThanOrEqual(ValidationResources::MINIMUM_JAVA_VERSION, $version);
    }

    public function testVersionCheckTimesOutOnAHangingRuntimeInsteadOfBlocking(): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open is unavailable.');
        }

        $script = $this->hangingExecutable();

        try {
            $startedAt = microtime(true);

            try {
                (new JavaRuntimeProbe(1))->assertSupportedVersion($script);
                $this->fail('A runtime that never exits must time out.');
            } catch (LocalValidationInfrastructureException $exception) {
                $this->assertStringContainsString('timed out', $exception->getMessage());
            }

            // A blocking read would keep the call alive for the whole 20 second sleep of the script.
            $this->assertLessThan(10.0, microtime(true) - $startedAt);
        } finally {
            @unlink($script);
        }
    }

    public function testVersionCheckRejectsAnExecutableThatCannotBeStarted(): void
    {
        $missing = ResourcePath::join(sys_get_temp_dir(), 'efaktura-missing-java-' . bin2hex(random_bytes(8)) . '/java');

        $this->expectException(LocalValidationInfrastructureException::class);
        $this->expectExceptionMessage('could not be started');

        (new JavaRuntimeProbe())->assertSupportedVersion($missing);
    }

    public function testRejectsANonPositiveVersionTimeout(): void
    {
        $this->expectException(InvalidInputException::class);

        new JavaRuntimeProbe(0);
    }

    /** Creates a script that ignores its arguments and runs for much longer than the test timeout. */
    private function hangingExecutable(): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'efaktura-hang-' . bin2hex(random_bytes(6)) . '.bat';
            file_put_contents($path, "@echo off\r\nping -n 21 127.0.0.1 >nul\r\n");

            return $path;
        }

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'efaktura-hang-' . bin2hex(random_bytes(6)) . '.sh';
        file_put_contents($path, "#!/bin/sh\nexec sleep 20\n");
        chmod($path, 0700);

        return $path;
    }
}
