<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookRegistrationResponse;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookSigningSecret;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/** Parses a webhook-creation response into the public Core registration model. */
final class WebhookRegistrationResponseFactory
{
    private const SCHEMA = 'WebhookCreateResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to the Core registration result.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return WebhookRegistrationResponse
     * @throws InvalidProviderResponseException when a required field is missing or invalid.
     */
    public static function fromArray(array $data): WebhookRegistrationResponse
    {
        return self::fromData(new ProviderResponseReader($data, self::SCHEMA));
    }

    /**
     * Maps validated provider fields to the Core registration result.
     *
     * @param  ProviderResponseReader           $response Validated access to provider fields.
     * @return WebhookRegistrationResponse
     * @throws InvalidProviderResponseException when a required field is missing or invalid.
     */
    public static function fromData(ProviderResponseReader $response): WebhookRegistrationResponse
    {
        $webhook = WebhookFactory::fromData($response, self::SCHEMA);
        $secret  = WebhookSigningSecret::fromString($response->requiredString('signingSecret'));

        return new WebhookRegistrationResponse(
            $webhook->id(),
            $secret,
            $webhook->events(),
            $webhook->status(),
            $webhook->createdAt(),
            $webhook->updatedAt(),
            $webhook->url(),
            $webhook->description()
        );
    }
}
