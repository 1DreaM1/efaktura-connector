<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Configuration;

use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerDefaults;
use DreaM1\EfakturaConnector\Core\Configuration\ConnectorRuntimeConfiguration;
use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Core\Retry\RetryPolicy;
use PHPUnit\Framework\TestCase;

/** Covers grouped runtime settings, executor defaults and validation of timeout and retry limits. */
class ConnectorRuntimeConfigurationTest extends TestCase
{
    public function testNestedRuntimeSettingsAreParsedIntoTypedValues(): void
    {
        $configuration = ConnectorRuntimeConfiguration::fromArray([
            'runtime' => [
                'http' => [
                    'connect_timeout_seconds' => '7',
                    'request_timeout_seconds' => 26,
                    'response_max_bytes'      => '2097152',
                ],
                'retry' => [
                    'enabled'             => 'false',
                    'max_attempts'        => 4,
                    'base_delay_ms'       => '250',
                    'max_delay_ms'        => 1200,
                    'max_inline_delay_ms' => 400,
                ],
                'circuit_breaker' => [
                    'failure_threshold'      => 7,
                    'failure_window_seconds' => 90,
                    'open_seconds'           => 45,
                ],
                'backpressure' => [
                    'fallback_cooldown_seconds' => 3,
                ],
                'validation' => [
                    'max_issues' => 12,
                ],
                'schematron' => [
                    'process_timeout_seconds'     => 75,
                    'max_concurrent_validations'  => '3',
                    'max_report_bytes'            => 16000000,
                    'max_error_output_bytes'      => 32000,
                    'max_error_detail_characters' => 256,
                ],
            ],
        ]);

        $this->assertSame(7, $configuration->httpConnectTimeoutSeconds());
        $this->assertSame(26, $configuration->httpRequestTimeoutSeconds());
        $this->assertSame(2097152, $configuration->httpResponseBodyLimit()->bytes());
        $this->assertSame(4, $configuration->retryPolicy()->maxAttempts());
        $this->assertSame(250, $configuration->retryPolicy()->delayForRetry(1));
        $this->assertFalse($configuration->retryEnabled());
        $this->assertSame(1200, $configuration->retryPolicy()->maxDelayMs());
        $this->assertSame(400, $configuration->retryPolicy()->maxInlineDelayMs());
        $this->assertSame(7, $configuration->circuitBreakerPolicy()->failureThreshold());
        $this->assertSame(90, $configuration->circuitBreakerPolicy()->failureWindowSeconds());
        $this->assertSame(45, $configuration->circuitBreakerPolicy()->openDurationSeconds());
        $this->assertSame(3, $configuration->rateLimitFallbackCooldownSeconds());
        $this->assertSame(12, $configuration->validationMaxIssues());
        $this->assertSame(75, $configuration->schematronProcessTimeoutSeconds());
        $this->assertSame(3, $configuration->schematronMaxConcurrentValidations());
        $this->assertSame(16000000, $configuration->schematronMaxReportBytes());
        $this->assertSame(32000, $configuration->schematronMaxErrorOutputBytes());
        $this->assertSame(256, $configuration->schematronMaxErrorDetailCharacters());
    }

    public function testRuntimeSettingsAreReadFromTheirGroupedSections(): void
    {
        $configuration = ConnectorRuntimeConfiguration::fromArray([
            'runtime' => [
                'http'  => ['connect_timeout_seconds' => 11],
                'retry' => ['max_attempts' => 2],
            ],
        ]);

        $this->assertSame(11, $configuration->httpConnectTimeoutSeconds());
        $this->assertSame(2, $configuration->retryPolicy()->maxAttempts());
    }

    public function testFlatRuntimeKeysDoNotOverrideGroupedDefaults(): void
    {
        $configuration = ConnectorRuntimeConfiguration::fromArray([
            'http_connect_timeout_seconds' => 11,
            'http_retry_max_attempts'      => 2,
        ]);

        $this->assertSame(
            ConnectorRuntimeConfiguration::DEFAULT_HTTP_CONNECT_TIMEOUT_SECONDS,
            $configuration->httpConnectTimeoutSeconds()
        );
        $this->assertSame(RetryPolicy::DEFAULT_MAX_ATTEMPTS, $configuration->retryPolicy()->maxAttempts());
    }

