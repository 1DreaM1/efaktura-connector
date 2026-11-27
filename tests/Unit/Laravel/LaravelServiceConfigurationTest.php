<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Laravel;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\InMemoryHttpRequestBackpressure;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\PooledCurlRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\PeppolBillingHeaderValidationStage;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schema\UblSchemaValidationStage;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\PeppolSchematronValidationStage;
use DreaM1\EfakturaConnector\Laravel\Configuration\LaravelServiceConfiguration;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Tests\Support\Validation\ConfiguredValidationStage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests Laravel-specific service configuration validation and defaults. */
class LaravelServiceConfigurationTest extends TestCase
{
    public function testParserUsesSharedConfigurationDefaults(): void
    {
        $configuration = LaravelServiceConfiguration::fromArray([]);

        $this->assertSame(
            LaravelServiceConfiguration::DEFAULT_REQUEST_EXECUTOR_CLASS,
            $configuration->requestExecutorClass()
        );
        $this->assertSame(
            LaravelServiceConfiguration::DEFAULT_VALIDATION_STAGE_CLASSES,
            $configuration->validationStageClasses()
        );

        $this->assertTrue($configuration->httpLoggingEnabled());
        $this->assertTrue($configuration->httpMetricsEnabled());

        $this->assertSame('cache', $configuration->executionLockDriver());
        $this->assertNull($configuration->cacheStore());
        $this->assertNull($configuration->executionLockStore());
        $this->assertNull($configuration->executionLockLeaseSeconds());
        $this->assertSame(120, $configuration->executionLockWaitSeconds());
    }

    public function testSharedCacheStoreIsInheritedByHttpExecutionLock(): void
    {
        $configuration = LaravelServiceConfiguration::fromArray([
            'laravel' => [
                'cache_store' => 'connector-state',
            ],
        ]);

        $this->assertSame('connector-state', $configuration->cacheStore());
        $this->assertSame('connector-state', $configuration->executionLockStore());
    }

    public function testHttpExecutionLockStoreOverridesSharedCacheStore(): void
    {
        $configuration = LaravelServiceConfiguration::fromArray([
            'laravel' => [
                'cache_store'       => 'connector-state',
                'request_execution' => [
                    'execution_lock' => ['store' => 'http-locks'],
                ],
            ],
        ]);

        $this->assertSame('connector-state', $configuration->cacheStore());
        $this->assertSame('http-locks', $configuration->executionLockStore());
    }

    public function testDefaultValidationStagesIncludePeppolBillingHeaderRules(): void
    {
        $this->assertSame(
            [
                UblSchemaValidationStage::class,
                PeppolBillingHeaderValidationStage::class,
                PeppolSchematronValidationStage::class,
            ],
            LaravelServiceConfiguration::DEFAULT_VALIDATION_STAGE_CLASSES
        );
    }

    public function testGroupedLaravelOptionsAndServiceOverridesAreParsed(): void
    {
        $configuration = LaravelServiceConfiguration::fromArray([
            'laravel' => [
                'request_execution' => [
                    'executor' => PooledCurlRequestExecutor::class,
                ],
                'validation' => [
                    'stages' => [ConfiguredValidationStage::class],
                ],
                'services' => [
                    HttpRequestBackpressureInterface::class => InMemoryHttpRequestBackpressure::class,
                ],
            ],
        ]);

        $this->assertSame(PooledCurlRequestExecutor::class, $configuration->requestExecutorClass());
        $this->assertSame([ConfiguredValidationStage::class], $configuration->validationStageClasses());
        $this->assertSame(
            [HttpRequestBackpressureInterface::class => InMemoryHttpRequestBackpressure::class],
            $configuration->serviceBindings()
        );
    }

    public function testConfiguredRequestExecutorClassIsTrimmedBeforeValidation(): void
    {
        $configuration = LaravelServiceConfiguration::fromArray([
            'laravel' => [
                'request_execution' => [
                    'executor' => '  ' . PooledCurlRequestExecutor::class . '  ',
                ],
            ],
        ]);

        $this->assertSame(PooledCurlRequestExecutor::class, $configuration->requestExecutorClass());
    }

