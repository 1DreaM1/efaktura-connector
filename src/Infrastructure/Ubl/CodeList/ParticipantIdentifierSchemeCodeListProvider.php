<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList;

use DreaM1\EfakturaConnector\Core\CodeLists\CodeListIdentifiers;
use DreaM1\EfakturaConnector\Core\CodeLists\CodeListOption;
use DreaM1\EfakturaConnector\Core\Contracts\CodeListInterface;
use DreaM1\EfakturaConnector\Domain\EFaktura\CodeList\ParticipantIdentifierSchemeCodeList;

/** Exposes active, registrable Peppol participant schemes as options. */
final class ParticipantIdentifierSchemeCodeListProvider implements CodeListInterface
{
    /** {@inheritdoc} */
    public function identifier(): string
    {
        return CodeListIdentifiers::PARTICIPANT_IDENTIFIER_SCHEMES;
    }

    /** @return CodeListOption[] */
    public function options(): array
    {
        $options = [];

        foreach (ParticipantIdentifierSchemeCodeList::all() as $code) {
            $options[] = new CodeListOption($code, $code);
        }

        return $options;
    }
}
