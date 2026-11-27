<?php

namespace DreaM1\EfakturaConnector\Tests\Support;

use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfiguration;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;

/**
 * Synthetic connector configuration and participant for tests.
 */
final class TestConfiguration
{
    public const BASE_URL       = 'https://sapi.synthetic.test';
    public const PARTICIPANT_ID = '0245:2012345676';
    public const CLIENT_ID      = 'synthetic-client';
    public const CLIENT_SECRET  = 'synthetic-secret';

    /**
     * Creates a connector configuration using synthetic test credentials by default.
     *
     * @param  string                  $clientId     OAuth client identifier.
     * @param  string                  $clientSecret OAuth client secret.
     * @param  string                  $baseUrl      SAPI base URL.
     * @param  string                  $scope        OAuth client scope.
     * @return SapiClientConfiguration Connector configuration for tests.
     */
    public static function connector(
        string $clientId = self::CLIENT_ID,
        string $clientSecret = self::CLIENT_SECRET,
        string $baseUrl = self::BASE_URL,
        string $scope = 'document:send document:receive'
    ): SapiClientConfiguration {
        return new SapiClientConfiguration($baseUrl, $clientId, $clientSecret, $scope);
    }

    /**
     * Creates a participant value object from a synthetic participant identifier.
     *
     * @param  string      $participantId Participant identifier.
     * @return Participant Parsed participant.
     */
    public static function participant(string $participantId = self::PARTICIPANT_ID): Participant
    {
        return Participant::parse($participantId);
    }

    /**
     * Computes the cache key used by SapiTokenProvider for the supplied configuration.
     *
     * @param  string $scope        OAuth client scope.
     * @param  string $clientId     OAuth client identifier.
     * @param  string $clientSecret OAuth client secret.
     * @param  string $baseUrl      SAPI base URL.
     * @return string Hashed cache key for the token configuration.
     */
    public static function tokenCacheKey(
        string $scope = 'document:send document:receive',
        string $clientId = self::CLIENT_ID,
        string $clientSecret = self::CLIENT_SECRET,
        string $baseUrl = self::BASE_URL
    ): string {
        return 'efaktura.oauth_token.' . hash('sha256', implode("\0", [$baseUrl, $clientId, $clientSecret, $scope]));
    }
}
