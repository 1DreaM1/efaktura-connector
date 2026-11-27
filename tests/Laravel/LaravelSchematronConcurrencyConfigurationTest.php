<?php

namespace DreaM1\EfakturaConnector\Tests\Laravel;

use DreaM1\EfakturaConnector\Core\Contracts\SchematronRunnerInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\FileSchematronConcurrencyLimiter;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\SaxonProcessExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\SaxonSchematronRunner;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Laravel\EFakturaConnectorServiceProvider;
use DreaM1\EfakturaConnector\Tests\Support\ReflectionAccess;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/** Checks that Laravel configuration sets the Schematron process concurrency limit. */
class LaravelSchematronConcurrencyConfigurationTest extends TestCase
{
    public function testLaravelConfigurationSetsTheConcurrentValidationLimit(): void
    {
        $workingDirectory = ResourcePath::join(
            sys_get_temp_dir(),
            'efaktura-app-' . bin2hex(random_bytes(8))
        );
        $lockDirectory = ResourcePath::join($workingDirectory, 'schematron-locks');
        mkdir($workingDirectory, 0700);
        $container = new class ($workingDirectory) extends Container {
            private string $applicationBasePath;

            /**
             * Creates a container that resolves application-relative paths under the test directory.
             *
             * @param string $applicationBasePath Application directory used by the test.
             */
            public function __construct(string $applicationBasePath)
            {
                $this->applicationBasePath = $applicationBasePath;
            }

            /**
             * Returns the application base path or resolves a path relative to it.
             *
             * @param  string $path Optional relative path to append.
             * @return string Absolute application path.
             */
            public function basePath($path = ''): string
            {
                return $path === ''
                    ? $this->applicationBasePath
                    : ResourcePath::join($this->applicationBasePath, $path);
            }
        };
        $container->instance('config', new Repository([
            'efaktura-connector' => [
                'runtime' => [
                    'schematron' => [
                        'max_concurrent_validations' => 1,
                    ],
                ],
                'laravel' => [
                    'validation' => [
                        'concurrency_lock_directory' => $lockDirectory,
                    ],
                ],
            ],
        ]));
        (new EFakturaConnectorServiceProvider($container))->register();

        $runner         = $container->make(SchematronRunnerInterface::class);
        $runnerProperty = new ReflectionProperty(SaxonSchematronRunner::class, 'processExecutor');
        ReflectionAccess::makeAccessible($runnerProperty);
        $executor        = $runnerProperty->getValue($runner);
        $limiterProperty = new ReflectionProperty(SaxonProcessExecutor::class, 'concurrencyLimiter');
        ReflectionAccess::makeAccessible($limiterProperty);
        $limiter = $limiterProperty->getValue($executor);

        $this->assertInstanceOf(FileSchematronConcurrencyLimiter::class, $limiter);
        $permit = null;
        try {
            $permit = $limiter->acquire($workingDirectory, 0);
            try {
                $limiter->acquire($workingDirectory, 0);
                $this->fail('The configured single slot must block a second validation.');
            } catch (LocalValidationInfrastructureException $exception) {
                $this->assertNotFalse(strpos($exception->getMessage(), 'concurrency limit is busy'));
            }
        } finally {
            if ($permit !== null) {
                $permit->release();
            }

            $this->removeLockFiles($lockDirectory);
            rmdir($workingDirectory);
        }
    }

    /**
     * Removes the deterministic Schematron lock files created for the test directory.
     *
     * @param string $lockDirectory Temporary lock directory configured by the test.
     */
    private function removeLockFiles(string $lockDirectory): void
    {
        $lockFile = ResourcePath::join($lockDirectory, 'slot-0.lock');

        if (is_file($lockFile)) {
            unlink($lockFile);
        }

        if (is_dir($lockDirectory)) {
            rmdir($lockDirectory);
        }
    }
}
