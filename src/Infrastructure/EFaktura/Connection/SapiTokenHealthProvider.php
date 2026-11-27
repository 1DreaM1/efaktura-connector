<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection;

use DreaM1\EfakturaConnector\Core\Connection\Health\TokenLifetime;
use DreaM1\EfakturaConnector\Core\Connection\Health\TokenRefreshRecommendation;
use DreaM1\EfakturaConnector\Core\Connection\Health\TokenStatus;
use DreaM1\EfakturaConnector\Core\Contracts\TokenHealthProviderInterface;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\GetTokenStatusEndpoint;

/**
 * Implements the health check by requesting the status of a SAPI access token.
 *
 * SAPI answers 401 for an invalid or expired token. The provider reports that as a status with valid = false
 * instead of an exception, provided the endpoint's connector hands the 401 over rather than replacing the token
 * (SapiClientFactory wires it that way). Other failures still propagate as typed provider exceptions.
 */
class SapiTokenHealthProvider implements TokenHealthProviderInterface
{
    /** Diagnostic message used when SAPI omits the token validity status. */
    private const INVALID_STATUS_MESSAGE = 'SAPI returned an invalid token status.';

    /** HTTP status SAPI uses for an invalid or expired token. */
    private const UNAUTHORIZED_STATUS = 401;

    private GetTokenStatusEndpoint $tokenStatus;

    /**
     * @param GetTokenStatusEndpoint $tokenStatus Endpoint used to request the current token status.
     */
    public function __construct(GetTokenStatusEndpoint $tokenStatus)
    {
        $this->tokenStatus = $tokenStatus;
    }

    /**
     * Whether SAPI reports the current access token as valid; false when it answers 401 for that token.
     *
     * @return bool
     * @throws InvalidProviderResponseException when SAPI omits the token validity status.
     * @throws ProviderApiException             when the provider status cannot be retrieved.
     * @throws RequestCoordinationException     when local request coordination cannot be established.
     */
    public function isTokenValid(): bool
    {
        $status = $this->getTokenStatus();
        $valid  = $status->valid();

        if ($valid === null) {
            throw new InvalidProviderResponseException(self::INVALID_STATUS_MESSAGE);
        }

        return $valid;
    }

    /**
     * Returns the status and refresh guidance for the current SAPI token.
     *
     * A 401 response yields a status with valid = false and no other fields: SAPI sends no token details for a
     * rejected token, so they stay unknown. The rejected token has already been forgotten locally, and the next
     * API call obtains a new one.
     *
     * @return TokenStatus
     * @throws ProviderApiException         when the provider status cannot be retrieved for another reason.
     * @throws RequestCoordinationException when local request coordination cannot be established.
     */
    public function getTokenStatus(): TokenStatus
    {
        try {
            return $this->tokenStatus->send();
        } catch (ProviderErrorResponseException $exception) {
            if ($exception->rawStatusCode() !== self::UNAUTHORIZED_STATUS) {
                throw $exception;
            }

            return TokenStatus::create(
                false,
                null,
                null,
                new TokenLifetime(),
                new TokenRefreshRecommendation()
            );
        }
    }
}
