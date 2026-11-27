<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\MessageStatuses;

use DreaM1\EfakturaConnector\Core\Documents\SentDocumentParties;
use DreaM1\EfakturaConnector\Core\MessageStatuses\ProviderMessageLevelStatus;
use DreaM1\EfakturaConnector\Core\MessageStatuses\ProviderMessageLevelStatusReason;
use DreaM1\EfakturaConnector\Core\MessageStatuses\ProviderMessageLevelStatusResult;
use DreaM1\EfakturaConnector\Domain\EFaktura\MessageLevelStatusCode;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderMessageId;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use PHPUnit\Framework\TestCase;

/** A message-level status groups its result and participants and still exposes each value directly. */
final class ProviderMessageLevelStatusTest extends TestCase
{
    public function testGroupsAndFlatAccessorsExposeTheSameValues(): void
    {
        $reason   = ProviderMessageLevelStatusReason::create('1', 'BR-1', 'Invalid amount');
        $sender   = Participant::parse('0245:2022706466');
        $receiver = Participant::parse('0245:2022706467');
        $code     = MessageLevelStatusCode::fromString('RE');

        $status = ProviderMessageLevelStatus::create(
            null,
            ProviderMessageId::fromString('msg-1'),
            new ProviderMessageLevelStatusResult($code, 'Rejected', [$reason]),
            new SentDocumentParties($sender, $receiver)
        );

        $this->assertSame($code, $status->result()->code());
        $this->assertSame('Rejected', $status->result()->description());
        $this->assertSame([$reason], $status->result()->reasons());
        $this->assertSame($sender, $status->parties()->sender());
        $this->assertSame($receiver, $status->parties()->receiver());

        $this->assertSame($code, $status->responseCode());
        $this->assertSame('Rejected', $status->responseDescription());
        $this->assertSame([$reason], $status->reasons());
        $this->assertSame($sender, $status->sender());
        $this->assertSame($receiver, $status->receiver());
        $this->assertSame('msg-1', $status->messageId()->value());
        $this->assertNull($status->documentTypeId());
        $this->assertNull($status->receivedAt());
    }

    public function testEmptyResultHasNoCodeDescriptionOrReasons(): void
    {
        $result = new ProviderMessageLevelStatusResult();

        $this->assertNull($result->code());
        $this->assertNull($result->description());
        $this->assertSame([], $result->reasons());
    }

    public function testReasonsAreReindexed(): void
    {
        $first  = ProviderMessageLevelStatusReason::create('1', null, null);
        $second = ProviderMessageLevelStatusReason::create('2', null, null);

        $result = new ProviderMessageLevelStatusResult(null, null, [4 => $first, 9 => $second]);

        $this->assertSame([$first, $second], $result->reasons());
    }

    public function testReasonsMustBeReasonValues(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Message-level status reasons must contain reason values.');

        new ProviderMessageLevelStatusResult(null, null, ['not a reason']);
    }
}
