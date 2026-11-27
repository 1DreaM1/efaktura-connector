<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Connection;

use DreaM1\EfakturaConnector\Core\Documents\ListReceivedDocumentsRequest;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblDocumentWriterInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\AuthenticatedRequestSender;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\InMemorySapiTokenCache;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenCacheInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenProvider;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\GetTokenStatusEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\RequestTokenEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpTransportInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\SapiClientFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\SapiTokenHealthProvider;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use DreaM1\EfakturaConnector\Tests\Support\TestConfiguration;
use PHPUnit\Framework\TestCase;
use stdClass;

/** The token status check reports the token a client holds instead of silently replacing a rejected one. */
class TokenStatusUnauthorizedTest extends TestCase
{
    public function testSenderWithoutReplaysReturnsTheFirstUnauthorizedResponseAndForgetsTheToken(): void
    {
        $log       = new stdClass();
        $transport = $this->transport($log, [401]);
        $provider  = $this->tokenProvider($transport);
        $connector = new ApiConnector(new AuthenticatedRequestSender($transport, $provider, 0));

        try {
            (new GetTokenStatusEndpoint($connector))->send();
            $this->fail('A 401 must reach the caller when no replay is allowed.');
        } catch (ProviderErrorResponseException $exception) {
            $this->assertSame(401, $exception->rawStatusCode());
        }

        $this->assertSame(1, $log->tokenRequests, 'No replacement token is requested for a replay.');
        $this->assertSame(1, $log->statusRequests, 'The request is not replayed.');

        // The rejected token was forgotten, so the next call authenticates with a fresh one.
        (new GetTokenStatusEndpoint($connector))->send();
        $this->assertSame(2, $log->tokenRequests);
        $this->assertNotSame($log->authorization[0], $log->authorization[1]);
    }

    public function testSenderRejectsAReplayCountOutsideTheSupportedRange(): void
    {
        $transport = $this->createMock(HttpTransportInterface::class);

        foreach ([-1, 2] as $retries) {
            try {
                new AuthenticatedRequestSender($transport, $this->tokenProvider($transport), $retries);
                $this->fail('A replay count of ' . $retries . ' must be rejected.');
            } catch (InvalidInputException $exception) {
                $this->assertStringContainsString('0 or 1', $exception->getMessage());
            }
        }
    }

    public function testUnauthorizedStatusIsReportedAsAnInvalidToken(): void
    {
        $endpoint = $this->createMock(GetTokenStatusEndpoint::class);
        $endpoint->method('send')->willThrowException(new ProviderErrorResponseException(401));
        $provider = new SapiTokenHealthProvider($endpoint);

        $status = $provider->getTokenStatus();

        $this->assertFalse($status->valid());
        $this->assertNull($status->tokenType());
        $this->assertNull($status->clientId());
        $this->assertNull($status->issuedAt());
        $this->assertNull($status->expiresAt());
        $this->assertNull($status->expiresInSeconds());
        $this->assertNull($status->shouldRefresh());
        $this->assertNull($status->refreshRecommendedAt());
        $this->assertFalse($provider->isTokenValid());
    }

