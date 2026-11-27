<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi;

use DreaM1\EfakturaConnector\Core\Configuration\OAuthScope;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\OAuthAccessToken;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\OAuthAuthorizationScheme;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\OAuthTokenLifetime;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ReadsProviderResponse;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * POST /sapi/auth/token → 200 SapiTokenResponse:
 * { access_token, token_type: "Bearer", expires_in, scope? }
 */
final class SapiTokenResponse
{
    use ReadsProviderResponse;

    /** Subtracted by the token provider before locally enforcing provider expiry. */
    public const EXPIRY_MARGIN_SECONDS = 180;

    private const SCHEMA = 'SapiTokenResponse';

    /** OAuth access token returned by SAPI. */
    public OAuthAccessToken $accessToken;
    /** Token type returned by SAPI, normally Bearer. */
    public OAuthAuthorizationScheme $tokenType;
    /** Token lifetime reported by SAPI. */
    public OAuthTokenLifetime $expiresIn;
    /** Granted OAuth scopes, when returned by SAPI. */
    public ?OAuthScope $scope;

    /**
     * @param OAuthAccessToken         $accessToken OAuth access token used to authorize the provider request.
     * @param OAuthAuthorizationScheme $tokenType   OAuth token type reported by the provider.
     * @param OAuthTokenLifetime       $expiresIn   Token lifetime reported by the provider.
     * @param OAuthScope|null          $scope       OAuth permission scope granted by SAPI.
     */
    public function __construct(
        OAuthAccessToken $accessToken,
        OAuthAuthorizationScheme $tokenType,
        OAuthTokenLifetime $expiresIn,
        ?OAuthScope $scope
    ) {
        $this->accessToken = $accessToken;
        $this->tokenType   = $tokenType;
        $this->expiresIn   = $expiresIn;
        $this->scope       = $scope;
    }

    /**
     * Creates a value from the supplied array data.
     *
     * @param  ProviderResponseReader           $response Validated access to the provider fields.
     * @return self
     * @throws InvalidProviderResponseException when a token response field is missing, invalid or unsupported.
     */
    public static function fromData(ProviderResponseReader $response): self
    {
        $accessTokenValue = $response->requiredString('access_token');
        $tokenTypeValue   = $response->requiredString('token_type');
        $expiresInValue   = $response->requiredInt('expires_in');
        $scopeValue       = $response->optionalString('scope');

        if (strcasecmp($tokenTypeValue, OAuthAuthorizationScheme::BEARER) !== 0) {
            throw new InvalidProviderResponseException(
                'The SAPI token response contains an invalid token type.',
                self::SCHEMA,
                'token_type'
            );
        }

        $tokenTypeValue = OAuthAuthorizationScheme::BEARER;

        $accessToken = self::parseField(
            'access_token',
            'The SAPI token response contains an invalid access token.',
            static fn () => OAuthAccessToken::fromString($accessTokenValue)
        );
        $tokenType = self::parseField(
            'token_type',
            'The SAPI token response contains an invalid token type.',
            static fn () => OAuthAuthorizationScheme::fromString($tokenTypeValue)
        );
        $scope = $scopeValue === null
            ? null
            : self::parseField(
                'scope',
                'The SAPI token response contains an invalid scope.',
                static fn () => OAuthScope::fromString($scopeValue)
            );
        $expiresIn = self::parseField(
            'expires_in',
            'The SAPI token response contains an invalid expiry.',
            static fn () => OAuthTokenLifetime::fromSeconds($expiresInValue)
        );

        // Reject lifetimes that would overflow when the token provider applies its local expiry margin.
        if ($expiresIn->seconds() > PHP_INT_MAX - time() + self::EXPIRY_MARGIN_SECONDS) {
            throw new InvalidProviderResponseException(
                'The SAPI token response contains an unsupported expiry.',
                self::SCHEMA,
                'expires_in'
            );
        }

        return new self($accessToken, $tokenType, $expiresIn, $scope);
    }

    /**
     * Builds one typed value from a response field and reports a rejected value as an invalid response.
     *
     * @template T
     * @param  string                           $field   Provider field name the value came from.
     * @param  string                           $message Diagnostic message for the invalid-response error.
     * @param  callable(): T                    $parse   Creates the typed value; may throw InvalidInputException.
     * @return T
     * @throws InvalidProviderResponseException when the value is rejected
     */
    private static function parseField(string $field, string $message, callable $parse)
    {
        try {
            return $parse();
        } catch (InvalidInputException $exception) {
            throw new InvalidProviderResponseException($message, self::SCHEMA, $field, $exception);
        }
    }
}
