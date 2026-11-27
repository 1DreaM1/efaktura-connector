<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Data read from a UBL Invoice or CreditNote by UblDocumentReaderInterface. */
final class UblDocument
{
    private UblDocumentHeader $header;
    private UblDocumentContent $content;

    /**
     * @param  UblDocumentHeader     $header  Header fields read from the UBL document.
     * @param  UblDocumentContent    $content Lines and tax details read from the UBL document.
     * @throws InvalidInputException when the header and content specify different document roots.
     */
    public function __construct(UblDocumentHeader $header, UblDocumentContent $content)
    {
        $headerRoot = $header->documentRoot();

        if ($headerRoot !== null && $headerRoot->value() !== $content->documentKind()) {
            throw new InvalidInputException('UBL document header and content must use the same document root.');
        }

        $this->header  = $header;
        $this->content = $content;
    }

    /**
     * Local name of the root element, see UblDocumentRoot.
     *
     * @return string
     */
    public function kind(): string
    {
        return $this->content->documentKind();
    }

    /**
     * Returns the header.
     *
     * @return UblDocumentHeader
     */
    public function header(): UblDocumentHeader
    {
        return $this->header;
    }

    /**
     * Returns the content.
     *
     * @return UblDocumentContent
     */
    public function content(): UblDocumentContent
    {
        return $this->content;
    }
}
