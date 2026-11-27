<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain;

use DreaM1\EfakturaConnector\Domain\EFaktura\CodeList\ParticipantIdentifierSchemeCodeList;
use PHPUnit\Framework\TestCase;

/** Checks which participant schemes are accepted for existing operations and new document submissions. */
class ParticipantIdentifierSchemeCodeListTest extends TestCase
{
    public function testRecognizesRegistrableSchemesFromTheBundledOpenPeppolRelease(): void
    {
        $this->assertSame('9.7', ParticipantIdentifierSchemeCodeList::VERSION);
        $this->assertTrue(ParticipantIdentifierSchemeCodeList::isAllowedForNewDocument('0245'));
        $this->assertTrue(ParticipantIdentifierSchemeCodeList::isAllowedForNewDocument('9915'));
    }

    /**
     * Rejects schemes that are unsupported for new document identifiers.
     *
     * @param string $scheme Candidate participant identifier scheme.
     * @dataProvider unsupportedSchemes
     */
    public function testRejectsSchemesNotAllowedForNewDocuments(string $scheme): void
    {
        $this->assertFalse(ParticipantIdentifierSchemeCodeList::isAllowedForNewDocument($scheme));
    }

    /**
     * Provides participant identifier schemes unsupported for new documents.
     *
     * @return array<array-key, array<array-key, mixed>> Unsupported scheme cases.
     */
    public function unsupportedSchemes(): array
    {
        return [
            'deprecated 0193' => ['0193'],
            'deprecated 9909' => ['9909'],
            'deprecated 9912' => ['9912'],
            'deprecated 9916' => ['9916'],
            'deprecated 9917' => ['9917'],
            'removed'         => ['0037'],
            'not registrable' => ['0205'],
            'unknown'         => ['9999'],
        ];
    }

    /**
     * Rejects schemes unavailable for participant-scoped API operations.
     *
     * @param string $scheme Candidate participant identifier scheme.
     * @dataProvider schemesUnavailableForApiOperations
     */
    public function testRejectsDeprecatedRemovedUnknownAndUnregistrableSchemesForApiOperations(string $scheme): void
    {
        $this->assertFalse(ParticipantIdentifierSchemeCodeList::isAllowedForExistingOperation($scheme));
    }

    /**
     * Provides schemes that must not be used for participant-scoped API operations.
     *
     * @return array<array-key, array<array-key, mixed>> Invalid API scheme cases.
     */
    public function schemesUnavailableForApiOperations(): array
    {
        return [
            'deprecated 9909'        => ['9909'],
            'deprecated 9912'        => ['9912'],
            'deprecated 9916'        => ['9916'],
            'deprecated 9917'        => ['9917'],
            'removed'                => ['0037'],
            'removed after its date' => ['0193'],
            'unregistrable'          => ['0205'],
            'unknown'                => ['9999'],
        ];
    }
}
