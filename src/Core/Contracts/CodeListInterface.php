<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\CodeLists\CodeListOption;

/** Provides selectable options for one identified code list. */
interface CodeListInterface
{
    /** Returns the stable identifier used to retrieve this list from a registry. */
    public function identifier(): string;

    /**
     * Returns the options in source order.
     *
     * @return CodeListOption[]
     */
    public function options(): array;
}
