<?php

namespace DreaM1\EfakturaConnector\Tests\Integration;

use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\AuthenticatedRequestSender;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenCacheInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenProvider;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\RequestTokenEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\ProviderTransport;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use DreaM1\EfakturaConnector\Tests\Support\TestConfiguration;
use PHPUnit\Framework\TestCase;
use Throwable;

/** Provides shared setup and fixtures for connector workflow integration tests. */
abstract class WorkflowTestCase extends TestCase
{
    protected const PARTICIPANT_ID = '0245:2012345676';
    protected const BASE_URL       = 'https://sapi.synthetic.test';
    protected const RECEIVER_ID    = '0245:2012345699';

    // ── Component factory ────────────────────────────────────────────────────

    /**
     * Creates an API connector using the shared workflow test configuration.
     *
     * @param  HttpRequestExecutorInterface $executor Executor used by the connector transport.
     * @return ApiConnector                 Connector configured with synthetic test credentials.
     */
    protected function buildConnector(HttpRequestExecutorInterface $executor): ApiConnector
    {
        return new ApiConnector($this->buildRequestSender($executor));
    }

    /**
     * Creates an authenticated sender with a token provider backed by a test cache mock.
     *
     * @param  HttpRequestExecutorInterface $executor Executor used by the test transport.
     * @return AuthenticatedRequestSender   Sender configured with synthetic credentials.
     */
    protected function buildRequestSender(HttpRequestExecutorInterface $executor): AuthenticatedRequestSender
    {
        $transport = $this->buildTransport($executor);
        $cache     = $this->createMock(SapiTokenCacheInterface::class);
        $cache->method('load')->willReturn(null);
        $tokenProvider = new SapiTokenProvider(
            new RequestTokenEndpoint($transport),
            $cache,
            TestConfiguration::connector('synthetic-client', 'synthetic-secret')
        );

        return new AuthenticatedRequestSender($transport, $tokenProvider);
    }

    /**
     * Creates an HTTP transport using the shared synthetic connector configuration.
     *
     * @param  HttpRequestExecutorInterface $executor Executor used for HTTP exchanges.
     * @return Transport                    Configured transport instance.
     */
    protected function buildTransport(HttpRequestExecutorInterface $executor): ProviderTransport
    {
        $configuration = TestConfiguration::connector(
            TestConfiguration::CLIENT_ID,
            TestConfiguration::CLIENT_SECRET,
            self::BASE_URL
        );
        return ProviderTransport::forSapiClient($configuration, $executor);
    }

    /** Returns the default synthetic participant used by workflow tests. */
    protected function participant(): Participant
    {
        return TestConfiguration::participant(self::PARTICIPANT_ID);
    }

    // ── Mock executor helpers ────────────────────────────────────────────────

    /**
     * Returns an executor that serves pre-loaded responses or throws exceptions in FIFO order.
     * Queue a ProviderConnectionException to simulate a network failure at that position.
     *
     * @param array<HttpExchangeResult|ProviderApiException> $queue
     *                                                              Synthetic responses and provider failures to return in order.
     */
    protected function queuedExecutor(array $queue): HttpRequestExecutorInterface
    {
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(
            function (HttpExchangeRequest $request) use (&$queue) {
                if (empty($queue)) {
                    $this->fail('Unexpected HTTP request to: ' . $request->path());
                }

                $response = array_shift($queue);

                if ($response instanceof Throwable) {
                    throw $response;
                }

                return $response;
            }
        );

        return $executor;
    }

    // ── Synthetic response helpers ───────────────────────────────────────────

    /** Returns a successful synthetic SAPI token exchange. */
    protected function tokenExchange(): HttpExchangeResult
    {
        return $this->jsonExchange(200, ApiFixtures::token());
    }

    /**
     * Creates a JSON exchange result for the supplied provider status and body.
     *
     * @param  int                  $status HTTP response status.
     * @param  array<string, mixed> $data   Decoded provider response body.
     * @return HttpExchangeResult   Synthetic JSON exchange.
     */
    protected function jsonExchange(int $status, array $data): HttpExchangeResult
    {
        return new HttpExchangeResult($status, (string) json_encode($data), [], microtime(true));
    }

    /**
     * Creates a synthetic connection exception for an executor response queue.
     *
     * @param  string                      $message Diagnostic failure message.
     * @return ProviderConnectionException Synthetic network failure.
     */
    protected function curlFailure(string $message = 'Synthetic connection failure.'): ProviderConnectionException
    {
        return new ProviderConnectionException($message);
    }
}
