<?php

namespace DreaM1\EfakturaConnector\Support;

use DreaM1\EfakturaConnector\Support\Exceptions\InvariantViolationException;

/**
 * Stops a value object that holds a secret from being written out by serialize().
 *
 * PHP serializes private properties verbatim, so a host job, cache entry or session holding such an object
 * would store the secret in clear text. Refusing is safer than omitting the value, because an object restored
 * without its secret would only fail later, at the first provider call. @internal
 */
trait RefusesSerialization
{
    /**
     * @return array<string, mixed>
     * @throws InvariantViolationException always
     */
    public function __serialize(): array
    {
        throw new InvariantViolationException(static::class . ' holds a secret and cannot be serialized.');
    }

    /**
     * @param  array<string, mixed>        $data Serialized state, which is never accepted.
     * @throws InvariantViolationException always
     */
    public function __unserialize(array $data): void
    {
        throw new InvariantViolationException(static::class . ' holds a secret and cannot be unserialized.');
    }
}
