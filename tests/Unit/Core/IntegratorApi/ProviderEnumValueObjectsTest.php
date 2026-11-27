<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientStatus;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ParticipantContractMode;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use PHPUnit\Framework\TestCase;

/** Checks tolerant provider parsing and strict caller parsing for Integrator status values. */
class ProviderEnumValueObjectsTest extends TestCase
{
    public function testIntegratorClientStatusPreservesUnknownProviderValues(): void
    {
        $unknown = IntegratorClientStatus::fromProvider('SUSPENDED');

        $this->assertSame('SUSPENDED', $unknown->value());
        $this->assertFalse($unknown->isKnown());
        $this->assertTrue(IntegratorClientStatus::fromProvider('APPROVED')->isKnown());
    }

    public function testParticipantContractModePreservesUnknownProviderValues(): void
    {
        $unknown = ParticipantContractMode::fromProvider('SEND_ARCHIVE');

        $this->assertSame('SEND_ARCHIVE', $unknown->value());
        $this->assertFalse($unknown->isKnown());
        $this->assertTrue(ParticipantContractMode::fromProvider('SEND_ONLY')->isKnown());
    }

    public function testCallerParserRemainsStrict(): void
    {
        $this->expectException(InvalidInputException::class);

        IntegratorClientStatus::fromString('SUSPENDED');
    }
}
