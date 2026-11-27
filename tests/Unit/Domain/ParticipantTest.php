<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain;

use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/** Checks parsing of Peppol participant identifiers and their scheme/value components. */
class ParticipantTest extends TestCase
{
    public function testParsesIdentifiersAndExposesTheirSchemeAndValue(): void
    {
        $participant = Participant::fromPeppolIdentifier(' 0088:synthetic-id ');

        $this->assertNotNull($participant);
        $this->assertSame('0088:synthetic-id', $participant->identifier());
        $this->assertSame('0088', $participant->scheme());
        $this->assertSame('synthetic-id', $participant->value());
        $this->assertNull(Participant::fromPeppolIdentifier('not-qualified'));
    }

    public function testParsingChecksIdentifierSyntaxWithoutResolvingTheScheme(): void
    {
        $participant = Participant::parse('9999:synthetic');

        $this->assertSame('9999', $participant->scheme());
        $this->assertSame('9999:synthetic', $participant->identifier());
    }

    public function testParsesAPeppolParticipantIdentifierIgnoringSurroundingWhitespace(): void
    {
        $participant = Participant::fromPeppolIdentifier(" \t0088:synthetic-id \n");

        $this->assertNotNull($participant);
        $this->assertSame('0088', $participant->scheme());
        $this->assertSame('synthetic-id', $participant->value());
        $this->assertSame('0088:synthetic-id', $participant->identifier());
    }

    /**
     * Rejects participant identifiers that do not use the scheme:value shape.
     *
     * @param string|null $identifier Candidate participant identifier.
     * @dataProvider malformedIdentifiers
     */
    public function testRejectsIdentifiersWithoutTheSchemeValueShape(?string $identifier): void
    {
        $this->assertNull(Participant::fromPeppolIdentifier($identifier));
    }

    /**
     * Provides participant identifiers that do not follow the scheme:value shape.
     *
     * @return array<array-key, array<array-key, mixed>> Malformed identifier cases.
     */
    public function malformedIdentifiers(): array
    {
        return [
            'missing'            => [null],
            'empty'              => [''],
            'no scheme'          => ['2012345676'],
            'short scheme'       => ['245:2012345676'],
            'letters in scheme'  => ['02A5:2012345676'],
            'empty value'        => ['0245:'],
            'space around colon' => ['0245 : 2012345676'],
            'space in value'     => ['0245:2012 345676'],
            'second colon'       => ['0245:2012:345676'],
            'slash in value'     => ['0245:2012/345676'],
            'non-ascii value'    => ["0245:identifik\xC3\xA1tor"],
        ];
    }
    public function testDoesNotValidateNationalIdentifierValues(): void
    {
        // The connector preserves identifier values; national rules belong to the application.
        $this->assertNotNull(Participant::fromPeppolIdentifier('0245:2012345677'));
        $this->assertNotNull(Participant::fromPeppolIdentifier('0245:synthetic'));
        $this->assertNotNull(Participant::fromPeppolIdentifier('0158:12345678'));
    }

    public function testParseThrowsForAMalformedIdentifier(): void
    {
        $this->assertSame(
            '0245:2012345676',
            Participant::parse(' 0245:2012345676 ')->identifier(),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Peppol participant ID must have a 4-digit scheme and a 1 to 130 character value.'
        );
        Participant::parse('0245 : 2012345676');
    }

    public function testEnforcesTheParticipantValueLengthLimit(): void
    {
        $value       = str_repeat('x', 130);
        $participant = Participant::fromPeppolIdentifier('0088:' . $value);

        $this->assertNotNull($participant);
        $this->assertSame('0088:' . $value, $participant->identifier());
        $this->assertNull(Participant::fromPeppolIdentifier('0088:' . $value . 'x'));
    }

    public function testParticipantValueEqualityIsCaseInsensitive(): void
    {
        $this->assertTrue(Participant::parse('0088:Abc-1')->equals(Participant::parse('0088:aBC-1')));
        $this->assertFalse(Participant::parse('0088:Abc-1')->equals(Participant::parse('0245:Abc-1')));
    }

    public function testParticipantDoesNotExposeLegacyConstructionAndSerializationHelpers(): void
    {
        $reflection = new ReflectionClass(Participant::class);

        foreach (['fromSchemeAndValue', 'fromArray', 'tryFromSchemeAndValue', 'notEquals', 'toArray'] as $method) {
            $this->assertFalse($reflection->hasMethod($method), $method . ' should not be part of the public API.');
        }
    }
}
