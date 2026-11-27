<?php

namespace DreaM1\EfakturaConnector\Laravel\Console;

use DreaM1\EfakturaConnector\Core\Configuration\SapiClientsConfiguration;
use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenCacheInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\JavaRuntimeProbe;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\PeppolSchematronValidationStage;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationResources;
use DreaM1\EfakturaConnector\Laravel\Cache\LaravelCacheStore;
use DreaM1\EfakturaConnector\Laravel\Configuration\LaravelPackageConfiguration;
use DreaM1\EfakturaConnector\Laravel\Configuration\LaravelServiceConfiguration;
use DreaM1\EfakturaConnector\Laravel\Transport\LaravelCacheLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/** Reports local configuration and runtime readiness without contacting the provider. */
final class CheckConnectorCommand extends Command
{
    /** Lease for the short-lived cache lock capability probe, in seconds. */
    private const CACHE_LOCK_LEASE_SECONDS = 5;
    /** Wait time for the cache lock capability probe; zero means fail immediately when busy. */
    private const CACHE_LOCK_WAIT_SECONDS = 0;

    protected $signature = 'efaktura:check';

    protected $description = 'Check local eFaktura connector configuration and runtime dependencies.';

    private int $failures = 0;

    /**
     * Checks the selected client, encrypted token-cache requirements, cache coordination and validation runtime.
     *
     * The command never sends an HTTP request to SAPI or Peppol Gateway. It may acquire and release
     * a short-lived lock in the configured Laravel cache store to verify atomic-lock support.
     */
    public function handle(): int
    {
        $this->failures       = 0;
        $packageConfiguration = (array) $this->laravel->make('config')->get('efaktura-connector', []);
        $serviceConfiguration = $this->checkServiceConfiguration($packageConfiguration);

        $this->checkDefaultOAuthClient($packageConfiguration);

        if ($this->usesDefaultTokenCache($serviceConfiguration)) {
            $this->checkEncryptionKey();
        }

        if ($serviceConfiguration !== null) {
            $this->checkCacheCoordination($serviceConfiguration);
            $this->checkValidationRuntime($serviceConfiguration);
        }

        return $this->reportResult();
    }

    /**
     * Validates the Laravel-specific configuration and returns it when it is usable.
     *
     * @param array<string, mixed> $packageConfiguration Package configuration from the Laravel repository.
     */
    private function checkServiceConfiguration(array $packageConfiguration): ?LaravelServiceConfiguration
    {
        try {
            $configuration = LaravelServiceConfiguration::fromArray($packageConfiguration);
            $this->line('<info>PASS</info> Laravel bridge configuration: valid');

            return $configuration;
        } catch (Throwable $exception) {
            $this->reportFailure('Laravel bridge configuration', $exception, true);

            return null;
        }
    }

    /**
     * Verifies that the configured default OAuth client is ready to use.
     *
     * @param array<string, mixed> $packageConfiguration Package configuration from the Laravel repository.
     */
    private function checkDefaultOAuthClient(array $packageConfiguration): void
    {
        $this->check('Default OAuth client', function () use ($packageConfiguration): string {
            SapiClientsConfiguration::fromArray(
                LaravelPackageConfiguration::connectorSettings($packageConfiguration)
            )->defaultClient()->assertReady();

            return 'configured';
        }, true);
    }

    /** Reports whether Laravel's encrypted token cache is the active implementation. */
    private function usesDefaultTokenCache(?LaravelServiceConfiguration $configuration): bool
    {
        return $configuration === null
            || ! $configuration->overrides(SapiTokenCacheInterface::class);
    }

    /** Verifies that Laravel can encrypt and decrypt a temporary value with app.key. */
    private function checkEncryptionKey(): void
    {
        $this->check('Laravel encryption key', function (): string {
            $key = $this->laravel->make('config')->get('app.key');

            if (! is_string($key) || trim($key) === '') {
                throw new ConfigurationException('Set app.key so the default token cache can encrypt OAuth tokens.');
            }

            $probe = 'efaktura-local-key-check';

            if (Crypt::decryptString(Crypt::encryptString($probe)) !== $probe) {
                throw new ConfigurationException('The configured app.key failed the encryption round trip.');
            }

            return 'configured';
        }, true);
    }

    /** Checks each Laravel cache store used for connector coordination. */
    private function checkCacheCoordination(LaravelServiceConfiguration $configuration): void
    {
        $cacheStore = $configuration->cacheStore();
        $this->checkCacheLock($cacheStore);

        $usesSeparateExecutionLockStore = $configuration->executionLockDriver() === LaravelServiceConfiguration::EXECUTION_LOCK_DRIVER_CACHE
            && $configuration->executionLockStore() !== $cacheStore;

        if ($usesSeparateExecutionLockStore) {
            $this->checkCacheLock($configuration->executionLockStore(), 'HTTP execution-lock cache');
        }
    }

    /** Checks the validation resources and any configured Schematron runtime. */
    private function checkValidationRuntime(LaravelServiceConfiguration $configuration): void
    {
        $this->checkValidationResources($configuration);

        if (! $this->usesSchematron($configuration)) {
            return;
        }

        $this->checkValidationWorkingDirectory($configuration);
        $this->checkSchematronLockDirectory($configuration);
        $this->checkSchematronRuntime($configuration);
    }

