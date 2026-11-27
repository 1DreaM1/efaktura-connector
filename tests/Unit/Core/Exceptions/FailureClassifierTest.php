<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Exceptions;

use DreaM1\EfakturaConnector\Core\Configuration\HttpResponseBodyLimit;
use DreaM1\EfakturaConnector\Core\Exceptions\CircuitBreakerOpenException;
use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Core\Exceptions\DocumentRejectedByProviderException;
use DreaM1\EfakturaConnector\Core\Exceptions\FailureAssessment;
use DreaM1\EfakturaConnector\Core\Exceptions\FailureClassifier;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderResponseTooLargeException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestDeferredException;
use DreaM1\EfakturaConnector\Core\Exceptions\TokenRequestException;
use DreaM1\EfakturaConnector\Core\Retry\RetryDeadline;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Exceptions\InvariantViolationException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/** A host decides to retry, verify, fix configuration or fail from the assessment alone. */
final class FailureClassifierTest extends TestCase
{
    /**
     * @param Throwable $failure        Failure to assess.
     * @param string    $category       Expected FailureAssessment category.
     * @param bool      $retryable      Expected retryability.
     * @param bool      $mayHaveReached Expected requestMayHaveReachedProvider().
     * @param bool      $outcomeUnknown Expected isOutcomeUnknown().
     * @dataProvider failures
     */
    public function testAssessesEachFailure(
        Throwable $failure,
        string $category,
        bool $retryable,
        bool $mayHaveReached,
        bool $outcomeUnknown
    ): void {
        $assessment = FailureClassifier::classify($failure);

        $this->assertSame($category, $assessment->category());
        $this->assertSame($retryable, $assessment->isRetryable());
        $this->assertSame($mayHaveReached, $assessment->requestMayHaveReachedProvider());
        $this->assertSame($outcomeUnknown, $assessment->isOutcomeUnknown());
    }

    /** @return array<string, array{Throwable, string, bool, bool, bool}> */
    public function failures(): array
    {
        $deferred = RetryDeadline::fromUnixTimestamp(1800000000);

        return [
            'deferred'              => [new RequestDeferredException($deferred), 'deferred', true, false, false],
            'breaker open'          => [new CircuitBreakerOpenException(), 'deferred', true, false, false],
            'coordination'          => [new RequestCoordinationException(), 'deferred', true, false, false],
            'network failure'       => [new ProviderConnectionException('Reset.'), 'transient', true, true, true],
            'failed before sending' => [ProviderConnectionException::beforeSending('No cURL.'), 'transient', true, false, false],
            'unauthorized'          => [new ProviderErrorResponseException(401), 'authentication', false, true, false],
            'forbidden'             => [new ProviderErrorResponseException(403), 'authentication', false, true, false],
            'rate limited'          => [new ProviderErrorResponseException(429), 'transient', true, true, false],
            'request timeout'       => [new ProviderErrorResponseException(408), 'transient', true, true, true],
            'server error'          => [new ProviderErrorResponseException(503), 'transient', true, true, true],
            'validation error'      => [new ProviderErrorResponseException(422), 'rejected', false, true, false],
            'unreadable success'    => [new InvalidProviderResponseException('Bad.'), 'invalid_response', false, true, true],
            'oversized response'    => [new ProviderResponseTooLargeException(HttpResponseBodyLimit::default(), 200), 'invalid_response', false, true, true],
            'token refused'         => [new TokenRequestException(new ProviderErrorResponseException(401)), 'authentication', false, false, false],
            'token without cause'   => [new TokenRequestException(), 'authentication', false, false, false],
            'token server down'     => [new TokenRequestException(new ProviderErrorResponseException(503)), 'transient', true, false, false],
            'token network failure' => [new TokenRequestException(new ProviderConnectionException('Reset.')), 'transient', true, false, false],
            'preflight rejection'   => [new DocumentRejectedByProviderException(), 'rejected', false, false, false],
            'invalid input'         => [new InvalidInputException('Bad value.'), 'invalid_input', false, false, false],
            'configuration'         => [new ConfigurationException('Missing secret.'), 'configuration', false, false, false],
            'invariant'             => [new InvariantViolationException('Broken.'), 'internal', false, true, true],
            'foreign exception'     => [new RuntimeException('Other.'), 'internal', false, true, true],
        ];
    }

    public function testRetryAtComesFromTheDeferralAndFromRetryAfter(): void
    {
        $deferredAt = RetryDeadline::fromUnixTimestamp(1800000000);

        $this->assertSame(
            $deferredAt,
            FailureClassifier::classify(new RequestDeferredException($deferredAt))->retryAt()
        );

        $limited = new ProviderErrorResponseException(429, '', ['Retry-After' => '120']);

        $this->assertNotNull(FailureClassifier::classify($limited)->retryAt());
        $this->assertNull(FailureClassifier::classify(new ProviderErrorResponseException(503))->retryAt());
    }

    public function testOnlyUnsentFailuresAreSafeToRepeatBlindly(): void
    {
        $this->assertTrue(FailureClassifier::classify(new CircuitBreakerOpenException())->isSafeToRepeatBlindly());
        $this->assertFalse(FailureClassifier::classify(new ProviderConnectionException('Reset.'))->isSafeToRepeatBlindly());
        $this->assertFalse(FailureClassifier::classify(new ProviderErrorResponseException(422))->isSafeToRepeatBlindly());
    }

    public function testAdministratorAttentionCoversCredentialsAndConfiguration(): void
    {
        $this->assertTrue(FailureClassifier::classify(new ProviderErrorResponseException(401))->needsAdministratorAttention());
        $this->assertTrue(FailureClassifier::classify(new ConfigurationException('Missing.'))->needsAdministratorAttention());
        $this->assertFalse(FailureClassifier::classify(new ProviderErrorResponseException(503))->needsAdministratorAttention());
        $this->assertSame(
            FailureAssessment::CATEGORY_INVALID_INPUT,
            FailureClassifier::classify(new InvalidInputException('x'))->category()
        );
    }
}
