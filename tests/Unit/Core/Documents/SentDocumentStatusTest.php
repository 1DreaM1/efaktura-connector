<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Documents;

use DreaM1\EfakturaConnector\Core\Documents\SentDocumentIdentity;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentOutcome;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentParties;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentProviderReference;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentStatus;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentTiming;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderMessageId;
use DreaM1\EfakturaConnector\Support\Timestamp;
use PHPUnit\Framework\TestCase;

/** Sent-document status groups related provider fields and still exposes each of them directly. */
final class SentDocumentStatusTest extends TestCase
{
    public function testEmptyGroupsLeaveEveryFieldNull(): void
    {
        $status = SentDocumentStatus::create(
            null,
            new SentDocumentParties(),
            new SentDocumentTiming(),
            new SentDocumentIdentity(),
            new SentDocumentOutcome()
        );

        $this->assertNull($status->status());
        $this->assertNull($status->sender());
        $this->assertNull($status->receiver());
        $this->assertNull($status->createdAt());
        $this->assertNull($status->updatedAt());
        $this->assertNull($status->identifier());
        $this->assertNull($status->documentId());
        $this->assertNull($status->documentTypeId());
        $this->assertNull($status->processId());
        $this->assertNull($status->payloadFormat());
        $this->assertNull($status->provider());
        $this->assertNull($status->error());
    }

    public function testFieldsAreExposedDirectlyAndThroughTheirGroup(): void
    {
        $sender   = Participant::parse('0245:2022706466');
        $receiver = Participant::parse('0245:2022706467');
        $created  = Timestamp::fromIso8601('2026-01-01T10:00:00Z');
        $updated  = Timestamp::fromIso8601('2026-01-02T10:00:00Z');
        $id       = ProviderDocumentId::fromString('provider-1');
        $provider = new SentDocumentProviderReference('ap', ProviderDocumentId::fromString('doc-1'), ProviderMessageId::fromString('msg-1'));

        $status = SentDocumentStatus::create(
            ProviderDocumentStatus::fromString(ProviderDocumentStatus::SENT_TO_ACCESS_POINT),
            new SentDocumentParties($sender, $receiver),
            new SentDocumentTiming($created, $updated),
            new SentDocumentIdentity($id, null, null, null, 'XML'),
            new SentDocumentOutcome($provider)
        );

        $this->assertSame($sender, $status->sender());
        $this->assertSame($receiver, $status->receiver());
        $this->assertSame($created, $status->createdAt());
        $this->assertSame($updated, $status->updatedAt());
        $this->assertSame($id, $status->identifier());
        $this->assertSame('XML', $status->payloadFormat());
        $this->assertSame($provider, $status->provider());
        $this->assertNull($status->error());

        $this->assertSame($sender, $status->parties()->sender());
        $this->assertSame($created, $status->timing()->createdAt());
        $this->assertSame($id, $status->identity()->identifier());
        $this->assertSame($provider, $status->outcome()->provider());
    }

    public function testSenderAndReceiverStayDistinct(): void
    {
        $sender   = Participant::parse('0245:2022706466');
        $receiver = Participant::parse('0245:2022706467');

        $parties = new SentDocumentParties($sender, $receiver);

        $this->assertSame($sender, $parties->sender());
        $this->assertSame($receiver, $parties->receiver());
        $this->assertNotSame($parties->sender(), $parties->receiver());
    }
}
