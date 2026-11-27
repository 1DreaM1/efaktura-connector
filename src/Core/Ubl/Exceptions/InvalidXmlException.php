<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Exceptions;

/**
 * The document is not well-formed XML, or it is unsafe to parse (too large, DTD / ENTITY declarations).
 */
final class InvalidXmlException extends UblValidationException
{
    private bool $unsafe;

    /** Restricts construction to the malformed and unsafe XML exception factories. */
    private function __construct(string $message, bool $unsafe)
    {
        parent::__construct($message);
        $this->unsafe = $unsafe;
    }

    /**
     * Creates an exception for XML rejected by the parser's safety limits.
     *
     * @return self
     */
    public static function unsafe(): self
    {
        return new self('XML is too large or contains a prohibited DTD / ENTITY declaration.', true);
    }

    /**
     * Returns the malformed.
     *
     * @return self
     */
    public static function malformed(): self
    {
        return new self('XML is not well-formed.', false);
    }

    /**
     * Whether the XML was rejected as unsafe.
     *
     * @return bool
     */
    public function isUnsafe(): bool
    {
        return $this->unsafe;
    }
}
