<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Webhooks;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidWebhookPayloadException;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookEventPayload;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookSigningSecret;
use DreaM1\EfakturaConnector\Domain\EFaktura\ReceivedDocumentMetadata;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\SentDocumentStatusChangedEvent;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookEventLimits;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Webhooks\PeppolWebhookEventFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Webhooks\PeppolWebhookHeaders;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Webhooks\PeppolWebhookHmacSha256SignatureVerifier;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Webhooks\PeppolWebhookSignatureVerifierInterface;
use DreaM1\EfakturaConnector\Tests\Support\FrozenClock;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests Peppol webhook verification ordering and typed event mapping. */
final class PeppolWebhookEventFactoryTest extends TestCase
{
    private const SECRET = 'synthetic-peppol-webhook-secret';
    /** Signed delivery timestamp used by HMAC-backed tests. */
    private const TIMESTAMP = 1730455200;

    public function testVerifiesBeforeMappingSentDocumentStatusChange(): void
    {
        $data = [
            'identifier'            => 'event-data-1',
            'documentId'            => 'document-1',
            'senderParticipantId'   => '0245:sender',
            'receiverParticipantId' => '0245:receiver',
            'status'                => 'DELIVERY_FAILED',
        ];
        $body     = $this->body('sent_document.status_changed', 'delivery-1', $data);
        $headers  = $this->headers('sent_document.status_changed', 'delivery-1');
        $verifier = $this->createMock(PeppolWebhookSignatureVerifierInterface::class);
        $verifier->expects($this->once())
            ->method('verify')
            ->with($body, $headers, $this->isInstanceOf(WebhookSigningSecret::class));

        $event = (new PeppolWebhookEventFactory($verifier))->fromSignedJson(
            $body,
            $headers,
            WebhookSigningSecret::fromString(self::SECRET)
        );

        $this->assertSame('delivery-1', $event->envelope()->eventId()->value());
        $this->assertInstanceOf(SentDocumentStatusChangedEvent::class, $event->eventPayload());
        $this->assertSame('document-1', $event->eventPayload()->documentId()->value());
    }

    public function testMapsInboxDocumentReceivedToTypedMetadata(): void
    {
        $data = [
            'documentId'            => 'received-1',
            'senderParticipantId'   => '0245:sender',
            'receiverParticipantId' => '0245:receiver',
            'documentTypeId'        => 'document-type-1',
            'processId'             => 'process-1',
            'creationDateTime'      => '2026-11-01T10:00:00Z',
        ];
        $body     = $this->body('inbox_document.received', 'delivery-2', $data);
        $headers  = $this->headers('inbox_document.received', 'delivery-2');
        $event    = $this->receive($body, $headers);
        $metadata = $event->eventPayload();

        $this->assertInstanceOf(ReceivedDocumentMetadata::class, $metadata);
        $this->assertSame('received-1', $metadata->documentId()->value());
        $this->assertSame('0245:sender', $metadata->sender()->identifier());
        $this->assertSame('0245:receiver', $metadata->receiver()->identifier());
        $this->assertSame('document-type-1', $metadata->documentTypeId()->value());
        $this->assertSame('process-1', $metadata->processId()->value());
        $this->assertSame('2026-11-01T10:00:00Z', $metadata->creationDateTime()->value());
    }

    public function testReturnsValidatedEnvelopeForWebhookTestDelivery(): void
    {
        $body  = $this->body('webhook.test', 'delivery-test', (object) []);
        $event = $this->receive($body, $this->headers('webhook.test', 'delivery-test'));

        $this->assertInstanceOf(WebhookEventPayload::class, $event->eventPayload());
        $this->assertSame('webhook.test', $event->envelope()->eventType()->value());
    }

    public function testRejectsUnsupportedEventTypes(): void
    {
        $body     = $this->body('future.event', 'delivery-future', (object) []);
        $headers  = $this->headers('future.event', 'delivery-future');
        $verifier = $this->createMock(PeppolWebhookSignatureVerifierInterface::class);
        $verifier->expects($this->once())->method('verify');

        $this->expectException(InvalidWebhookPayloadException::class);
        $this->expectExceptionMessage('Webhook event type is not supported.');

        (new PeppolWebhookEventFactory($verifier))->fromSignedJson(
            $body,
            $headers,
            WebhookSigningSecret::fromString(self::SECRET)
        );
    }

