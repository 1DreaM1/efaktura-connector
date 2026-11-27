<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Webhooks;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidWebhookPayloadException;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookSigningSecret;
use DreaM1\EfakturaConnector\Support\HashAlgorithm;

/**
 * Verifies Peppol webhook signatures over timestamp, delivery ID and the exact raw body.
 *
 * @api
 */
final class PeppolWebhookHmacSha256SignatureVerifier implements PeppolWebhookSignatureVerifierInterface
{
    private const SIGNATURE_HEADER_PATTERN = '/\A[a-f0-9]{64}\z/iD';

    /**
     * Verifies HMAC-SHA256 over "timestamp.delivery-id.raw-body" using the complete signing secret.
     *
     * @throws InvalidWebhookPayloadException when the header or signature is invalid
     */
    public function verify(
        string $rawBody,
        PeppolWebhookHeaders $headers,
        WebhookSigningSecret $secret
    ): void {
        $signature = $this->signatureFromHeader($headers->signature(), $headers->timestamp());

        if ($signature === null) {
            throw new InvalidWebhookPayloadException('Peppol webhook signature is invalid.');
        }

        $signedMessage = $headers->timestamp() . '.' . $headers->id() . '.' . $rawBody;
        $expected      = hash_hmac(HashAlgorithm::SHA_256, $signedMessage, $secret->reveal());

        if (! hash_equals($expected, strtolower($signature))) {
            throw new InvalidWebhookPayloadException('Peppol webhook signature is invalid.');
        }
    }

    /** Extracts the single supported timestamp and v1 signature fields from the provider header. */
    private function signatureFromHeader(string $header, string $timestamp): ?string
    {
        if (! ctype_digit($timestamp)) {
            return null;
        }

        $parts = [];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);

            if (count($pair) !== 2 || $pair[0] === '' || $pair[1] === '' || array_key_exists($pair[0], $parts)) {
                return null;
            }

            $parts[$pair[0]] = $pair[1];
        }

        if (count($parts) !== 2 || ($parts['t'] ?? null) !== $timestamp) {
            return null;
        }

        $signature = $parts['v1'] ?? null;

        if (! is_string($signature) || ! preg_match(self::SIGNATURE_HEADER_PATTERN, $signature)) {
            return null;
        }

        return $signature;
    }
}