    public function testUnknownRuntimeKeysAreRejectedInsteadOfUsingDefaults(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Unknown runtime.http configuration key "request_timeuot_seconds"');

        ConnectorRuntimeConfiguration::fromArray([
            'runtime' => [
                'http' => ['request_timeuot_seconds' => 12],
            ],
        ]);
    }

    public function testConnectTimeoutCannotExceedTotalRequestTimeout(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('HTTP connect timeout must not exceed the total request timeout');

        ConnectorRuntimeConfiguration::fromArray([
            'runtime' => [
                'http' => [
                    'connect_timeout_seconds' => 20,
                    'request_timeout_seconds' => 10,
                ],
            ],
        ]);
    }

    public function testExecutionLayersCanBeSwitchedOffIndividually(): void
    {
        $configuration = ConnectorRuntimeConfiguration::fromArray([
            'runtime' => [
                'circuit_breaker' => ['enabled' => 'false'],
                'backpressure'    => ['enabled' => true, 'execution_lock' => 0],
            ],
        ]);

        $this->assertTrue($configuration->retryEnabled());
        $this->assertFalse($configuration->circuitBreakerEnabled());
        $this->assertTrue($configuration->backpressureEnabled());
        $this->assertFalse($configuration->executionLockEnabled());
    }

    public function testExecutionLockIsNeverEffectiveWithoutBackpressure(): void
    {
        $configuration = ConnectorRuntimeConfiguration::fromArray([
            'runtime' => ['backpressure' => ['enabled' => false, 'execution_lock' => true]],
        ]);

        $this->assertFalse($configuration->backpressureEnabled());
        $this->assertFalse($configuration->executionLockEnabled());
    }

    public function testInvalidLayerSwitchIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);

        ConnectorRuntimeConfiguration::fromArray([
            'runtime' => ['circuit_breaker' => ['enabled' => 'maybe']],
        ]);
    }

    public function testDefaultsMatchTheRuntimeExecutorDefaults(): void
    {
        $configuration = ConnectorRuntimeConfiguration::fromArray([]);

        $this->assertSame(
            ConnectorRuntimeConfiguration::DEFAULT_HTTP_CONNECT_TIMEOUT_SECONDS,
            $configuration->httpConnectTimeoutSeconds()
        );
        $this->assertSame(
            ConnectorRuntimeConfiguration::DEFAULT_HTTP_REQUEST_TIMEOUT_SECONDS,
            $configuration->httpRequestTimeoutSeconds()
        );
        $this->assertSame(
            ConnectorRuntimeConfiguration::DEFAULT_HTTP_RESPONSE_MAX_BYTES,
            $configuration->httpResponseBodyLimit()->bytes()
        );
        $this->assertSame(RetryPolicy::DEFAULT_MAX_ATTEMPTS, $configuration->retryPolicy()->maxAttempts());
        $this->assertTrue($configuration->retryEnabled());
        $this->assertTrue($configuration->circuitBreakerEnabled());
        $this->assertTrue($configuration->backpressureEnabled());
        $this->assertTrue($configuration->executionLockEnabled());
        $this->assertSame(CircuitBreakerDefaults::FAILURE_THRESHOLD, $configuration->circuitBreakerPolicy()->failureThreshold());
        $this->assertSame(ConnectorRuntimeConfiguration::DEFAULT_VALIDATION_MAX_ISSUES, $configuration->validationMaxIssues());
        $this->assertSame(
            ConnectorRuntimeConfiguration::DEFAULT_SCHEMATRON_MAX_CONCURRENT_VALIDATIONS,
            $configuration->schematronMaxConcurrentValidations()
        );
    }

    /**
     * Rejects runtime configuration values outside their supported ranges.
     *
     * @param array<string, mixed> $values Runtime settings under test.
     * @dataProvider invalidRuntimeConfigurationProvider
     */
    public function testInvalidRuntimeLimitsAreRejected(array $values): void
    {
        $this->expectException(ConfigurationException::class);

        ConnectorRuntimeConfiguration::fromArray($values);
    }

    /**
     * Provides invalid runtime configuration values and their setting paths.
     *
     * @return array<array-key, array<array-key, mixed>> Invalid configuration cases.
     */
    public function invalidRuntimeConfigurationProvider(): array
    {
        return [
            'invalid timeout'                => [['runtime' => ['http' => ['connect_timeout_seconds' => 'fast']]]],
            'zero timeout'                   => [['runtime' => ['http' => ['request_timeout_seconds' => 0]]]],
            'zero response size'             => [['runtime' => ['http' => ['response_max_bytes' => 0]]]],
            'response size above safety cap' => [[
                'runtime' => ['http' => ['response_max_bytes' => 134217729]],
            ]],
            'zero retry attempts'                          => [['runtime' => ['retry' => ['max_attempts' => 0]]]],
            'too many retry attempts'                      => [['runtime' => ['retry' => ['max_attempts' => 11]]]],
            'negative retry delay'                         => [['runtime' => ['retry' => ['base_delay_ms' => -1]]]],
            'retry delay overflows microsecond conversion' => [[
                'runtime' => ['retry' => ['max_delay_ms' => intdiv(PHP_INT_MAX, 1000) + 1]],
            ]],
            'invalid retry enabled'          => [['runtime' => ['retry' => ['enabled' => 'sometimes']]]],
            'zero breaker threshold'         => [['runtime' => ['circuit_breaker' => ['failure_threshold' => 0]]]],
            'negative backpressure cooldown' => [['runtime' => ['backpressure' => ['fallback_cooldown_seconds' => 0]]]],
            'zero validation issues'         => [['runtime' => ['validation' => ['max_issues' => 0]]]],
            'too many validation issues'     => [['runtime' => ['validation' => ['max_issues' => 1001]]]],
            'zero report size'               => [['runtime' => ['schematron' => ['max_report_bytes' => 0]]]],
            'report size above safety cap'   => [[
                'runtime' => ['schematron' => ['max_report_bytes' => 134217729]],
            ]],
            'zero Schematron concurrency' => [['runtime' => ['schematron' => ['max_concurrent_validations' => 0]]]],
            'Java heap below the minimum' => [['runtime' => ['schematron' => ['java_max_heap_mb' => 63]]]],
            'Java heap above the maximum' => [['runtime' => ['schematron' => ['java_max_heap_mb' => 65537]]]],
            'Java heap not a number'      => [['runtime' => ['schematron' => ['java_max_heap_mb' => 'lots']]]],
            'negative Java heap'          => [['runtime' => ['schematron' => ['java_max_heap_mb' => -512]]]],
        ];
    }

    public function testJavaHeapLimitIsUnsetByDefault(): void
    {
        $this->assertNull(ConnectorRuntimeConfiguration::fromArray([])->schematronJavaMaxHeapMegabytes());
    }

    /**
     * @param int|string|null $value    Configured value.
     * @param int|null        $expected Parsed heap limit.
     * @dataProvider javaHeapValues
     */
    public function testJavaHeapLimitIsParsedFromEnvironmentStyleValues($value, ?int $expected): void
    {
        $configuration = ConnectorRuntimeConfiguration::fromArray([
            'runtime' => ['schematron' => ['java_max_heap_mb' => $value]],
        ]);

        $this->assertSame($expected, $configuration->schematronJavaMaxHeapMegabytes());
    }

    /** @return array<string, array{int|string|null, int|null}> */
    public static function javaHeapValues(): array
    {
        return [
            'null'           => [null, null],
            'empty string'   => ['', null],
            'blank string'   => ['   ', null],
            'integer'        => [512, 512],
            'numeric string' => ['1024', 1024],
            'minimum'        => [ConnectorRuntimeConfiguration::MIN_CONFIGURED_SCHEMATRON_JAVA_HEAP_MB, 64],
            'maximum'        => [ConnectorRuntimeConfiguration::MAX_CONFIGURED_SCHEMATRON_JAVA_HEAP_MB, 65536],
        ];
    }
}
