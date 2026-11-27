<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;

/**
 * Gives a typed provider response its standard `fromArray()` entry point.
 *
 * The using class declares a `SCHEMA` constant naming the provider schema (used in error messages) and implements
 * `fromData()`, which reads validated fields from a {@see ProviderResponseReader}.
 */
trait ReadsProviderResponse
{
    /**
     * Maps a decoded provider response object to its typed value.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return self
     * @throws InvalidProviderResponseException when a required field is missing or invalid
     */
    public static function fromArray(array $data): self
    {
        return self::fromData(new ProviderResponseReader($data, self::SCHEMA));
    }
}
