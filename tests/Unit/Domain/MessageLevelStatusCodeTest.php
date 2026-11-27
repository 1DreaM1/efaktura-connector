<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain;

use DreaM1\EfakturaConnector\Domain\EFaktura\MessageLevelStatusCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks parsing and preservation of provider message-level status codes. */
class MessageLevelStatusCodeTest extends TestCase
{
    public function testCreatesCanonicalValueAndComparesCodes(): void
    {
        $code = MessageLevelStatusCode::fromString('ap');

        $this->assertSame(MessageLevelStatusCode::DELIVERED, $code->value());
        $this->assertTrue($code->equals(MessageLevelStatusCode::fromString('AP')));
        $this->assertFalse($code->equals(MessageLevelStatusCode::fromString('RE')));
    }

    public function testRejectsBlankCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message-level status code must not be empty.');

        MessageLevelStatusCode::fromString(" \t\n");
    }

    public function testPreservesUnknownCodesForHostClassification(): void
    {
        $code = MessageLevelStatusCode::fromString('error');

        $this->assertSame('ERROR', $code->value());
        $this->assertFalse(MessageLevelStatusCode::isSuccess($code->value()));
        $this->assertFalse(MessageLevelStatusCode::isFailure($code->value()));
        $this->assertFalse(MessageLevelStatusCode::isForwarded($code->value()));
    }

    public function testIsSuccessReturnsTrueForDelivered(): void
    {
        $this->assertTrue(MessageLevelStatusCode::isSuccess(MessageLevelStatusCode::DELIVERED));
        $this->assertTrue(MessageLevelStatusCode::isSuccess('ap'));
    }

    public function testIsSuccessReturnsFalseForOtherCodes(): void
    {
        $this->assertFalse(MessageLevelStatusCode::isSuccess(MessageLevelStatusCode::FORWARDED));
        $this->assertFalse(MessageLevelStatusCode::isSuccess(MessageLevelStatusCode::DELIVERY_FAILED));
    }

    public function testIsFailureReturnsTrueForDeliveryFailed(): void
    {
        $this->assertTrue(MessageLevelStatusCode::isFailure(MessageLevelStatusCode::DELIVERY_FAILED));
        $this->assertTrue(MessageLevelStatusCode::isFailure('re'));
    }

    public function testIsFailureReturnsFalseForSuccessAndForwarded(): void
    {
        $this->assertFalse(MessageLevelStatusCode::isFailure(MessageLevelStatusCode::DELIVERED));
        $this->assertFalse(MessageLevelStatusCode::isFailure(MessageLevelStatusCode::FORWARDED));
    }

    public function testIsForwardedReturnsTrueForForwarded(): void
    {
        $this->assertTrue(MessageLevelStatusCode::isForwarded(MessageLevelStatusCode::FORWARDED));
        $this->assertTrue(MessageLevelStatusCode::isForwarded('ab'));
    }

    public function testIsForwardedReturnsFalseForOtherCodes(): void
    {
        $this->assertFalse(MessageLevelStatusCode::isForwarded(MessageLevelStatusCode::DELIVERED));
        $this->assertFalse(MessageLevelStatusCode::isForwarded(MessageLevelStatusCode::DELIVERY_FAILED));
    }

    public function testCodesAreMutuallyExclusive(): void
    {
        $codes = [
            MessageLevelStatusCode::DELIVERED,
            MessageLevelStatusCode::DELIVERY_FAILED,
            MessageLevelStatusCode::FORWARDED,
        ];

        foreach ($codes as $code) {
            $results = [
                MessageLevelStatusCode::isSuccess($code),
                MessageLevelStatusCode::isFailure($code),
                MessageLevelStatusCode::isForwarded($code),
            ];
            $trueCount = count(array_filter($results));
            $this->assertLessThanOrEqual(1, $trueCount, "Code '{$code}' matched more than one semantic category.");
        }
    }
}
