<?php

namespace DreaM1\EfakturaConnector\Laravel\Transport;

use DateTime;
use DreaM1\EfakturaConnector\Core\CircuitBreaker\AbstractStatefulCircuitBreaker;
use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerPolicy;
use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerState;
use DreaM1\EfakturaConnector\Core\Configuration\IntegratorAccountConfiguration;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientProfile;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\CacheFailureMetricsInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\SafeConnectorMetrics;
use DreaM1\EfakturaConnector\Laravel\Cache\LaravelCacheStore;
use DreaM1\EfakturaConnector\Support\HashAlgorithm;
use Throwable;

/**
 * Shares breaker state through atomic Laravel cache locks.
 * Falls back to process-local state if cache coordination fails.
 */
final class LaravelCacheCircuitBreaker extends AbstractStatefulCircuitBreaker
{
    /** Minimum interval between retries when shared cache coordination fails, in seconds. */
    private const SHARED_STATE_RETRY_INTERVAL_SECONDS = 5;

    private string $cacheKey;
    private bool $fallbackActive     = false;
    private int $nextSharedAttemptAt = 0;
    private ?string $store;
    private ?ConnectorMetricsInterface $metrics;
    /** @var callable(): int */
    private $unixClock;
    private CircuitBreakerState $fallbackState;

    /**
     * Creates a breaker with a stable cache identity and optional timing policy.
     *
     * @param string                         $cacheKey  Non-sensitive identity used to scope shared breaker state.
     * @param CircuitBreakerPolicy|null      $policy    Failure thresholds and open/half-open timings.
     * @param callable|null                  $unixClock Zero-argument callback returning the current Unix timestamp as an integer.
     * @param string|null                    $store     Laravel cache store used for shared breaker state.
     * @param ConnectorMetricsInterface|null $metrics   Receives a cache-failure event when shared state is unavailable.
     */
    public function __construct(
        string $cacheKey,
        ?CircuitBreakerPolicy $policy = null,
        ?callable $unixClock = null,
        ?string $store = null,
        ?ConnectorMetricsInterface $metrics = null
    ) {
        parent::__construct($policy);
        $this->metrics       = $metrics;
        $this->cacheKey      = 'efaktura.circuit_breaker.' . $cacheKey;
        $this->fallbackState = CircuitBreakerState::closed();
        $this->store         = $store;
        $this->unixClock     = $unixClock ?? static function (): int {
            return time();
        };
    }

    /**
     * Creates a breaker whose shared state is scoped to the configured provider connection.
     *
     * @param  SapiClientConfigurationInterface $configuration Provider connection settings used to derive the cache identity.
     * @param  CircuitBreakerPolicy|null        $policy        Optional policy defining breaker thresholds and timings.
     * @param  string|null                      $store         Laravel cache store used for shared breaker state.
     * @return self                             Cache-backed breaker for the configured connection.
     */
    public static function forConnectionConfiguration(
        SapiClientConfigurationInterface $configuration,
        ?CircuitBreakerPolicy $policy = null,
        ?string $store = null,
        ?ConnectorMetricsInterface $metrics = null
    ): self {
        $profile  = SapiClientProfile::fromConfiguration($configuration);
        $identity = hash(HashAlgorithm::SHA_256, implode("\0", [
            $profile->baseUrl(),
            $profile->clientId(),
            $profile->clientScope(),
        ]));

        return new self($identity, $policy, null, $store, $metrics);
    }

    /**
     * Creates a breaker scoped to one Integrator API account and provider URL.
     *
     * The account identifier is used instead of the token so token rotation preserves the account's
     * provider health state without exposing credentials in the cache key.
     *
     * @param  IntegratorAccountConfiguration $configuration Integrator account settings.
     * @param  CircuitBreakerPolicy|null      $policy        Optional breaker thresholds and timings.
     * @param  string|null                    $store         Laravel cache store used for shared state.
     * @param  ConnectorMetricsInterface|null $metrics       Receives cache-failure events.
     * @return self
     */
    public static function forIntegratorAccountConfiguration(
        IntegratorAccountConfiguration $configuration,
        ?CircuitBreakerPolicy $policy = null,
        ?string $store = null,
        ?ConnectorMetricsInterface $metrics = null
    ): self {
        $identity = hash(HashAlgorithm::SHA_256, implode("\0", [
            'integrator-api',
            $configuration->baseUrl()->value(),
            $configuration->integratorId()->value(),
        ]));

        return new self($identity, $policy, null, $store, $metrics);
    }

