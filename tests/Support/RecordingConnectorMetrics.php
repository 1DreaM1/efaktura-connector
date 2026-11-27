<?php

namespace DreaM1\EfakturaConnector\Tests\Support;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\CacheFailureMetricsInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;

/** Stores metric calls for unit tests without introducing a monitoring dependency. */
final class RecordingConnectorMetrics implements ConnectorMetricsInterface, CacheFailureMetricsInterface
{
    /** @var string[] */
    public array $cacheFailures = [];

    /** @var array<int, array{method: string, status_code: int|null, duration_seconds: float, outcome: string}> */
    public array $apiRequests = [];

    /** @var string[] */
    public array $tokenCacheEvents = [];

    /** @var array<int, array{duration_seconds: float, outcome: string, ruleset_count: int}> */
    public array $schematronValidations = [];

    /**
     * Records the component whose shared cache failed.
     *
     * @param string $component Component label to retain.
     */
    public function recordCacheFailure(string $component): void
    {
        $this->cacheFailures[] = $component;
    }

    /**
     * Records one HTTP request metric for later assertions.
     *
     * @param string   $method          HTTP method.
     * @param int|null $statusCode      Response status, or null when no response was received.
     * @param float    $durationSeconds Request duration in seconds.
     * @param string   $outcome         Recorded request outcome label.
     */
    public function recordApiRequest(
        string $method,
        ?int $statusCode,
        float $durationSeconds,
        string $outcome
    ): void {
        $this->apiRequests[] = [
            'method'           => $method,
            'status_code'      => $statusCode,
            'duration_seconds' => $durationSeconds,
            'outcome'          => $outcome,
        ];
    }

    /**
     * Records a token cache event label for later assertions.
     *
     * @param string $event Event label to retain.
     */
    public function recordTokenCacheEvent(string $event): void
    {
        $this->tokenCacheEvents[] = $event;
    }

    /**
     * Records a Schematron validation metric for later assertions.
     *
     * @param float  $durationSeconds Validation duration in seconds.
     * @param string $outcome         Recorded validation outcome label.
     * @param int    $rulesetCount    Number of rule sets executed.
     */
    public function recordSchematronValidation(
        float $durationSeconds,
        string $outcome,
        int $rulesetCount
    ): void {
        $this->schematronValidations[] = [
            'duration_seconds' => $durationSeconds,
            'outcome'          => $outcome,
            'ruleset_count'    => $rulesetCount,
        ];
    }
}
