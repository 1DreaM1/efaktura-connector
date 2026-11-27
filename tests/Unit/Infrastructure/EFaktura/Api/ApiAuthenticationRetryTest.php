<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\Documents\DocumentRequestContext;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentReference;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentReference;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Domain\EFaktura\MessageLevelStatusIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\AuthenticatedRequestSender;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenCacheInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenProvider;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetMessageLevelStatusContentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\GetReceivedDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\GetTokenStatusEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\RequestTokenEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpTransportInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\MediaType;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use DreaM1\EfakturaConnector\Tests\Support\TestConfiguration;
use PHPUnit\Framework\TestCase;

/** Checks one-time token refresh and request replay after an unauthorized provider response. */
class ApiAuthenticationRetryTest extends TestCase
{
    public function testAuthenticatedRequestObtainsANewTokenAfterUnauthorizedResponse(): void
    {
        $transport            = $this->createMock(HttpTransportInterface::class);
        $tokenRequests        = 0;
        $documentRequests     = 0;
        $authorizationHeaders = [];
        $transport->expects($this->exactly(4))->method('send')->willReturnCallback(
            function (HttpExchangeRequest $request) use (
                &$tokenRequests,
                &$documentRequests,
                &$authorizationHeaders
            ) {
                if ($request->path() === '/sapi/auth/token') {
                    $tokenRequests++;

                    return ApiFixtures::jsonExchange([
                        'access_token' => 'synthetic-access-' . $tokenRequests,
                        'token_type'   => 'Bearer',
                        'expires_in'   => 900,
                    ]);
                }

                $documentRequests++;

                foreach ($request->headers()->all() as $headerLine) {
                    if ($headerLine->headerName()->isNamed(HttpHeaderName::AUTHORIZATION)) {
                        $authorizationHeaders[] = $headerLine->toString();
                    }
                }

                if ($documentRequests === 1) {
                    return ApiFixtures::jsonExchange(
                        ApiFixtures::error('Synthetic unauthorized response', [], 'AUTH'),
                        401
                    );
                }

                return ApiFixtures::jsonExchange(['valid' => true]);
            }
        );

        $connector = new ApiConnector(
            new AuthenticatedRequestSender($transport, $this->tokenProvider($transport))
        );
        $result = (new GetTokenStatusEndpoint($connector))->send();

        $this->assertTrue($result->valid());
        $this->assertSame(2, $tokenRequests);
        $this->assertSame(2, $documentRequests);
        $this->assertNotSame($authorizationHeaders[0], $authorizationHeaders[1]);
    }

    public function testEndpointHeadersAreForwardedAlongWithTransportManagedHeaders(): void
    {
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->exactly(2))->method('send')->willReturnCallback(
            function (HttpExchangeRequest $request) {
                if ($request->path() === '/sapi/auth/token') {
                    return ApiFixtures::jsonExchange([
                        'access_token' => 'synthetic-access-token',
                        'token_type'   => 'Bearer',
                        'expires_in'   => 900,
                    ]);
                }

                $this->assertSame(MediaType::APPLICATION_JSON, $request->acceptHeader());
                $this->assertSame([
                    'X-Peppol-Participant-Id: 0245:2012345676',
                    'Authorization: Bearer synthetic-access-token',
                ], array_map(
                    static function (HttpHeaderLine $headerLine): string {
                        return $headerLine->toString();
                    },
                    $request->headers()->all()
                ));

                return ApiFixtures::jsonExchange(
                    ApiFixtures::receivedDocumentDetail('<Invoice/>')
                );
            }
        );
        $connector = new ApiConnector(
            new AuthenticatedRequestSender($transport, $this->tokenProvider($transport))
        );

        $result = (new GetReceivedDocumentEndpoint($connector))->send(
            new ReceivedDocumentReference(
                TestConfiguration::participant(),
                ProviderDocumentId::fromString('provider-document'),
                new DocumentRequestContext('provider-document', null)
            )
        );

