<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Webhooks\Webhook;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookUrl;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookEventType;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookStatus;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Timestamp;

/** Parses a Peppol Gateway webhook schema into the public Core webhook model. */
final class WebhookFactory
{
    private const SCHEMA = 'Webhook';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to a Core webhook.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return Webhook
     * @throws InvalidProviderResponseException when a required field is missing or invalid.
     */
    public static function fromArray(array $data): Webhook
    {
        return self::fromData(new ProviderResponseReader($data, self::SCHEMA));
    }

    /**
     * Maps validated provider fields to a Core webhook.
     *
     * @param  ProviderResponseReader           $response Validated access to provider fields.
     * @param  string                           $schema   Schema name used for URL validation errors.
     * @return Webhook
     * @throws InvalidProviderResponseException when a required field is missing or invalid.
     */
    public static function fromData(
        ProviderResponseReader $response,
        string $schema = self::SCHEMA
    ): Webhook {
        $createdAt = $response->requiredTimestamp('createdAt');
        $updatedAt = $response->requiredTimestamp('updatedAt');
        $urlValue  = $response->requiredString('url');

        try {
            $url = WebhookUrl::fromString($urlValue);
        } catch (InvalidInputException $exception) {
            throw new InvalidProviderResponseException(
                sprintf('Provider response %s has an invalid field "url".', $schema),
                $schema,
                'url',
                $exception
            );
        }

        $events = array_map(
            static fn (string $event): WebhookEventType => WebhookEventType::fromString($event),
            $response->requiredStringList('events')
        );

        return new Webhook(
            WebhookIdentifier::fromString($response->requiredString('id')),
            $url,
            $events,
            WebhookStatus::fromProvider($response->requiredString('status')),
            $response->nullableString('description'),
            $createdAt,
            $updatedAt
        );
    }
}
