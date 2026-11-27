<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi;

use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientId;
use DreaM1\EfakturaConnector\Core\Connection\Health\TokenLifetime;
use DreaM1\EfakturaConnector\Core\Connection\Health\TokenRefreshRecommendation;
use DreaM1\EfakturaConnector\Core\Connection\Health\TokenStatus;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Maps GET /sapi/auth/token/status → 200 SapiTokenStatusResponse to the public token-status model:
 * { valid?, token_type?, client_id?, issued_at?, expires_at?, expires_in_seconds?,
 *   should_refresh?, refresh_recommended_at? }
 *
 * The response schema and TokenStatus carry the same eight optional values, so no separate response object is kept.
 */
final class TokenStatusFactory
{
    private const SCHEMA = 'SapiTokenStatusResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to the public token-status model.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return TokenStatus
     * @throws InvalidProviderResponseException when a supplied token-status field is invalid.
     */
    public static function fromArray(array $data): TokenStatus
    {
        return self::fromData(new ProviderResponseReader($data, self::SCHEMA));
    }

    /**
     * Maps validated provider fields to the public token-status model.
     *
     * @param  ProviderResponseReader           $response Validated access to the provider fields.
     * @return TokenStatus
     * @throws InvalidProviderResponseException when a supplied token-status field is invalid.
     */
    public static function fromData(ProviderResponseReader $response): TokenStatus
    {
        $issuedAt             = $response->optionalTimestamp('issued_at');
        $expiresAt            = $response->optionalTimestamp('expires_at');
        $refreshRecommendedAt = $response->optionalTimestamp('refresh_recommended_at');
        $clientIdValue        = $response->optionalString('client_id');
        $clientId             = null;

        if ($clientIdValue !== null) {
            try {
                $clientId = OAuthClientId::fromString($clientIdValue);
            } catch (InvalidInputException $exception) {
                throw new InvalidProviderResponseException(
                    'The SAPI token status response contains an invalid client ID.',
                    self::SCHEMA,
                    'client_id',
                    $exception
                );
            }
        }

        $valid            = $response->optionalBool('valid');
        $tokenType        = $response->optionalString('token_type');
        $expiresInSeconds = $response->optionalInt('expires_in_seconds');
        $shouldRefresh    = $response->optionalBool('should_refresh');

        return TokenStatus::create(
            $valid,
            $tokenType,
            $clientId,
            new TokenLifetime($issuedAt, $expiresAt, $expiresInSeconds),
            new TokenRefreshRecommendation($shouldRefresh, $refreshRecommendedAt)
        );
    }
}