    public function testRequestExecutionAndValidationRuntimeOptionsAreParsed(): void
    {
        $configuration = LaravelServiceConfiguration::fromArray([
            'laravel' => [
                'request_execution' => [
                    'executor'       => PooledCurlRequestExecutor::class,
                    'logging'        => 'off',
                    'metrics'        => true,
                    'execution_lock' => [
                        'driver'              => 'file',
                        'temporary_directory' => '/var/tmp/efaktura-http-locks',
                    ],
                ],
                'validation' => [
                    'stages'                     => [PeppolSchematronValidationStage::class],
                    'resource_directory'         => '/opt/efaktura/validation',
                    'working_directory'          => '/var/tmp/efaktura',
                    'concurrency_lock_directory' => '/var/tmp/efaktura-schematron-locks',
                    'java_binary'                => '/usr/bin/java',
                ],
            ],
        ]);

        $this->assertSame(PooledCurlRequestExecutor::class, $configuration->requestExecutorClass());
        $this->assertSame([PeppolSchematronValidationStage::class], $configuration->validationStageClasses());
        $this->assertFalse($configuration->httpLoggingEnabled());
        $this->assertTrue($configuration->httpMetricsEnabled());
        $this->assertSame('file', $configuration->executionLockDriver());
        $this->assertSame('/var/tmp/efaktura-http-locks', $configuration->executionLockTemporaryDirectory());
        $this->assertSame('/opt/efaktura/validation', $configuration->validationResourceDirectory());
        $this->assertSame('/var/tmp/efaktura', $configuration->validationWorkingDirectory());
        $this->assertSame(
            '/var/tmp/efaktura-schematron-locks',
            $configuration->schematronConcurrencyLockDirectory()
        );
        $this->assertSame('/usr/bin/java', $configuration->javaBinary());
    }

    public function testExecutionLockTemporaryDirectoryRequiresFileDriver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('temporary_directory setting requires driver="file"');

        LaravelServiceConfiguration::fromArray([
            'laravel' => [
                'request_execution' => [
                    'execution_lock' => [
                        'temporary_directory' => '/var/tmp/efaktura-http-locks',
                    ],
                ],
            ],
        ]);
    }

    public function testBlankExecutionLockLeaseIsTreatedAsNotConfigured(): void
    {
        foreach ([null, ''] as $lease) {
            $configuration = LaravelServiceConfiguration::fromArray([
                'laravel' => ['request_execution' => ['execution_lock' => ['lease_seconds' => $lease]]],
            ]);

            $this->assertNull($configuration->executionLockLeaseSeconds());
        }
    }

    public function testRejectsAnExecutionLockLeaseBelowOneSecond(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('laravel.request_execution.execution_lock.lease_seconds');

        LaravelServiceConfiguration::fromArray([
            'laravel' => ['request_execution' => ['execution_lock' => ['lease_seconds' => 0]]],
        ]);
    }

    public function testRequestExecutionCacheLockSettingsAreParsed(): void
    {
        $configuration = LaravelServiceConfiguration::fromArray([
            'laravel' => [
                'request_execution' => [
                    'execution_lock' => [
                        'driver'        => 'cache',
                        'store'         => 'http-locks',
                        'lease_seconds' => '240',
                        'wait_seconds'  => 8,
                    ],
                ],
            ],
        ]);

        $this->assertSame('cache', $configuration->executionLockDriver());
        $this->assertSame('http-locks', $configuration->executionLockStore());
        $this->assertSame(240, $configuration->executionLockLeaseSeconds());
        $this->assertSame(8, $configuration->executionLockWaitSeconds());
    }

    public function testInvalidRequestExecutionLockDriverIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LaravelServiceConfiguration::fromArray([
            'laravel' => [
                'request_execution' => [
                    'execution_lock' => ['driver' => 'database'],
                ],
            ],
        ]);
    }

    public function testInvalidRequestExecutionLockLeaseAndWaitAreRejected(): void
    {
        foreach ([['lease_seconds' => 0], ['wait_seconds' => -1]] as $settings) {
            try {
                LaravelServiceConfiguration::fromArray([
                    'laravel' => [
                        'request_execution' => [
                            'execution_lock' => $settings,
                        ],
                    ],
                ]);
                $this->fail('Expected invalid HTTP execution lock timing to be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function testServiceBindingsAreReadFromTheLaravelSection(): void
    {
        $configuration = LaravelServiceConfiguration::fromArray([
            'laravel' => [
                'services' => [
                    HttpRequestBackpressureInterface::class => InMemoryHttpRequestBackpressure::class,
                ],
            ],
        ]);

        $this->assertSame(
            [HttpRequestBackpressureInterface::class => InMemoryHttpRequestBackpressure::class],
            $configuration->serviceBindings()
        );
    }

    public function testServiceOverrideMustImplementItsContract(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LaravelServiceConfiguration::fromArray([
            'laravel' => [
                'services' => [
                    HttpRequestBackpressureInterface::class => PooledCurlRequestExecutor::class,
                ],
            ],
        ]);
    }

    public function testTheRemovedLayersSectionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LaravelServiceConfiguration::fromArray([
            'laravel' => [
                'request_execution' => [
                    'layers' => ['circuit_breaker' => false],
                ],
            ],
        ]);
    }

    public function testUnknownLaravelSettingsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown laravel.request_execution.execution_lock configuration key "lease_second"');

        LaravelServiceConfiguration::fromArray([
            'laravel' => [
                'request_execution' => [
                    'execution_lock' => ['lease_second' => 240],
                ],
            ],
        ]);
    }

    public function testEmptyValidationStageListIsRejectedDuringConfigurationParsing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('laravel.validation.stages setting must contain at least one stage');

        LaravelServiceConfiguration::fromArray([
            'laravel' => [
                'validation' => ['stages' => []],
            ],
        ]);
    }
}
