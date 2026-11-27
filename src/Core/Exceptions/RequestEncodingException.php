<?php

namespace DreaM1\EfakturaConnector\Core\Exceptions;

use Throwable;

/**
 * A request body could not be encoded (e.g. invalid UTF-8). Nothing was sent to the provider.
 */
final class RequestEncodingException extends EFakturaException
{
    /**
     * @param string         $message  Message or diagnostic text associated with the result.
     * @param Throwable|null $previous Previous exception that caused this failure, when available.
     */
    public function __construct(string $message = 'API request body could not be encoded.', ?Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