    public function testCallsVerifierBeforeAttemptingJsonParsing(): void
    {
        $headers  = $this->headers('sent_document.status_changed', 'delivery-invalid-json');
        $verifier = $this->createMock(PeppolWebhookSignatureVerifierInterface::class);
        $verifier->expects($this->once())->method('verify');

        $this->expectException(InvalidWebhookPayloadException::class);
        $this->expectExceptionMessage('Webhook payload must be a JSON object.');

        (new PeppolWebhookEventFactory($verifier))->fromSignedJson(
            '{invalid-json',
            $headers,
            WebhookSigningSecret::fromString(self::SECRET)
        );
    }

    public function testRejectsOversizedBodyBeforeCallingVerifier(): void
    {
        $headers  = $this->headers('sent_document.status_changed', 'delivery-oversized');
        $verifier = $this->createMock(PeppolWebhookSignatureVerifierInterface::class);
        $verifier->expects($this->never())->method('verify');

        $this->expectException(InvalidWebhookPayloadException::class);
        $this->expectExceptionMessage('Webhook payload exceeds the maximum allowed size.');

        (new PeppolWebhookEventFactory($verifier))->fromSignedJson(
            str_repeat('x', WebhookEventLimits::MAX_PAYLOAD_SIZE_BYTES + 1),
            $headers,
            WebhookSigningSecret::fromString(self::SECRET)
        );
    }

