<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Exceptions;

use DreaM1\EfakturaConnector\Core\Configuration\HttpResponseBodyLimit;
use DreaM1\EfakturaConnector\Core\Exceptions\CircuitBreakerOpenException;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderResponseTooLargeException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestDeferredException;
use DreaM1\EfakturaConnector\Core\Exceptions\TokenRequestException;
use DreaM1\EfakturaConnector\Core\Retry\RetryDeadline;
use PHPUnit\Framework\TestCase;

/** Hosts decide whether a document submission may be repeated blindly from the failure type alone. */
final class RequestMayHaveReachedProviderTest extends TestCase
{
    /**
     * @param ProviderApiException $exception Failure to classify.
     * @param bool                 $expected  Whether the request could have arrived at the provider.
     * @dataProvider failures
     */
    public function testClassifiesWhetherTheRequestCouldHaveArrived(ProviderApiException $exception, bool $expected): void
    {
        $this->assertSame($expected, $exception->requestMayHaveReachedProvider());
    }

    /** @return array<string, array{ProviderApiException, bool}> */
    public function failures(): array
    {
        return [
            'deferred by backpressure'  => [new RequestDeferredException(RetryDeadline::fromUnixTimestamp(1800000000)), false],
            'token unavailable'         => [new TokenRequestException(), false],
            'circuit breaker open'      => [new CircuitBreakerOpenException(), false],
            'failed before sending'     => [ProviderConnectionException::beforeSending('Failed to initialize cURL.'), false],
            'network failure'           => [new ProviderConnectionException('Connection reset.'), true],
            'provider error response'   => [new ProviderErrorResponseException(500), true],
            'invalid provider response' => [new InvalidProviderResponseException('Bad.'), true],
            'response too large'        => [new ProviderResponseTooLargeException(HttpResponseBodyLimit::default(), 200), true],
        ];
    }

    public function testFailureBeforeSendingStaysAConnectionFailure(): void
    {
        $exception = ProviderConnectionException::beforeSending('Failed to configure cURL request.');

        $this->assertInstanceOf(ProviderConnectionException::class, $exception);
        $this->assertSame('Failed to configure cURL request.', $exception->getMessage());
        $this->assertNull($exception->httpStatusCode());
    }
}
