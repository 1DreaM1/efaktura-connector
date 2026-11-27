<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Internal;

use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentRoot;

/** @internal Document identity fields read from a UBL header. */
final class UblDocumentHeaderIdentity
{
    private ?string $number;
    private ?string $customizationId;
    private ?PeppolProcessIdentifier $processId;
    private ?string $documentTypeCode;
    private ?UblDocumentRoot $documentRoot;
    private ?PeppolDocumentTypeIdentifier $documentTypeId;

    public function __construct(
        ?string $number,
        ?string $customizationId,
        ?string $processId,
        ?string $documentTypeCode,
        ?string $documentRoot,
        ?string $documentTypeId
    ) {
        $this->number           = $number;
        $this->customizationId  = $customizationId;
        $this->processId        = $processId !== null ? PeppolProcessIdentifier::fromString($processId) : null;
        $this->documentTypeCode = $documentTypeCode;
        $this->documentRoot     = $documentRoot !== null ? UblDocumentRoot::fromString($documentRoot) : null;
        $this->documentTypeId   = $documentTypeId !== null
            ? PeppolDocumentTypeIdentifier::fromString($documentTypeId)
            : null;
    }

    public function number(): ?string
    {
        return $this->number;
    }

    public function customizationId(): ?string
    {
        return $this->customizationId;
    }

    public function processId(): ?PeppolProcessIdentifier
    {
        return $this->processId;
    }

    public function documentTypeCode(): ?string
    {
        return $this->documentTypeCode;
    }

    public function documentRoot(): ?UblDocumentRoot
    {
        return $this->documentRoot;
    }

    public function documentTypeId(): ?PeppolDocumentTypeIdentifier
    {
        return $this->documentTypeId;
    }
}
