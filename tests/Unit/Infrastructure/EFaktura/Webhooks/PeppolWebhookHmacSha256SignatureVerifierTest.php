<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Webhooks;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidWebhookPayloadException;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookSigningSecret;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Webhooks\PeppolWebhookHeaders;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Webhooks\PeppolWebhookHmacSha256SignatureVerifier;
use PHPUnit\Framework\TestCase;

/** Tests the provider-confirmed Peppol webhook signature input. */
final class PeppolWebhookHmacSha256SignatureVerifierTest extends TestCase
{
    private const SECRET    = 'whsec_synthetic-secret';
    private const TIMESTAMP = '1783061804';
    private const EVENT_ID  = 'delivery-1';
    private const BODY      = '{"id":"delivery-1","event":"inbox_document.received","data":{}}';
    private const SIGNATURE = '6c155ddb9a518e9c7542e1cf11cc3881ab2169fb34b0ef7c7ec21e42de018667';

    public function testVerifiesHmacSha256OfTimestampIdAndExactRawBody(): void
    {
        $headers = $this->headers(
            't=' . self::TIMESTAMP . ',v1=' . self::SIGNATURE,
            self::TIMESTAMP,
            self::EVENT_ID
        );

        (new PeppolWebhookHmacSha256SignatureVerifier())->verify(
            self::BODY,
            $headers,
            WebhookSigningSecret::fromString(self::SECRET)
        );

        $this->addToAssertionCount(1);
    }

    public function testRejectsSignatureWhenRawBodyChanges(): void
    {
        $headers = $this->headers(
            't=' . self::TIMESTAMP . ',v1=' . self::SIGNATURE,
            self::TIMESTAMP,
            self::EVENT_ID
        );

        $this->expectException(InvalidWebhookPayloadException::class);
        $this->expectExceptionMessage('Peppol webhook signature is invalid.');

        (new PeppolWebhookHmacSha256SignatureVerifier())->verify(
            self::BODY . ' ',
            $headers,
            WebhookSigningSecret::fromString(self::SECRET)
        );
    }

    public function testRejectsSignatureWhenDeliveryIdChanges(): void
    {
        $headers = $this->headers(
            't=' . self::TIMESTAMP . ',v1=' . self::SIGNATURE,
            self::TIMESTAMP,
            'delivery-2'
        );

        $this->expectException(InvalidWebhookPayloadException::class);

        (new PeppolWebhookHmacSha256SignatureVerifier())->verify(
            self::BODY,
            $headers,
            WebhookSigningSecret::fromString(self::SECRET)
        );
    }

    public function testRejectsSignatureWhenSignedTimestampChanges(): void
    {
        $headers = $this->headers(
            't=1783061805,v1=' . self::SIGNATURE,
            '1783061805',
            self::EVENT_ID
        );

        $this->expectException(InvalidWebhookPayloadException::class);

        (new PeppolWebhookHmacSha256SignatureVerifier())->verify(
            self::BODY,
            $headers,
            WebhookSigningSecret::fromString(self::SECRET)
        );
    }

    public function testRejectsSignatureTimestampThatDiffersFromTimestampHeader(): void
    {
        $headers = $this->headers(
            't=1783061805,v1=' . self::SIGNATURE,
            self::TIMESTAMP,
            self::EVENT_ID
        );

        $this->expectException(InvalidWebhookPayloadException::class);

        (new PeppolWebhookHmacSha256SignatureVerifier())->verify(
            self::BODY,
            $headers,
            WebhookSigningSecret::fromString(self::SECRET)
        );
    }

    public function testRejectsSha512LengthSignature(): void
    {
        $headers = $this->headers(
            't=' . self::TIMESTAMP . ',v1=' . str_repeat('a', 128),
            self::TIMESTAMP,
            self::EVENT_ID
        );

        $this->expectException(InvalidWebhookPayloadException::class);

        (new PeppolWebhookHmacSha256SignatureVerifier())->verify(
            self::BODY,
            $headers,
            WebhookSigningSecret::fromString(self::SECRET)
        );
    }

    public function testRejectsMalformedSignatureHeader(): void
    {
        $headers = $this->headers(
            'v1=invalid,t=' . self::TIMESTAMP . ',v1=duplicate',
            self::TIMESTAMP,
            self::EVENT_ID
        );

        $this->expectException(InvalidWebhookPayloadException::class);

        (new PeppolWebhookHmacSha256SignatureVerifier())->verify(
            self::BODY,
            $headers,
            WebhookSigningSecret::fromString(self::SECRET)
        );
    }

    private function headers(string $signature, string $timestamp, string $id): PeppolWebhookHeaders
    {
        return PeppolWebhookHeaders::fromValues(
            'inbox_document.received',
            $id,
            $timestamp,
            $signature
        );
    }
}
