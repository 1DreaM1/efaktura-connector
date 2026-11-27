<?php

namespace DreaM1\EfakturaConnector\Core\Exceptions;

/** The encoded API request body exceeds the connector's configured byte limit. */
final class RequestBodyTooLargeException extends EFakturaException
{
    private int $maxBytes;

    /**
     * Creates an exception for a request body that exceeds its maximum encoded size.
     *
     * @param int $maxBytes Maximum serialized request body size in bytes.
     */
    public function __construct(int $maxBytes)
    {
        $this->maxBytes = $maxBytes;

        parent::__construct('API request body exceeds the configured size limit of ' . $maxBytes . ' bytes.');
    }

    /** Returns the maximum serialized request body size in bytes. */
    public function maxBytes(): int
    {
        return $this->maxBytes;
    }
}
