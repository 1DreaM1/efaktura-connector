<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList;

use DreaM1\EfakturaConnector\Core\CodeLists\CodeListIdentifiers;
use DreaM1\EfakturaConnector\Core\CodeLists\CodeListOption;
use DreaM1\EfakturaConnector\Core\Contracts\CodeListInterface;

/** Exposes canonical UN/ECE units as typed selectable options. */
final class UnitCodeListProvider implements CodeListInterface
{
    /** {@inheritdoc} */
    public function identifier(): string
    {
        return CodeListIdentifiers::UNITS;
    }

    /** @return CodeListOption[] */
    public function options(): array
    {
        $options = [];

        foreach (UnitCodeList::all() as $code) {
            $name = UnitCodeList::name($code);

            if ($name !== null) {
                $options[] = new CodeListOption($code, $name);
            }
        }

        return $options;
    }
}
