<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfiguration;
use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestDeferredException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;
use DreaM1\EfakturaConnector\Core\Exceptions\TokenRequestException;
use DreaM1\EfakturaConnector\Core\Retry\RetryDeadline;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\OAuthAccessToken;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiToken;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenCacheInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenProvider;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenRefreshLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\RequestTokenEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpTransportInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use DreaM1\EfakturaConnector\Tests\Support\FrozenClock;
use DreaM1\EfakturaConnector\Tests\Support\RecordingConnectorMetrics;
use DreaM1\EfakturaConnector\Tests\Support\TestConfiguration;
use PHPUnit\Framework\TestCase;

/** Covers token refresh, cache identity, expiry margins and provider or local failure handling. */
class SapiTokenProviderTest extends TestCase
{
    public function testAccessTokenContractExposesTypedSecretWithoutStringAlias(): void
    {
        $this->assertTrue(method_exists(SapiToken::class, 'accessToken'));
        $this->assertFalse(method_exists(SapiToken::class, 'value'));
    }

    public function testRecordsCacheMissAndSuccessfulRefreshWithoutExposingToken(): void
    {
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->expects($this->once())->method('load')->willReturn(null);
        $cache->expects($this->once())->method('store');
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturn(
            ApiFixtures::jsonExchange([
                'access_token' => 'sensitive-synthetic-token',
                'token_type'   => 'Bearer',
                'expires_in'   => 300,
            ])
        );
        $metrics  = new RecordingConnectorMetrics();
        $provider = new SapiTokenProvider(
            new RequestTokenEndpoint($transport),
            $cache,
            $this->credentials(),
            null,
            $metrics
        );

        $provider->getToken();

        $this->assertSame([
            ConnectorMetricsInterface::TOKEN_CACHE_MISS,
            ConnectorMetricsInterface::TOKEN_REFRESH_SUCCESS,
        ], $metrics->tokenCacheEvents);
        $this->assertStringNotContainsString('sensitive-synthetic-token', json_encode($metrics->tokenCacheEvents));
    }

    public function testRecordsInMemoryTokenCacheHit(): void
    {
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->expects($this->once())->method('load')->willReturn(null);
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturn(
            ApiFixtures::jsonExchange([
                'access_token' => 'issued-token',
                'token_type'   => 'Bearer',
                'expires_in'   => 300,
            ])
        );
        $metrics  = new RecordingConnectorMetrics();
        $provider = new SapiTokenProvider(
            new RequestTokenEndpoint($transport),
            $cache,
            $this->credentials(),
            null,
            $metrics
        );

        $provider->getToken();
        $provider->getToken();

        $this->assertSame([
            ConnectorMetricsInterface::TOKEN_CACHE_MISS,
            ConnectorMetricsInterface::TOKEN_REFRESH_SUCCESS,
            ConnectorMetricsInterface::TOKEN_MEMORY_HIT,
        ], $metrics->tokenCacheEvents);
    }

    public function testReturnsCachedTokenWithoutRequestingAnotherOne(): void
    {
        $cache       = $this->createMock(SapiTokenCacheInterface::class);
        $credentials = $this->credentials();
        $cache->expects($this->once())
            ->method('load')
            ->with($this->scopedCacheKey())
            ->willReturn(new SapiToken(OAuthAccessToken::fromString('cached-token'), time() + 300));

        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->never())->method('send');
        $metrics  = new RecordingConnectorMetrics();
        $provider = new SapiTokenProvider(new RequestTokenEndpoint($transport), $cache, $credentials, null, $metrics);

