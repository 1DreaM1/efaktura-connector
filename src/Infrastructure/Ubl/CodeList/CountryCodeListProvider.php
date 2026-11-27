<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList;

use DreaM1\EfakturaConnector\Core\CodeLists\CodeListIdentifiers;
use DreaM1\EfakturaConnector\Core\CodeLists\CodeListOption;
use DreaM1\EfakturaConnector\Core\Contracts\CodeListInterface;

/** Exposes country codes as typed selectable options. */
final class CountryCodeListProvider implements CodeListInterface
{
    /** {@inheritdoc} */
    public function identifier(): string
    {
        return CodeListIdentifiers::COUNTRIES;
    }

    /** @return CodeListOption[] */
    public function options(): array
    {
        $options = [];

        foreach (CountryCodeList::all() as $code) {
            $options[] = new CodeListOption($code, CountryCodeList::name($code));
        }

        return $options;
    }
}
