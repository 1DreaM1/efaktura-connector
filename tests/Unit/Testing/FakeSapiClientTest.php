<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Testing;

use DreaM1\EfakturaConnector\Core\Contracts\TokenHealthProviderInterface;
use DreaM1\EfakturaConnector\Core\Contracts\WebhookRegistryInterface;
use DreaM1\EfakturaConnector\Core\Documents\DocumentSubmissionResult;
use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use DreaM1\EfakturaConnector\Core\Documents\OutboundDocumentPayload;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentRequest;
use DreaM1\EfakturaConnector\Core\Documents\Sha256Checksum;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Domain\EFaktura\SubmittedDocumentId;
use DreaM1\EfakturaConnector\Support\Exceptions\InvariantViolationException;
use DreaM1\EfakturaConnector\Support\Timestamp;
use DreaM1\EfakturaConnector\Testing\FakeSapiClient;
use PHPUnit\Framework\TestCase;

final class FakeSapiClientTest extends TestCase
{
    private const KEY_1 = '00000000-0000-4000-8000-000000000001';
    private const KEY_2 = '00000000-0000-4000-8000-000000000002';
    private const KEY_3 = '00000000-0000-4000-8000-000000000003';

    public function testSubmissionAcceptsAndRecordsDocumentsByDefault(): void
    {
        $client  = new FakeSapiClient('billing');
        $request = $this->request(self::KEY_1);

        $result = $client->documentSubmissionGateway()->send($request);

        $this->assertSame('billing', $client->name());
        $this->assertSame('fake-document-1', $result->providerDocumentId()->value());
        $this->assertSame(ProviderDocumentStatus::ACCEPTED, $result->status()->value());
        $this->assertSame([$request], $client->submissions()->sent());
        $this->assertSame($request, $client->submissions()->lastRequest());
    }

    public function testQueuedOutcomesAreConsumedInOrder(): void
    {
        $client = new FakeSapiClient();
        $client->submissions()
            ->willThrow(new ProviderConnectionException('Reset.'))
            ->willReturn($this->result('queued-1'));

        try {
            $client->documentSubmissionGateway()->send($this->request(self::KEY_1));
            $this->fail('The queued failure was not thrown.');
        } catch (ProviderConnectionException $failure) {
            $this->assertSame('Reset.', $failure->getMessage());
        }

        $result = $client->documentSubmissionGateway()->send($this->request(self::KEY_1));

        $this->assertSame('queued-1', $result->providerDocumentId()->value());
    }

    public function testRepeatedIdempotencyKeyReplaysTheFirstSuccessWithoutANewSubmission(): void
    {
        $client = new FakeSapiClient();

        $first  = $client->documentSubmissionGateway()->send($this->request(self::KEY_2));
        $second = $client->documentSubmissionGateway()->send($this->request(self::KEY_2));
        $other  = $client->documentSubmissionGateway()->send($this->request(self::KEY_3));

        $this->assertSame($first, $second);
        $this->assertCount(2, $client->submissions()->sent());
        $this->assertSame('fake-document-2', $other->providerDocumentId()->value());
    }

    public function testNoRequestIsRecordedBeforeAnySubmission(): void
    {
        $this->assertNull((new FakeSapiClient())->submissions()->lastRequest());
    }

    public function testSuppliedServicesAreReturnedAndOthersFailLoudly(): void
    {
        $webhooks = $this->createMock(WebhookRegistryInterface::class);
        $health   = $this->createMock(TokenHealthProviderInterface::class);
        $client   = (new FakeSapiClient())->useWebhookRegistry($webhooks)->useTokenHealthProvider($health);

        $this->assertSame($webhooks, $client->webhookRegistry());
        $this->assertSame($health, $client->tokenHealthProvider());
        $this->assertSame('document:send', $client->profile()->clientScope());

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('useReceivedDocumentProvider()');

        $client->receivedDocumentProvider();
    }

    private function request(string $key): SendDocumentRequest
    {
        $sender  = Participant::parse('0245:2012345676');
        $xml     = '<Invoice>synthetic</Invoice>';
        $payload = new OutboundDocumentPayload(
            SubmittedDocumentId::fromString('INV-SYNTHETIC-1'),
            PeppolDocumentTypeIdentifier::fromString('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0::2.1'),
            PeppolProcessIdentifier::fromString('urn:fdc:peppol.eu:2017:poacc:billing:03:1.0'),
            $sender,
            Participant::parse('0245:2012345677'),
            Timestamp::fromIso8601('2026-11-18T09:00:00Z'),
            $xml,
            Sha256Checksum::fromContent($xml)
        );

        return new SendDocumentRequest($sender, $payload, IdempotencyKey::fromString($key));
    }

    private function result(string $id): DocumentSubmissionResult
    {
        return new DocumentSubmissionResult(
            ProviderDocumentId::fromString($id),
            ProviderDocumentStatus::fromString(ProviderDocumentStatus::ACCEPTED),
            Timestamp::fromIso8601('2026-11-18T09:00:00Z')
        );
    }
}