    /**
     * @dataProvider otherFailureStatuses
     */
    public function testOtherFailuresStillPropagate(int $statusCode): void
    {
        $failure  = new ProviderErrorResponseException($statusCode);
        $endpoint = $this->createMock(GetTokenStatusEndpoint::class);
        $endpoint->method('send')->willThrowException($failure);

        try {
            (new SapiTokenHealthProvider($endpoint))->getTokenStatus();
            $this->fail('Only a 401 is translated into an invalid token.');
        } catch (ProviderErrorResponseException $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    /** @return array<string, array{int}> */
    public static function otherFailureStatuses(): array
    {
        return ['forbidden' => [403], 'not found' => [404], 'locked' => [423], 'server error' => [503]];
    }

    public function testClientFromTheFactoryReportsARejectedTokenAsInvalidAndRecovers(): void
    {
        $log    = new stdClass();
        $client = $this->clientFromFactory($log, [401]);

        $health = $client->tokenHealthProvider();

        $this->assertFalse($health->isTokenValid());
        $this->assertSame(1, $log->tokenRequests);
        $this->assertSame(1, $log->statusRequests);

        // The rejected token was dropped; the next check obtains a new token and reports it valid.
        $this->assertTrue($health->isTokenValid());
        $this->assertSame(2, $log->tokenRequests);
        $this->assertSame(2, $log->statusRequests);
    }

    public function testClientFromTheFactoryStillReplaysOtherCallsAfterUnauthorized(): void
    {
        $log    = new stdClass();
        $client = $this->clientFromFactory($log, [], [401]);

        // A document operation keeps the transparent replacement of a rejected token.
        $page = $client->receivedDocumentProvider()->getInboxPage(
            new ListReceivedDocumentsRequest(TestConfiguration::participant('0245:2012345676'))
        );

        $this->assertCount(1, $page->documents());
        $this->assertSame(2, $log->tokenRequests);
        $this->assertSame(2, $log->otherRequests);
    }

    /**
     * Builds a transport where the listed status requests (by order) answer 401 and the others succeed.
     *
     * @param int[] $statusResponses HTTP status codes for token status requests, in order; 200 afterwards.
     */
    private function transport(stdClass $log, array $statusResponses): HttpTransportInterface
    {
        $log->tokenRequests  = 0;
        $log->statusRequests = 0;
        $log->authorization  = [];
        $transport           = $this->createMock(HttpTransportInterface::class);
        $transport->method('send')->willReturnCallback(
            function (HttpExchangeRequest $request) use ($log, $statusResponses): HttpExchangeResult {
                return $this->respond($request, $log, $statusResponses, []);
            }
        );

        return $transport;
    }

    /**
     * @param int[] $statusResponses Token status responses in order.
     * @param int[] $otherResponses  Responses for every other API call in order.
     */
    private function clientFromFactory(
        stdClass $log,
        array $statusResponses,
        array $otherResponses = []
    ) {
        $log->tokenRequests  = 0;
        $log->statusRequests = 0;
        $log->otherRequests  = 0;
        $log->authorization  = [];
        $executor            = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(
            function (HttpExchangeRequest $request) use ($log, $statusResponses, $otherResponses): HttpExchangeResult {
                return $this->respond($request, $log, $statusResponses, $otherResponses);
            }
        );

        $factory = new SapiClientFactory(
            static function () use ($executor) {
                return $executor;
            },
            new InMemorySapiTokenCache(),
            $this->createMock(UblDocumentWriterInterface::class)
        );

        return $factory->create(
            'synthetic',
            TestConfiguration::connector('synthetic-client', 'synthetic-secret', 'https://sapi.synthetic.test')
        );
    }

    /**
     * @param int[] $statusResponses Token status responses in order; later requests succeed.
     * @param int[] $otherResponses  Other API call responses in order; later requests succeed.
     */
    private function respond(
        HttpExchangeRequest $request,
        stdClass $log,
        array $statusResponses,
        array $otherResponses
    ): HttpExchangeResult {
        if ($request->path() === '/sapi/auth/token') {
            $log->tokenRequests++;

            return ApiFixtures::jsonExchange([
                'access_token' => 'synthetic-access-' . $log->tokenRequests,
                'token_type'   => 'Bearer',
                'expires_in'   => 900,
            ]);
        }

        foreach ($request->headers()->all() as $headerLine) {
            if ($headerLine->headerName()->isNamed(HttpHeaderName::AUTHORIZATION)) {
                $log->authorization[] = $headerLine->toString();
            }
        }

        if ($request->path() === '/sapi/auth/token/status') {
            $index = $log->statusRequests++;
            $code  = $statusResponses[$index] ?? 200;

            return $code === 401
                ? ApiFixtures::jsonExchange(ApiFixtures::error('Synthetic unauthorized response', [], 'AUTH'), 401)
                : ApiFixtures::jsonExchange(['valid' => true]);
        }

        $index = $log->otherRequests++;
        $code  = $otherResponses[$index] ?? 200;

        return $code === 401
            ? ApiFixtures::jsonExchange(ApiFixtures::error('Synthetic unauthorized response', [], 'AUTH'), 401)
            : ApiFixtures::jsonExchange(ApiFixtures::receivedDocumentList(['doc-1']));
    }

    private function tokenProvider(HttpTransportInterface $transport): SapiTokenProvider
    {
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->method('load')->willReturn(null);

        return new SapiTokenProvider(
            new RequestTokenEndpoint($transport),
            $cache,
            TestConfiguration::connector('synthetic-client', 'synthetic-secret')
        );
    }
}