    /**
     * Applies a breaker state transition through shared cache or the local fallback state.
     *
     * @param  callable(CircuitBreakerState, float): mixed $operation Transition receiving state and current Unix time.
     * @return mixed                                       Result produced by the state transition.
     */
    protected function updateState(callable $operation)
    {
        if ($this->fallbackActive) {
            // Avoid repeatedly waiting on a failed shared store; keep serving this worker from its
            // local state until the next scheduled coordination attempt.
            if (($this->unixClock)() < $this->nextSharedAttemptAt) {
                return $this->updateFallbackState($operation);
            }

            try {
                $result = $this->updateSharedState($operation);
                // Shared coordination is available again, so continue from its authoritative state.
                $this->fallbackActive      = false;
                $this->nextSharedAttemptAt = 0;

                return $result;
            } catch (Throwable $exception) {
                // Continue with local protection and defer another cache attempt for a short interval.
                $this->reportSharedStateFailure();
                $this->scheduleSharedRetry();

                return $this->updateFallbackState($operation);
            }
        }

        try {
            return $this->updateSharedState($operation);
        } catch (Throwable $exception) {
            // Cache/lock failure must not disable the breaker in this process.
            $this->fallbackActive = true;
            $this->reportSharedStateFailure();
            $this->scheduleSharedRetry();

            return $this->updateFallbackState($operation);
        }
    }

    /** Makes a shared-state outage visible, since the breaker keeps protecting this worker locally. */
    private function reportSharedStateFailure(): void
    {
        SafeConnectorMetrics::cacheFailure($this->metrics, CacheFailureMetricsInterface::COMPONENT_CIRCUIT_BREAKER);
    }

    /** Schedules the next attempt to coordinate breaker state through the shared cache. */
    private function scheduleSharedRetry(): void
    {
        $this->nextSharedAttemptAt = ($this->unixClock)() + self::SHARED_STATE_RETRY_INTERVAL_SECONDS;
    }

    /**
     * Executes a transition against the cache while holding its atomic lock.
     *
     * @param  callable(CircuitBreakerState, float): mixed $operation Atomic state transition.
     * @return mixed                                       Result produced by the transition.
     */
    private function updateSharedState(callable $operation)
    {
        // Lock the full read-transition-write cycle so workers cannot lose each other's failures
        // or grant multiple half-open probes from the same shared state.
        return LaravelCacheLock::synchronizedOnStore($this->cacheKey . '.lock', function () use ($operation) {
            $state  = CircuitBreakerState::fromArray(LaravelCacheStore::get($this->store, $this->cacheKey));
            $result = $operation($state, microtime(true));
            $this->save($state);
            // Preserve active permits if cache coordination fails before their outcomes arrive.
            $this->fallbackState = CircuitBreakerState::fromArray($state->toArray());

            return $result;
        }, $this->store);
    }

    /**
     * Applies a transition to this instance's local state after cache coordination fails.
     *
     * @param  callable(CircuitBreakerState, float): mixed $operation Atomic state transition.
     * @return mixed                                       Result produced by the transition.
     */
    private function updateFallbackState(callable $operation)
    {
        return $operation($this->fallbackState, microtime(true));
    }

    /**
     * Persists breaker generation and active failures for their relevant windows.
     *
     * @param CircuitBreakerState $state Breaker state to persist.
     */
    private function save(CircuitBreakerState $state): void
    {
        $policy = $this->circuitBreakerPolicy();
        $data   = $state->toArray();
        // Keep OPEN/HALF_OPEN state through the cooldown plus failure window; CLOSED state only
        // needs to survive long enough to retain recent failures for threshold counting.
        $ttl = $state->state() === CircuitBreakerState::STATE_OPEN
            || $state->state() === CircuitBreakerState::STATE_HALF_OPEN
            ? $policy->failureWindowSeconds() + $policy->openDurationSeconds()
            : $policy->failureWindowSeconds();
        LaravelCacheStore::put($this->store, $this->cacheKey, $data, new DateTime('@' . (time() + $ttl)));
    }
}
