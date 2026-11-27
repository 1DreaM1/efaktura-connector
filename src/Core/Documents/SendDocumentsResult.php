<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** Result of a Peppol Gateway batch submission. */
final class SendDocumentsResult
{
    private int $count;
    /** @var ProviderDocumentId[] */
    private array $identifiers;

    /**
     * Creates a batch submission result.
     *
     * @param  int                   $count       Number of documents accepted by the Gateway.
     * @param  ProviderDocumentId[]  $identifiers Gateway identifiers returned for accepted documents.
     * @throws InvalidInputException when count is negative, does not match the identifiers, or an identifier
     *                               has the wrong type
     */
    public function __construct(int $count, array $identifiers)
    {
        if ($count < 0) {
            throw new InvalidInputException('Accepted document count must not be negative.');
        }

        $identifiers = TypedArray::valuesOfType(
            $identifiers,
            ProviderDocumentId::class,
            'Gateway identifiers must be ProviderDocumentId instances.'
        );

        if ($count !== count($identifiers)) {
            throw new InvalidInputException('Accepted document count must match the number of Gateway identifiers.');
        }

        $this->count       = $count;
        $this->identifiers = $identifiers;
    }

    /** Returns the number of documents accepted by the Gateway. */
    public function count(): int
    {
        return $this->count;
    }

    /** @return ProviderDocumentId[] Gateway identifiers for accepted documents. */
    public function identifiers(): array
    {
        return $this->identifiers;
    }
}
