<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Finds the code lists available to a host application. */
interface CodeListRegistryInterface
{
    /**
     * Returns the registered list for an identifier.
     *
     * @param  string                $identifier Stable code list identifier.
     * @return CodeListInterface
     * @throws InvalidInputException when the identifier is not registered.
     */
    public function get(string $identifier): CodeListInterface;

    /**
     * Returns registered identifiers in registration order.
     *
     * @return string[]
     */
    public function identifiers(): array;
}