        $this->assertNotNull($result);
    }

    public function testSecondUnauthorizedResponseClearsTokenBeforeTheNextApiCall(): void
    {
        $tokenRequests        = 0;
        $documentRequests     = 0;
        $authorizationHeaders = [];
        $transport            = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->exactly(6))->method('send')->willReturnCallback(
            function (HttpExchangeRequest $request) use (
                &$tokenRequests,
                &$documentRequests,
                &$authorizationHeaders
            ) {
                if ($request->path() === '/sapi/auth/token') {
                    $tokenRequests++;

                    return ApiFixtures::jsonExchange([
                        'access_token' => 'synthetic-access-' . $tokenRequests,
                        'token_type'   => 'Bearer',
                        'expires_in'   => 900,
                    ]);
                }

                $documentRequests++;

                foreach ($request->headers()->all() as $headerLine) {
                    if ($headerLine->headerName()->isNamed(HttpHeaderName::AUTHORIZATION)) {
                        $authorizationHeaders[] = $headerLine->toString();
                    }
                }

                if ($documentRequests <= 2) {
                    return ApiFixtures::jsonExchange(
                        ApiFixtures::error('Synthetic unauthorized response', [], 'AUTH'),
                        401
                    );
                }

                return ApiFixtures::jsonExchange(['valid' => true]);
            }
        );
        $connector = new ApiConnector(
            new AuthenticatedRequestSender($transport, $this->tokenProvider($transport))
        );

        try {
            (new GetTokenStatusEndpoint($connector))->send();
            $this->fail('The client must return the second unauthorized response.');
        } catch (ProviderApiException $exception) {
            $this->assertSame(401, $exception->httpStatusCode()->value());
        }

        $result = (new GetTokenStatusEndpoint($connector))->send();

        $this->assertTrue($result->valid());
        $this->assertSame(3, $tokenRequests);
        $this->assertSame(3, $documentRequests);
        $this->assertNotSame($authorizationHeaders[0], $authorizationHeaders[1]);
        $this->assertNotSame($authorizationHeaders[1], $authorizationHeaders[2]);
    }

    public function testPeppolXmlRequestUsesParticipantHeaderAndObtainsANewTokenAfterUnauthorizedResponse(): void
    {
        $transport            = $this->createMock(HttpTransportInterface::class);
        $tokenRequests        = 0;
        $xmlRequests          = 0;
        $authorizationHeaders = [];
        $transport->expects($this->exactly(4))->method('send')->willReturnCallback(
            function (HttpExchangeRequest $request) use (
                &$tokenRequests,
                &$xmlRequests,
                &$authorizationHeaders
            ) {
                if ($request->path() === '/sapi/auth/token') {
                    $this->assertSame(MediaType::APPLICATION_JSON, $request->acceptHeader());
                    $this->assertSame('POST', $request->httpMethod()->value());
                    $tokenRequests++;

                    return ApiFixtures::jsonExchange([
                        'access_token' => 'synthetic-xml-access-' . $tokenRequests,
                        'token_type'   => 'Bearer',
                        'expires_in'   => 900,
                    ]);
                }

                $this->assertSame(MediaType::APPLICATION_XML, $request->acceptHeader());
                $this->assertSame('GET', $request->httpMethod()->value());
                $this->assertSame(
                    '/v1/sent-documents/provider-out-1/message-level-statuses/mls-1/content',
                    $request->path()
                );
                $participantHeader = false;

                foreach ($request->headers()->all() as $headerLine) {
                    if ($headerLine->headerName()->isNamed(HttpHeaderName::AUTHORIZATION)) {
                        $authorizationHeaders[] = $headerLine->toString();
                    }

                    if (
                        $headerLine->headerName()->isNamed(HttpHeaderName::PEPPOL_PARTICIPANT_ID)
                        && $headerLine->value() === '0245:2012345676'
                    ) {
                        $participantHeader = true;
                    }
                }

                $this->assertTrue($participantHeader);
                $xmlRequests++;

                if ($xmlRequests === 1) {
                    return ApiFixtures::jsonExchange(
                        ApiFixtures::error('Synthetic unauthorized XML response', [], 'AUTH'),
                        401
                    );
                }

                return ApiFixtures::xmlExchange('<ApplicationResponse />');
            }
        );

        $connector = new ApiConnector(
            new AuthenticatedRequestSender($transport, $this->tokenProvider($transport))
        );
        $result = (new GetMessageLevelStatusContentEndpoint($connector))->send(
            new SentDocumentReference(
                TestConfiguration::participant(),
                ProviderDocumentId::fromString('provider-out-1'),
                new DocumentRequestContext('provider-out-1', null)
            ),
            MessageLevelStatusIdentifier::fromString('mls-1')
        );

        $this->assertSame('<ApplicationResponse />', $result->xml());
        $this->assertSame(2, $tokenRequests);
        $this->assertNotSame($authorizationHeaders[0], $authorizationHeaders[1]);
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
