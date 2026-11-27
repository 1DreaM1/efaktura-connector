<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/**
 * Maps POST /v1/validate-document → 200 ValidateDocumentResponse to the provider's verdict: { valid? }
 *
 * The schema marks `valid` optional, but a response without it carries no verdict, so it is rejected here as an
 * invalid provider response instead of being guessed.
 */
final class DocumentValidationVerdictReader
{
    private const SCHEMA = 'ValidateDocumentResponse';

    private function __construct()
    {
    }

    /**
     * Reads the validation verdict from a decoded provider response object.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return bool                             True when the provider accepted the document.
     * @throws InvalidProviderResponseException when the verdict is missing or not a boolean.
     */
    public static function fromArray(array $data): bool
    {
        $valid = (new ProviderResponseReader($data, self::SCHEMA))->optionalBool('valid');

        if ($valid === null) {
            throw new InvalidProviderResponseException(
                'Provider response ValidateDocumentResponse is missing the "valid" verdict.',
                self::SCHEMA,
                'valid'
            );
        }

        return $valid;
    }
}
