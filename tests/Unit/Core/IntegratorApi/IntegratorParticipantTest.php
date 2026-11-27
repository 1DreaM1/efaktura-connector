<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorParticipant;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorParticipantCompany;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorParticipantContract;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorParticipantTimestamps;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Support\Timestamp;
use PHPUnit\Framework\TestCase;

/** Participant metadata is grouped by topic while every value stays reachable directly. */
final class IntegratorParticipantTest extends TestCase
{
    public function testGroupsAndFlatAccessorsExposeTheSameValues(): void
    {
        $participant = Participant::parse('0245:2022706466');
        $created     = Timestamp::fromIso8601('2026-01-01T10:00:00Z');
        $received    = Timestamp::fromIso8601('2026-01-02T10:00:00Z');

        $value = new IntegratorParticipant(
            $participant,
            new IntegratorParticipantCompany('2022706466', 'Example s.r.o.', 'info@example.test', '+421000000000'),
            new IntegratorParticipantTimestamps($created, $received),
            new IntegratorParticipantContract(true, false)
        );

        $this->assertSame($participant, $value->participantId());

        $this->assertSame('2022706466', $value->company()->dic());
        $this->assertSame('Example s.r.o.', $value->company()->legalName());
        $this->assertSame('info@example.test', $value->company()->email());
        $this->assertSame('+421000000000', $value->company()->phone());
        $this->assertSame('2022706466', $value->dic());
        $this->assertSame('Example s.r.o.', $value->legalName());
        $this->assertSame('info@example.test', $value->companyEmail());
        $this->assertSame('+421000000000', $value->companyPhone());

        $this->assertSame($created, $value->timestamps()->sourceCreatedAt());
        $this->assertSame($received, $value->timestamps()->receivedAt());
        $this->assertSame($created, $value->sourceCreatedAt());
        $this->assertSame($received, $value->receivedAt());

        $this->assertTrue($value->contract()->internalTransferRequired());
        $this->assertFalse($value->contract()->isOwner());
        $this->assertNull($value->contract()->activeContractMode());
        $this->assertTrue($value->internalTransferRequired());
        $this->assertFalse($value->isOwner());
        $this->assertNull($value->activeContractMode());
    }

    public function testEmptyGroupsLeaveEveryValueNull(): void
    {
        $value = new IntegratorParticipant(
            null,
            new IntegratorParticipantCompany(),
            new IntegratorParticipantTimestamps(),
            new IntegratorParticipantContract()
        );

        $this->assertNull($value->participantId());
        $this->assertNull($value->dic());
        $this->assertNull($value->legalName());
        $this->assertNull($value->companyEmail());
        $this->assertNull($value->companyPhone());
        $this->assertNull($value->sourceCreatedAt());
        $this->assertNull($value->receivedAt());
        $this->assertNull($value->internalTransferRequired());
        $this->assertNull($value->isOwner());
        $this->assertNull($value->activeContractMode());
    }
}
