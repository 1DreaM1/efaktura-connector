<?php

namespace DreaM1\EfakturaConnector\Tests\Support;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationResources;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;

/** Provides the environment check used by tests that execute the bundled Saxon runtime. */
trait RequiresSaxonRuntime
{
    /** Skips the test when the bundled Saxon runtime or required process support is unavailable. */
    protected function requireSaxonRuntime(): void
    {
        $directoryName  = 'saxon-he-' . ValidationResources::SAXON_VERSION;
        $saxonDirectory = ValidationResources::path($directoryName);

        if (
            ! function_exists('proc_open')
            || ! is_file(ResourcePath::join($saxonDirectory, $directoryName . '.jar'))
        ) {
            $this->markTestSkipped('Saxon resources or PHP proc_open are unavailable.');
        }

        $javaHome   = getenv('JAVA_HOME');
        $javaInHome = $javaHome
            ? ResourcePath::join($javaHome, 'bin/' . (DIRECTORY_SEPARATOR === '\\' ? 'java.exe' : 'java'))
            : null;
        $java    = $javaInHome !== null && is_file($javaInHome) ? $javaInHome : 'java';
        $process = @proc_open(
            [$java, '-version'],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            sys_get_temp_dir(),
            null,
            ['bypass_shell' => true]
        );

        if (! is_resource($process)) {
            $this->markTestSkipped('Java is unavailable.');
        }

        fclose($pipes[0]);
        $versionOutput = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if (
            $exitCode !== 0
            || ! preg_match('/version "([^"]+)"/i', $versionOutput, $matches)
        ) {
            $this->markTestSkipped('Java is unavailable.');
        }

        $versionParts     = explode('.', $matches[1]);
        $javaMajorVersion = (int) ($versionParts[0] === '1' ? ($versionParts[1] ?? 0) : $versionParts[0]);

        if ($javaMajorVersion < ValidationResources::MINIMUM_JAVA_VERSION) {
            $this->markTestSkipped('Java 11 or later is required.');
        }
    }
}
