<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication;

use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpTransportInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use Throwable;

/**
 * Decorates API transport requests with the configured SAPI bearer token.
 *
 * After one 401 response it forgets the local token, obtains a replacement through the token
 * provider, and replays the original request once. This authorization recovery is separate from
 * the HTTP executor's retry policy. The token endpoint must use the plain transport, not this
 * decorator, because a token is required before this sender can authorize a request.
 *
 * A sender created with zero replays still forgets a rejected token but returns the 401 response to its
 * caller. That is how a token status check reports the token it holds instead of silently checking a new one.
 */
class AuthenticatedRequestSender implements HttpTransportInterface
{
    /** Default number of one-time request replays allowed after an unauthorized response. */
    private const MAX_UNAUTHORIZED_RETRIES = 1;

    private HttpTransportInterface $transport;
    private SapiTokenProvider $tokenProvider;
    private int $unauthorizedRetries;

    /**
     * @param  HttpTransportInterface $transport           HTTP transport used to perform the request.
     * @param  SapiTokenProvider      $tokenProvider       Provider used to acquire SAPI access tokens.
     * @param  int                    $unauthorizedRetries Replays with a fresh token after a 401: 1 (the default) or 0
     *                                                     to return the first 401 to the caller.
     * @throws InvalidInputException  when the number of replays is not 0 or 1.
     */
    public function __construct(
        HttpTransportInterface $transport,
        SapiTokenProvider $tokenProvider,
        int $unauthorizedRetries = self::MAX_UNAUTHORIZED_RETRIES
    ) {
        if ($unauthorizedRetries < 0 || $unauthorizedRetries > self::MAX_UNAUTHORIZED_RETRIES) {
            throw new InvalidInputException('The number of unauthorized replays must be 0 or 1.');
        }

        $this->transport           = $transport;
        $this->tokenProvider       = $tokenProvider;
        $this->unauthorizedRetries = $unauthorizedRetries;
    }

    /**
     * Sends the request with authorization, retrying once only when the provider responds with 401.
     *
     * The final exchange is returned unchanged, including a second 401 response.
     *
     * @param  HttpExchangeRequest          $request Prepared HTTP request to send with provider authentication.
     * @return HttpExchangeResult
     * @throws ProviderApiException         when token acquisition or the executor request fails
     * @throws RequestEncodingException     when token request data cannot be encoded
     * @throws RequestCoordinationException when local request coordination fails
     * @throws ConfigurationException       when required OAuth settings are missing
     * @throws InvalidInputException        when the request path or authentication header is invalid
     */
    public function send(HttpExchangeRequest $request): HttpExchangeResult
    {
        $unauthorizedRetries = 0;

        while (true) {
            // Rebuild the header for each send so a replay uses the token obtained after the prior 401.
            $token = $this->tokenProvider->getToken();

            $exchange = $this->transport->send($this->withAccessToken($request, $token));

            if ($exchange->httpStatusCode() !== null && $exchange->httpStatusCode()->isUnauthorized()) {
                // Clear even on the final 401 so a later API call does not reuse the rejected credential. Only
                // the rejected token is cleared; a token another worker has already refreshed is kept.
                $this->tokenProvider->forget($token);

                // Count only extra sends: the initial request may be replayed once with a fresh token.
                if ($unauthorizedRetries >= $this->unauthorizedRetries) {
                    return $exchange;
                }

                $unauthorizedRetries++;
                continue;
            }

            return $exchange;
        }
    }

    /** Replaces any supplied authorization header with the SAPI bearer token. */
    private function withAccessToken(HttpExchangeRequest $request, SapiToken $token): HttpExchangeRequest
    {
        return $request->withHeader(
            HttpHeaderName::authorization(),
            OAuthAuthorizationScheme::BEARER . ' ' . $token->accessToken()->reveal()
        );
    }
}
