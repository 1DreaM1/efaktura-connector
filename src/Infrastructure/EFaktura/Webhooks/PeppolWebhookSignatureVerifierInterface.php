<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Webhooks;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidWebhookPayloadException;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookSigningSecret;

/**
 * Verifies the configured Peppol Gateway webhook signing scheme.
 *
 * @api
 */
interface PeppolWebhookSignatureVerifierInterface
{
    /**
     * Verifies the exact raw body and delivery headers before any JSON parsing occurs.
     *
     * An implementation must reject invalid signatures and apply any timestamp or replay checks supported by its
     * signing scheme. The bundled HMAC-SHA256 verifier authenticates the timestamp, delivery ID and exact raw body.
     *
     * @throws InvalidWebhookPayloadException when the signature or timestamp is invalid
     */
    public function verify(
        string $rawBody,
        PeppolWebhookHeaders $headers,
        WebhookSigningSecret $secret
    ): void;
}
