<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList;

use DreaM1\EfakturaConnector\Core\CodeLists\CodeListIdentifiers;
use DreaM1\EfakturaConnector\Core\CodeLists\CodeListOption;
use DreaM1\EfakturaConnector\Core\Contracts\CodeListInterface;

/** Exposes Peppol Electronic Address Scheme codes as options. */
final class ElectronicAddressSchemeCodeListProvider implements CodeListInterface
{
    /** {@inheritdoc} */
    public function identifier(): string
    {
        return CodeListIdentifiers::ELECTRONIC_ADDRESS_SCHEMES;
    }

    /** @return CodeListOption[] */
    public function options(): array
    {
        $options = [];

        foreach (ElectronicAddressSchemeCodeList::all() as $code) {
            $options[] = new CodeListOption($code, $code);
        }

        return $options;
    }
}
