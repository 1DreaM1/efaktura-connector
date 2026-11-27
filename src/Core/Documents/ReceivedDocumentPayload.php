<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Domain\EFaktura\ReceivedDocumentMetadata;

/** Payload downloaded for a received provider document, with its provider-reported format. */
final class ReceivedDocumentPayload
{
    private string $xml;
    private string $payloadFormat;
    private ReceivedDocumentMetadata $metadata;

    /**
     * @param string                   $xml           XML payload downloaded for the received document.
     * @param string                   $payloadFormat Format reported for the document payload.
     * @param ReceivedDocumentMetadata $metadata      Provider metadata associated with the document.
     */
    public function __construct(
        string $xml,
        string $payloadFormat,
        ReceivedDocumentMetadata $metadata
    ) {
        $this->xml           = $xml;
        $this->payloadFormat = $payloadFormat;
        $this->metadata      = $metadata;
    }

    /**
     * Returns the XML.
     *
     * @return string
     */
    public function xml(): string
    {
        return $this->xml;
    }

    /**
     * Returns the payload format exactly as supplied.
     *
     * @return string
     */
    public function payloadFormat(): string
    {
        return $this->payloadFormat;
    }

    /** Returns the provider metadata included with the downloaded payload. */
    public function metadata(): ReceivedDocumentMetadata
    {
        return $this->metadata;
    }
}