        $this->assertSame('cached-token', $provider->getToken()->accessToken()->reveal());
        $this->assertSame('cached-token', $provider->getToken()->accessToken()->reveal());
        $this->assertSame([
            ConnectorMetricsInterface::TOKEN_CACHE_HIT,
            ConnectorMetricsInterface::TOKEN_MEMORY_HIT,
        ], $metrics->tokenCacheEvents);
    }

    public function testExpiredCachedTokenIsRemovedBeforeRequestingAReplacement(): void
    {
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->method('load')->willReturn(new SapiToken(OAuthAccessToken::fromString('expired-token'), time() - 1));
        $cache->expects($this->once())->method('forget')->with($this->scopedCacheKey());

        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->willReturn(ApiFixtures::jsonExchange([
                'access_token' => 'replacement-token',
                'token_type'   => 'Bearer',
                'expires_in'   => 300,
            ]));

        $provider = new SapiTokenProvider(new RequestTokenEndpoint($transport), $cache, $this->credentials());

        $this->assertSame('replacement-token', $provider->getToken()->accessToken()->reveal());
    }

    public function testRequestsAndCachesNewTokenOnce(): void
    {
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->expects($this->once())->method('load')->willReturn(null);
        $cache->expects($this->once())->method('store')->with(
            $this->scopedCacheKey(),
            $this->callback(function ($token) {
                return $token instanceof SapiToken
                    && $token->accessToken()->reveal() === 'issued-token'
                    && $token->expiresAt() > time();
            })
        );

        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(
            function (HttpExchangeRequest $request) {
                $this->assertSame('POST', $request->httpMethod()->value());
                $this->assertSame('/sapi/auth/token', $request->path());
                $this->assertSame([], $request->headers()->all());
                $this->assertSame([
                    'client_id'     => 'synthetic-client',
                    'client_secret' => 'synthetic-secret',
                    'grant_type'    => 'client_credentials',
                    'scope'         => 'document:send document:receive',
                ], json_decode($request->body(), true));

                return ApiFixtures::jsonExchange([
                    'access_token' => 'issued-token',
                    'token_type'   => 'Bearer',
                    'expires_in'   => 300,
                ]);
            }
        );
        $provider = new SapiTokenProvider(new RequestTokenEndpoint($transport), $cache, $this->credentials());

        $this->assertSame('issued-token', $provider->getToken()->accessToken()->reveal());
        $this->assertSame('issued-token', $provider->getToken()->accessToken()->reveal());
    }

    public function testRefreshLockRechecksCacheBeforeRequestingAnotherToken(): void
    {
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->expects($this->exactly(2))->method('load')->willReturnOnConsecutiveCalls(
            null,
            new SapiToken(OAuthAccessToken::fromString('refreshed-by-another-worker'), time() + 300)
        );
        $cache->expects($this->never())->method('store');

        $lock = $this->createMock(SapiTokenRefreshLockInterface::class);
        $lock->expects($this->once())->method('synchronized')->with(
            $this->scopedCacheKey(),
            $this->greaterThan(0),
            $this->greaterThan(0),
            $this->isType('callable')
        )->willReturnCallback(function ($cacheKey, $leaseSeconds, $waitSeconds, callable $operation) {
            return $operation();
        });

        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->never())->method('send');
        $provider = new SapiTokenProvider(
            new RequestTokenEndpoint($transport),
            $cache,
            $this->credentials(),
            $lock
        );

        $this->assertSame('refreshed-by-another-worker', $provider->getToken()->accessToken()->reveal());
    }

    public function testRefreshLockCountsASingleCacheMissForOneRefresh(): void
    {
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->method('load')->willReturn(null);
        $lock = $this->createMock(SapiTokenRefreshLockInterface::class);
        $lock->method('synchronized')->willReturnCallback(
            function ($cacheKey, $leaseSeconds, $waitSeconds, callable $operation) {
                return $operation();
            }
        );
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->method('send')->willReturn(ApiFixtures::jsonExchange([
            'access_token' => 'issued-token',
            'token_type'   => 'Bearer',
            'expires_in'   => 300,
        ]));
        $metrics  = new RecordingConnectorMetrics();
        $provider = new SapiTokenProvider(
            new RequestTokenEndpoint($transport),
            $cache,
            $this->credentials(),
            $lock,
            $metrics
        );

        $provider->getToken();

        $this->assertSame([
            ConnectorMetricsInterface::TOKEN_CACHE_MISS,
            ConnectorMetricsInterface::TOKEN_REFRESH_SUCCESS,
        ], $metrics->tokenCacheEvents);
    }

    public function testForgetClearsTheRejectedTokenFromMemoryAndCache(): void
    {
        $rejected = new SapiToken(OAuthAccessToken::fromString('rejected-token'), time() + 300);
        $cache    = $this->createMock(SapiTokenCacheInterface::class);
        $cache->method('load')->willReturn($rejected);
        $cache->expects($this->once())->method('forget')->with($this->scopedCacheKey());
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->never())->method('send');
        $provider = new SapiTokenProvider(new RequestTokenEndpoint($transport), $cache, $this->credentials());

        $this->assertSame('rejected-token', $provider->getToken()->accessToken()->reveal());

        $provider->forget($rejected);
    }

    public function testForgetKeepsATokenAnotherWorkerAlreadyRefreshed(): void
    {
        $rejected = new SapiToken(OAuthAccessToken::fromString('rejected-token'), time() + 300);
        $fresh    = new SapiToken(OAuthAccessToken::fromString('fresh-token'), time() + 300);
        $cache    = $this->createMock(SapiTokenCacheInterface::class);
        $cache->method('load')->willReturnOnConsecutiveCalls($rejected, $fresh);
        $cache->expects($this->never())->method('forget');
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->never())->method('send');
        $provider = new SapiTokenProvider(new RequestTokenEndpoint($transport), $cache, $this->credentials());

        $provider->getToken();
        $provider->forget($rejected);
    }

    public function testForgetBeforeAnyTokenWasResolvedDoesNothing(): void
    {
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->expects($this->never())->method('load');
        $cache->expects($this->never())->method('forget');
        $transport = $this->createMock(HttpTransportInterface::class);
        $provider  = new SapiTokenProvider(new RequestTokenEndpoint($transport), $cache, $this->credentials());

        $provider->forget(new SapiToken(OAuthAccessToken::fromString('unknown-token'), time() + 300));

        $this->addToAssertionCount(1);
    }

    public function testRequestsTokenAndUsesCacheKeyForTheCurrentScope(): void
    {
        $cacheKeys = [];
        $cache     = $this->createMock(SapiTokenCacheInterface::class);
        $cache->expects($this->exactly(2))->method('load')->willReturnCallback(function ($key) use (&$cacheKeys) {
            $cacheKeys[] = $key;

            return null;
        });

        $requestedScopes = [];
        $transport       = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->exactly(2))->method('send')->willReturnCallback(
            function (HttpExchangeRequest $request) use (&$requestedScopes) {
                $requestedScopes[] = json_decode($request->body(), true)['scope'];

                return ApiFixtures::jsonExchange([
                    'access_token' => 'issued-token-' . count($requestedScopes),
                    'token_type'   => 'Bearer',
                    'expires_in'   => 300,
                    'scope'        => $requestedScopes[count($requestedScopes) - 1],
                ]);
            }
        );
        $sendTokenProvider = new SapiTokenProvider(
            new RequestTokenEndpoint($transport),
            $cache,
            $this->credentials('synthetic-client', 'synthetic-secret', 'document:send')
        );
        $receiveTokenProvider = new SapiTokenProvider(
            new RequestTokenEndpoint($transport),
            $cache,
            $this->credentials('synthetic-client', 'synthetic-secret', 'document:receive')
        );

        $sendToken    = $sendTokenProvider->getToken();
        $receiveToken = $receiveTokenProvider->getToken();

        $this->assertSame('issued-token-1', $sendToken->accessToken()->reveal());
        $this->assertSame('issued-token-2', $receiveToken->accessToken()->reveal());

        $this->assertSame(['document:send', 'document:receive'], $requestedScopes);
        $this->assertNotSame($cacheKeys[0], $cacheKeys[1]);
    }

    public function testDoesNotRequestATokenWhenNoScopeIsConfigured(): void
    {
        $cache     = $this->createMock(SapiTokenCacheInterface::class);
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->never())->method('send');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('OAuth scope is not configured');

        (new SapiTokenProvider(
            new RequestTokenEndpoint($transport),
            $cache,
            $this->credentials('synthetic-client', 'synthetic-secret', '')
        ))->getToken();
    }

    public function testAcceptsTheSpecifiedBearerTokenType(): void
    {
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->method('load')->willReturn(null);

        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->willReturn(ApiFixtures::jsonExchange([
                'access_token' => 'issued-token',
                'token_type'   => 'Bearer',
                'expires_in'   => 300,
            ]));

        $provider = new SapiTokenProvider(new RequestTokenEndpoint($transport), $cache, $this->credentials());

        $this->assertSame('issued-token', $provider->getToken()->accessToken()->reveal());
    }

    /**
     * Rejects malformed expiry values returned in token responses.
     *
     * @param mixed $expiresIn Candidate expires_in value.
     * @dataProvider invalidExpiryValues
     */
    public function testRejectsMalformedTokenExpiryValues($expiresIn): void
    {
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->method('load')->willReturn(null);
        $cache->expects($this->never())->method('store');

        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->willReturn(ApiFixtures::jsonExchange([
                'access_token' => 'issued-token',
                'token_type'   => 'Bearer',
                'expires_in'   => $expiresIn,
            ]));

        $this->expectException(TokenRequestException::class);

        (new SapiTokenProvider(new RequestTokenEndpoint($transport), $cache, $this->credentials()))->getToken();
    }

    public function testUnsupportedExpiryRetainsTokenResponseContext(): void
    {
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->method('load')->willReturn(null);
        $cache->expects($this->never())->method('store');

        $headers = [
            'content-type' => 'application/json',
            'x-request-id' => 'synthetic-token-response',
        ];
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->willReturn(new HttpExchangeResult(
                200,
                json_encode([
                    'access_token' => 'issued-token',
                    'token_type'   => 'Bearer',
                    'expires_in'   => PHP_INT_MAX,
                ]),
                $headers,
                microtime(true)
            ));

        try {
            (new SapiTokenProvider(new RequestTokenEndpoint($transport), $cache, $this->credentials()))->getToken();
            $this->fail('An unsupported token lifetime should be rejected.');
        } catch (TokenRequestException $exception) {
            $this->assertSame(200, $exception->httpStatusCode()->value());
            $this->assertSame($headers, $exception->responseHeaders());
            $this->assertInstanceOf(InvalidProviderResponseException::class, $exception->getPrevious());
        }
    }

    public function testRejectsTokenResponseWithoutRequiredExpiry(): void
    {
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->method('load')->willReturn(null);
        $cache->expects($this->never())->method('store');

        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->willReturn(ApiFixtures::jsonExchange([
                'access_token' => 'issued-token',
                'token_type'   => 'Bearer',
            ]));

        $this->expectException(TokenRequestException::class);

        (new SapiTokenProvider(new RequestTokenEndpoint($transport), $cache, $this->credentials()))->getToken();
    }

    /**
     * Provides malformed access-token expiry values.
     *
     * @return array<array-key, array<array-key, mixed>> Invalid expiry cases.
     */
    public function invalidExpiryValues(): array
    {
        return [
            'array'      => [['unexpected' => 'value']],
            'negative'   => [-1],
            'fractional' => [1.5],
            'text'       => ['not-a-number'],
            'too large'  => [PHP_INT_MAX],
        ];
    }

    public function testRejectsMissingOAuthCredentials(): void
    {
        $cache     = $this->createMock(SapiTokenCacheInterface::class);
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->never())->method('send');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('OAuth client ID or secret');

        (new SapiTokenProvider(
            new RequestTokenEndpoint($transport),
            $cache,
            $this->credentials('synthetic-client', '')
        ))
            ->getToken();
    }

    public function testWrapsConnectionFailuresAsTokenAcquisitionFailures(): void
    {
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->method('load')->willReturn(null);

        $connectionException = new ProviderConnectionException('Synthetic connection failure.');
        $transport           = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->once())->method('send')->willThrowException($connectionException);

        try {
            (new SapiTokenProvider(new RequestTokenEndpoint($transport), $cache, $this->credentials()))->getToken();
            $this->fail('A token endpoint connection failure must be reported.');
        } catch (ProviderApiException $exception) {
            $this->assertSame($connectionException, $exception->getPrevious());
        }
    }

    public function testPropagatesDeferredTokenRequestWithItsRetryDeadline(): void
    {
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->method('load')->willReturn(null);
        $cache->expects($this->never())->method('store');

        $retryAt           = RetryDeadline::fromUnixTimestamp(time() + 60);
        $deferredException = new RequestDeferredException($retryAt);
        $transport         = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->once())->method('send')->willThrowException($deferredException);

        try {
            (new SapiTokenProvider(new RequestTokenEndpoint($transport), $cache, $this->credentials()))->getToken();
            $this->fail('A locally deferred token request must retain its retry deadline.');
        } catch (RequestDeferredException $exception) {
            $this->assertSame($deferredException, $exception);
            $this->assertSame($retryAt, $exception->retryAt());
        }
    }

    public function testPreservesProviderStatusWhenTokenRequestFails(): void
    {
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->method('load')->willReturn(null);

        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturn(
            ApiFixtures::jsonExchange(
                ApiFixtures::error('Synthetic auth detail.', [], 'AUTH'),
                401
            )
        );

        try {
            (new SapiTokenProvider(new RequestTokenEndpoint($transport), $cache, $this->credentials()))->getToken();
            $this->fail('Provider authentication failures must be reported as token acquisition failures.');
        } catch (ProviderApiException $exception) {
            $this->assertSame(401, $exception->httpStatusCode()->value());
            $this->assertInstanceOf(TokenRequestException::class, $exception);
            $this->assertInstanceOf(ProviderErrorResponseException::class, $exception->getPrevious());
        }
    }

    public function testRejectsMalformedOAuthTokenResponse(): void
    {
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->method('load')->willReturn(null);

        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->willReturn(ApiFixtures::jsonExchange([
                'access_token' => ['invalid' => 'token'],
                'token_type'   => 'Bearer',
                'expires_in'   => 300,
            ]));

        $this->expectException(TokenRequestException::class);

        (new SapiTokenProvider(new RequestTokenEndpoint($transport), $cache, $this->credentials()))->getToken();
    }

    public function testMalformedTokenApiResponseIsClassifiedAsInvalidOAuthResponse(): void
    {
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->method('load')->willReturn(null);
        $malformedResponse = InvalidProviderResponseException::malformedJson();

        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->once())->method('send')->willThrowException($malformedResponse);

        try {
            (new SapiTokenProvider(new RequestTokenEndpoint($transport), $cache, $this->credentials()))->getToken();
            $this->fail('Malformed OAuth responses must be reported as invalid provider responses.');
        } catch (ProviderApiException $exception) {
            $this->assertSame($malformedResponse, $exception->getPrevious());
        }
    }

    /**
     * Propagates local configuration failures without wrapping them as provider failures.
     *
     * @param RequestEncodingException $failure Configuration failure expected from the operation.
     * @dataProvider configurationFailures
     */
    public function testDoesNotWrapLocalConfigurationFailuresAsProviderFailures(RequestEncodingException $failure): void
    {
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->method('load')->willReturn(null);

        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->once())->method('send')->willThrowException($failure);

        try {
            (new SapiTokenProvider(new RequestTokenEndpoint($transport), $cache, $this->credentials()))->getToken();
            $this->fail('Local configuration failures must retain their original type.');
        } catch (RequestEncodingException $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    /**
     * Provides local configuration failures that must not be wrapped as provider errors.
     *
     * @return array<array-key, array<array-key, mixed>> Configuration failure cases.
     */
    public function configurationFailures(): array
    {
        return [
            'request encoding' => [new RequestEncodingException()],
        ];
    }

    public function testRejectsBearerTokensThatCouldInjectRequestHeaders(): void
    {
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->method('load')->willReturn(null);
        $cache->expects($this->never())->method('store');

        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->willReturn(ApiFixtures::jsonExchange([
                'access_token' => "issued-token\r\nX-Injected: true",
                'token_type'   => 'Bearer',
                'expires_in'   => 300,
            ]));

        $this->expectException(TokenRequestException::class);

        (new SapiTokenProvider(new RequestTokenEndpoint($transport), $cache, $this->credentials()))->getToken();
    }

    public function testTokenExpiryFollowsTheInjectedClock(): void
    {
        $clock = new FrozenClock();
        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->method('load')->willReturn(null);
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->exactly(2))->method('send')->willReturnOnConsecutiveCalls(
            ApiFixtures::jsonExchange([
                'access_token' => 'first-token',
                'token_type'   => 'Bearer',
                'expires_in'   => 300,
            ]),
            ApiFixtures::jsonExchange([
                'access_token' => 'second-token',
                'token_type'   => 'Bearer',
                'expires_in'   => 300,
            ])
        );
        $provider = new SapiTokenProvider(
            new RequestTokenEndpoint($transport),
            $cache,
            $this->credentials(),
            null,
            null,
            $clock
        );

        $first = $provider->getToken();
        $this->assertSame('first-token', $first->accessToken()->reveal());
        $this->assertSame(
            FrozenClock::EPOCH + 300 - SapiTokenProvider::EXPIRY_MARGIN_SECONDS,
            $first->expiresAt()
        );

        $clock->advance(300 - SapiTokenProvider::EXPIRY_MARGIN_SECONDS - 1);
        $this->assertSame('first-token', $provider->getToken()->accessToken()->reveal());

        $clock->advance(1);
        $this->assertSame('second-token', $provider->getToken()->accessToken()->reveal());
    }

    private function scopedCacheKey(): string
    {
        return TestConfiguration::tokenCacheKey();
    }

    /**
     * Creates synthetic connector credentials for token-provider scenarios.
     *
     * @param string $clientId     OAuth client identifier.
     * @param string $clientSecret OAuth client secret.
     * @param string $scope        Requested OAuth scope.
     */
    private function credentials(
        string $clientId = 'synthetic-client',
        string $clientSecret = 'synthetic-secret',
        string $scope = 'document:send document:receive'
    ): SapiClientConfiguration {
        return TestConfiguration::connector($clientId, $clientSecret, TestConfiguration::BASE_URL, $scope);
    }
}
