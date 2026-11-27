<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Laravel;

use DreaM1\EfakturaConnector\Core\Configuration\ConnectorRuntimeConfiguration;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\CurlRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\PooledCurlRequestExecutor;
use DreaM1\EfakturaConnector\Laravel\Configuration\LaravelExecutionLockLeasePolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

/** Tests runtime validation for Laravel cache execution-lock leases. */
class LaravelExecutionLockLeasePolicyTest extends TestCase
{
    public function testRejectsLeaseShorterThanOneRequestTimeoutAndMargin(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 50 seconds');

        LaravelExecutionLockLeasePolicy::resolveCacheLeaseSeconds(
            49,
            CurlRequestExecutor::class,
            $this->runtime()
        );
    }

    public function testAcceptsLeaseAtOneRequestTimeoutAndMargin(): void
    {
        LaravelExecutionLockLeasePolicy::resolveCacheLeaseSeconds(
            50,
            CurlRequestExecutor::class,
            $this->runtime()
        );

        $this->addToAssertionCount(1);
    }

    public function testDerivesTheLeaseFromTheRequestTimeoutWhenNoneIsConfigured(): void
    {
        $this->assertSame(
            50,
            LaravelExecutionLockLeasePolicy::resolveCacheLeaseSeconds(null, CurlRequestExecutor::class, $this->runtime())
        );
        $this->assertSame(
            55,
            LaravelExecutionLockLeasePolicy::resolveCacheLeaseSeconds(
                null,
                PooledCurlRequestExecutor::class,
                ConnectorRuntimeConfiguration::fromArray([
                    'runtime' => ['http' => ['request_timeout_seconds' => 50]],
                ])
            )
        );
    }

    public function testKeepsAnExplicitLeaseThatIsLongEnough(): void
    {
        $this->assertSame(
            240,
            LaravelExecutionLockLeasePolicy::resolveCacheLeaseSeconds(240, CurlRequestExecutor::class, $this->runtime())
        );
    }

    public function testRetrySettingsDoNotAffectTheRequiredLease(): void
    {
        // The lock is held for one attempt only, so even a long retry sequence needs no longer lease.
        LaravelExecutionLockLeasePolicy::resolveCacheLeaseSeconds(
            50,
            CurlRequestExecutor::class,
            ConnectorRuntimeConfiguration::fromArray([
                'runtime' => [
                    'http'  => ['request_timeout_seconds' => 45],
                    'retry' => [
                        'max_attempts'        => 10,
                        'base_delay_ms'       => 30000,
                        'max_delay_ms'        => 30000,
                        'max_inline_delay_ms' => 30000,
                    ],
                ],
            ])
        );

        $this->addToAssertionCount(1);
    }

    public function testDefaultLeaseCoversDefaultRuntimeForBuiltInCurlExecutors(): void
    {
        foreach ([CurlRequestExecutor::class, PooledCurlRequestExecutor::class] as $executorClass) {
            LaravelExecutionLockLeasePolicy::resolveCacheLeaseSeconds(
                180,
                $executorClass,
                ConnectorRuntimeConfiguration::fromArray([])
            );
        }

        $this->addToAssertionCount(1);
    }

    public function testRejectsCustomExecutorsWithoutAnEnforceableRuntimeBound(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be validated for a custom HTTP request executor');

        LaravelExecutionLockLeasePolicy::resolveCacheLeaseSeconds(
            1000,
            stdClass::class,
            ConnectorRuntimeConfiguration::fromArray([])
        );
    }

    private function runtime(): ConnectorRuntimeConfiguration
    {
        return ConnectorRuntimeConfiguration::fromArray([
            'runtime' => ['http' => ['request_timeout_seconds' => 45]],
        ]);
    }
}
