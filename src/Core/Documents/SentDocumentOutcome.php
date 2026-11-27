<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

/** Downstream delivery details of a submitted document: provider references and any error. */
final class SentDocumentOutcome
{
    private ?SentDocumentProviderReference $provider;
    private ?SentDocumentError $error;

    /**
     * Creates the delivery outcome.
     *
     * @param SentDocumentProviderReference|null $provider Downstream provider identifiers.
     * @param SentDocumentError|null             $error    Provider error details, if the document failed.
     */
    public function __construct(?SentDocumentProviderReference $provider = null, ?SentDocumentError $error = null)
    {
        $this->provider = $provider;
        $this->error    = $error;
    }

    /** Returns downstream provider identifiers, or null when omitted. */
    public function provider(): ?SentDocumentProviderReference
    {
        return $this->provider;
    }

    /** Returns provider error details, or null when the response has no error. */
    public function error(): ?SentDocumentError
    {
        return $this->error;
    }
}
