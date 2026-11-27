<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList;

use DreaM1\EfakturaConnector\Core\CodeLists\CodeListIdentifiers;
use DreaM1\EfakturaConnector\Core\CodeLists\CodeListOption;
use DreaM1\EfakturaConnector\Core\Contracts\CodeListInterface;

/** Exposes bundled ISO 4217 currencies as typed selectable options. */
final class CurrencyCodeListProvider implements CodeListInterface
{
    /** {@inheritdoc} */
    public function identifier(): string
    {
        return CodeListIdentifiers::CURRENCIES;
    }

    /** @return CodeListOption[] */
    public function options(): array
    {
        $options = [];

        foreach (CurrencyCodeList::all() as $code) {
            $name = CurrencyCodeList::name($code);

            if ($name !== null) {
                $options[] = new CodeListOption($code, $name);
            }
        }

        return $options;
    }
}
