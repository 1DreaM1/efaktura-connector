<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics;

use Throwable;

/** Keeps failures in an optional metrics backend from changing connector behavior. */
final class SafeConnectorMetrics
{
    /**
     * Records one HTTP attempt while suppressing exceptions from the optional metrics backend.
     *
     * @param ConnectorMetricsInterface|null $metrics         Metrics sink, or null when metrics are disabled.
     * @param string                         $method          HTTP method used for the attempt.
     * @param int|null                       $statusCode      HTTP status received, or null when no response was received.
     * @param float                          $durationSeconds Elapsed time for this attempt.
     * @param string                         $outcome         Stable outcome label defined by ConnectorMetricsInterface.
     */
    public static function apiRequest(
        ?ConnectorMetricsInterface $metrics,
        string $method,
        ?int $statusCode,
        float $durationSeconds,
        string $outcome
    ): void {
        if ($metrics === null) {
            return;
        }

        try {
            $metrics->recordApiRequest($method, $statusCode, $durationSeconds, $outcome);
        } catch (Throwable $exception) {
            // Metrics are optional and must not change the request result.
        }
    }

    /**
     * Records a token-cache lifecycle event while suppressing metrics backend failures.
     *
     * @param ConnectorMetricsInterface|null $metrics Metrics sink, or null when metrics are disabled.
     * @param string                         $event   Stable event label defined by ConnectorMetricsInterface.
     */
    public static function tokenCacheEvent(?ConnectorMetricsInterface $metrics, string $event): void
    {
        if ($metrics === null) {
            return;
        }

        try {
            $metrics->recordTokenCacheEvent($event);
        } catch (Throwable $exception) {
            // Metrics are optional and must not change token lifecycle behavior.
        }
    }

    /**
     * Reports a failed shared-cache operation to sinks that opt in, without affecting the caller.
     *
     * @param ConnectorMetricsInterface|null $metrics   Metrics sink, or null when metrics are disabled.
     * @param string                         $component One of the CacheFailureMetricsInterface::COMPONENT_* labels.
     */
    public static function cacheFailure(?ConnectorMetricsInterface $metrics, string $component): void
    {
        if (! $metrics instanceof CacheFailureMetricsInterface) {
            return;
        }

        try {
            $metrics->recordCacheFailure($component);
        } catch (Throwable $exception) {
            // Metrics are optional and must not change how the cache outage is handled.
        }
    }

    /**
     * Records a Schematron validation run while suppressing metrics backend failures.
     *
     * @param ConnectorMetricsInterface|null $metrics         Metrics sink, or null when metrics are disabled.
     * @param float                          $durationSeconds Elapsed validation time.
     * @param string                         $outcome         Stable outcome label defined by ConnectorMetricsInterface.
     * @param int                            $rulesetCount    Number of rule sets applied to the document.
     */
    public static function schematronValidation(
        ?ConnectorMetricsInterface $metrics,
        float $durationSeconds,
        string $outcome,
        int $rulesetCount
    ): void {
        if ($metrics === null) {
            return;
        }

        try {
            $metrics->recordSchematronValidation($durationSeconds, $outcome, $rulesetCount);
        } catch (Throwable $exception) {
            // Metrics are optional and must not change validation results.
        }
    }
}
