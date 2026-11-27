<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Webhooks\Webhook;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookList;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookRegistryPage;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/** Parses a Peppol Gateway webhook-list schema into the public Core page model. */
final class WebhookRegistryPageFactory
{
    private const SCHEMA = 'WebhookList';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to a Core webhook registry page.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return WebhookRegistryPage
     * @throws InvalidProviderResponseException when a required field is missing or invalid.
     */
    public static function fromArray(array $data): WebhookRegistryPage
    {
        return self::fromData(new ProviderResponseReader($data, self::SCHEMA));
    }

    /**
     * Maps validated provider fields to a Core webhook registry page.
     *
     * @param  ProviderResponseReader           $response Validated access to provider fields.
     * @return WebhookRegistryPage
     * @throws InvalidProviderResponseException when a required field is missing or invalid.
     */
    public static function fromData(ProviderResponseReader $response): WebhookRegistryPage
    {
        $webhooks = array_map(
            static function (ProviderResponseReader $webhookResponse): Webhook {
                return WebhookFactory::fromData($webhookResponse);
            },
            $response->requiredObjectList('webhooks', 'Webhook')
        );

        return new WebhookRegistryPage(new WebhookList($webhooks), $response->optionalPageToken('nextPageToken'));
    }
}