    /** Returns whether local Schematron validation is part of the configured pipeline. */
    private function usesSchematron(LaravelServiceConfiguration $configuration): bool
    {
        return in_array(
            PeppolSchematronValidationStage::class,
            $configuration->validationStageClasses(),
            true
        );
    }

    /** Prints a summary and returns the command's exit code. */
    private function reportResult(): int
    {
        if ($this->failures > 0) {
            $this->error(sprintf(
                '%d local connector check(s) failed. No provider API request was sent.',
                $this->failures
            ));

            return 1;
        }

        $this->info('The local connector checks passed. No provider API request was sent.');

        return 0;
    }

    /** Verifies that a store can acquire an atomic lock and release it. */
    private function checkCacheLock(?string $store, string $label = 'Laravel cache lock'): void
    {
        $this->check($label, function () use ($store): string {
            $key     = 'efaktura.diagnostics.' . bin2hex(random_bytes(16));
            $release = LaravelCacheLock::acquire(
                $key,
                self::CACHE_LOCK_LEASE_SECONDS,
                self::CACHE_LOCK_WAIT_SECONDS,
                $store
            );
            $release();

            if (! LaravelCacheStore::supportsAtomicLocks($store)) {
                return 'uses the local file-lock compatibility fallback';
            }

            return $store === null
                ? 'default store supports atomic locks'
                : sprintf('store "%s" supports atomic locks', $store);
        });
    }

    /** Checks that the configured or bundled UBL validation resources exist. */
    private function checkValidationResources(LaravelServiceConfiguration $configuration): void
    {
        $this->check('UBL validation resources', function () use ($configuration): string {
            $directory = $configuration->validationResourceDirectory() ?? ValidationResources::directory();

            if (! is_dir($directory) || ! is_readable($directory)) {
                throw new ConfigurationException('The configured validation resource directory is missing or unreadable.');
            }

            return 'readable';
        }, true);
    }

    /** Checks that the local Schematron process working directory is writable. */
    private function checkValidationWorkingDirectory(LaravelServiceConfiguration $configuration): void
    {
        $this->check('Validation working directory', function () use ($configuration): string {
            $directory = $configuration->validationWorkingDirectory() ?? $this->laravel->basePath();

            if (! is_dir($directory) || ! is_writable($directory)) {
                throw new ConfigurationException('The validation working directory is missing or not writable.');
            }

            return 'writable';
        }, true);
    }

    /** Checks file-lock support and whether the Schematron lock directory can be created. */
    private function checkSchematronLockDirectory(LaravelServiceConfiguration $configuration): void
    {
        $this->check('Schematron lock directory', function () use ($configuration): string {
            if (! function_exists('flock')) {
                throw new ConfigurationException('Enable flock to coordinate local Schematron validation.');
            }

            $directory = $configuration->schematronConcurrencyLockDirectory()
                ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'efaktura-schematron-locks';
            $ancestor = $directory;
            while (! file_exists($ancestor) && dirname($ancestor) !== $ancestor) {
                $ancestor = dirname($ancestor);
            }

            if (! is_dir($ancestor) || ! is_writable($ancestor)) {
                throw new ConfigurationException('The Schematron lock directory cannot be created or written.');
            }

            return file_exists($directory) ? 'writable' : 'parent directory is writable';
        }, true);
    }

    /** Verifies that Schematron has proc_open and a runnable Java 11+ executable. */
    private function checkSchematronRuntime(LaravelServiceConfiguration $configuration): void
    {
        $procOpenAvailable = function_exists('proc_open');
        $this->check('Schematron process support', function () use ($procOpenAvailable): string {
            if (! $procOpenAvailable) {
                throw new ConfigurationException('Enable proc_open to run local Schematron validation.');
            }

            return 'proc_open is available';
        }, true);

        if (! $procOpenAvailable) {
            return;
        }

        $this->check('Java runtime', function () use ($configuration): string {
            $probe  = new JavaRuntimeProbe();
            $binary = $probe->find($configuration->javaBinary());

            if ($binary === null) {
                throw new ConfigurationException(sprintf(
                    'Set java_binary or make Java %d+ available on PATH.',
                    ValidationResources::MINIMUM_JAVA_VERSION
                ));
            }

            return 'Java ' . $probe->assertSupportedVersion($binary) . ' is available';
        }, true);
    }

    /** Runs a local check and records a concise failure without printing environment values. */
    private function check(string $label, callable $operation, bool $showFailureReason = false): void
    {
        try {
            $detail = $operation();
            $this->line('<info>PASS</info> ' . $label . ': ' . $detail);
        } catch (Throwable $exception) {
            $this->reportFailure($label, $exception, $showFailureReason);
        }
    }

    /** Records one failed check and includes its technical reason when requested. */
    private function reportFailure(string $label, Throwable $exception, bool $showFailureReason): void
    {
        $failure = $showFailureReason && trim($exception->getMessage()) !== ''
            ? ': ' . $exception->getMessage()
            : ' (' . get_class($exception) . ')';
        $this->line('<error>FAIL</error> ' . $label . $failure);
        $this->failures++;
    }
}
