<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Support\NonBlankString;

/** Identifies the host document for request logs without coupling the connector to its model. */
final class DocumentRequestContext
{
    private string $documentId;
    private ?string $documentNumber;

    /**
     * @param string      $documentId     Provider document identifier.
     * @param string|null $documentNumber Business document number assigned by the sender.
     */
    public function __construct(string $documentId, ?string $documentNumber)
    {
        NonBlankString::assertNotBlank($documentId, 'Document ID must not be empty.');

        $this->documentId     = $documentId;
        $this->documentNumber = $documentNumber;
    }

    /**
     * Returns the document ID.
     *
     * @return string
     */
    public function documentId(): string
    {
        return $this->documentId;
    }

    /**
     * Returns the document number.
     *
     * @return string|null
     */
    public function documentNumber(): ?string
    {
        return $this->documentNumber;
    }

    /**
     * Returns the stable log-context keys in snake_case.
     *
     * @return array{document_id: string, document_number: string|null}
     */
    public function toArray(): array
    {
        return [
            'document_id'     => $this->documentId,
            'document_number' => $this->documentNumber,
        ];
    }
}