    public function testHeadersRejectBlankValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PeppolWebhookHeaders::fromValues('event', 'id', 'timestamp', " \t");
    }

    public function testHeadersRedactSignaturesFromDebugOutput(): void
    {
        $headers = $this->headers('sent_document.status_changed', 'delivery-redacted');

        ob_start();
        var_dump($headers);
        $output = (string) ob_get_clean();

        $this->assertStringNotContainsString('synthetic-signature', $output);
        $this->assertStringContainsString('[redacted]', $output);
    }

    public function testRotationAcceptsADeliverySignedWithThePreviousSecret(): void
    {
        $body    = $this->body('webhook.test', 'delivery-rotation', (object) []);
        $headers = $this->signedHeaders('delivery-rotation', $body, 'whsec_previous');

        $event = $this->hmacFactory()->fromSignedJsonWithAnySecret($body, $headers, [
            WebhookSigningSecret::fromString('whsec_current'),
            WebhookSigningSecret::fromString('whsec_previous'),
        ]);

        $this->assertSame('delivery-rotation', $event->envelope()->eventId()->value());
    }

    public function testRotationRejectsADeliveryThatNoSecretVerifies(): void
    {
        $body    = $this->body('webhook.test', 'delivery-rotation', (object) []);
        $headers = $this->signedHeaders('delivery-rotation', $body, 'whsec_unknown');

        try {
            $this->hmacFactory()->fromSignedJsonWithAnySecret($body, $headers, [
                WebhookSigningSecret::fromString('whsec_current'),
                WebhookSigningSecret::fromString('whsec_previous'),
            ]);
            $this->fail('A delivery signed with an unknown secret must be rejected.');
        } catch (InvalidWebhookPayloadException $exception) {
            $this->assertSame(
                'Peppol webhook signature is invalid for every supplied signing secret.',
                $exception->getMessage()
            );
            $this->assertInstanceOf(InvalidWebhookPayloadException::class, $exception->getPrevious());
        }
    }

    public function testSingleSecretFailureKeepsTheVerifierException(): void
    {
        $body    = $this->body('webhook.test', 'delivery-single', (object) []);
        $headers = $this->signedHeaders('delivery-single', $body, 'whsec_unknown');

        $this->expectException(InvalidWebhookPayloadException::class);
        $this->expectExceptionMessage('Peppol webhook signature is invalid.');

        $this->hmacFactory()->fromSignedJson($body, $headers, WebhookSigningSecret::fromString('whsec_current'));
    }

    /**
     * @dataProvider invalidSecretLists
     * @param array<int, mixed> $secrets
     */
    public function testRotationRequiresABoundedListOfSecrets(array $secrets, string $message): void
    {
        $body = $this->body('webhook.test', 'delivery-list', (object) []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->hmacFactory()->fromSignedJsonWithAnySecret(
            $body,
            $this->signedHeaders('delivery-list', $body, 'whsec_current'),
            $secrets
        );
    }

    /** @return array<string, array{array<int, mixed>, string}> */
    public function invalidSecretLists(): array
    {
        $tooMany = [];

        for ($index = 0; $index <= PeppolWebhookEventFactory::MAX_ROTATION_SECRETS; $index++) {
            $tooMany[] = WebhookSigningSecret::fromString('whsec_' . $index);
        }

        return [
            'empty'     => [[], 'Between one and 5 webhook signing secrets are required.'],
            'too many'  => [$tooMany, 'Between one and 5 webhook signing secrets are required.'],
            'raw value' => [['whsec_current'], 'Webhook signing secrets must be WebhookSigningSecret values.'],
        ];
    }

    /** @dataProvider timestampOffsets */
    public function testTimestampToleranceIsAppliedAfterSignatureVerification(int $offsetSeconds, bool $accepted): void
    {
        $body    = $this->body('webhook.test', 'delivery-fresh', (object) []);
        $headers = $this->signedHeaders('delivery-fresh', $body, 'whsec_current');
        $factory = $this->hmacFactory()->withTimestampTolerance(
            300,
            new FrozenClock(self::TIMESTAMP + $offsetSeconds)
        );

        if (! $accepted) {
            $this->expectException(InvalidWebhookPayloadException::class);
            $this->expectExceptionMessage('Peppol webhook timestamp is outside the allowed tolerance.');
        }

        $event = $factory->fromSignedJson($body, $headers, WebhookSigningSecret::fromString('whsec_current'));

        $this->assertSame('delivery-fresh', $event->envelope()->eventId()->value());
    }

    /** @return array<string, array{int, bool}> */
    public function timestampOffsets(): array
    {
        return [
            'within window'      => [300, true],
            'stale'              => [301, false],
            'too far in future'  => [-301, false],
            'exact current time' => [0, true],
        ];
    }

    public function testTimestampToleranceDoesNotMaskAnInvalidSignature(): void
    {
        $body    = $this->body('webhook.test', 'delivery-stale', (object) []);
        $headers = $this->signedHeaders('delivery-stale', $body, 'whsec_unknown');

        $this->expectException(InvalidWebhookPayloadException::class);
        $this->expectExceptionMessage('Peppol webhook signature is invalid.');

        $this->hmacFactory()
            ->withTimestampTolerance(60, new FrozenClock(self::TIMESTAMP + 3600))
            ->fromSignedJson($body, $headers, WebhookSigningSecret::fromString('whsec_current'));
    }

    public function testTimestampToleranceIsOptInAndReturnsACopy(): void
    {
        $body    = $this->body('webhook.test', 'delivery-old', (object) []);
        $headers = $this->signedHeaders('delivery-old', $body, 'whsec_current');
        $factory = $this->hmacFactory();

        $factory->withTimestampTolerance(60, new FrozenClock(self::TIMESTAMP + 3600));

        $event = $factory->fromSignedJson($body, $headers, WebhookSigningSecret::fromString('whsec_current'));

        $this->assertSame('delivery-old', $event->envelope()->eventId()->value());
    }

    public function testTimestampToleranceMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Webhook timestamp tolerance must be positive.');

        $this->hmacFactory()->withTimestampTolerance(0);
    }

    private function hmacFactory(): PeppolWebhookEventFactory
    {
        return new PeppolWebhookEventFactory(new PeppolWebhookHmacSha256SignatureVerifier());
    }

    private function signedHeaders(string $id, string $body, string $secret): PeppolWebhookHeaders
    {
        $timestamp = (string) self::TIMESTAMP;
        $signature = hash_hmac('sha256', $timestamp . '.' . $id . '.' . $body, $secret);

        return PeppolWebhookHeaders::fromValues(
            'webhook.test',
            $id,
            $timestamp,
            't=' . $timestamp . ',v1=' . $signature
        );
    }

    private function receive(string $body, PeppolWebhookHeaders $headers)
    {
        $verifier = $this->createMock(PeppolWebhookSignatureVerifierInterface::class);

        return (new PeppolWebhookEventFactory($verifier))->fromSignedJson(
            $body,
            $headers,
            WebhookSigningSecret::fromString(self::SECRET)
        );
    }

    /** @param array<string, mixed>|object $data */
    private function body(string $event, string $id, $data): string
    {
        return (string) json_encode([
            'id'         => $id,
            'event'      => $event,
            'occurredAt' => '2026-11-01T10:00:00Z',
            'data'       => $data,
        ]);
    }

    private function headers(string $event, string $id): PeppolWebhookHeaders
    {
        return PeppolWebhookHeaders::fromValues(
            $event,
            $id,
            '1730455200',
            't=1730455200,v1=synthetic-signature'
        );
    }
}
