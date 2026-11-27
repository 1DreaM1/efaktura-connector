<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Core\Pagination\PageCursor;
use DreaM1\EfakturaConnector\Core\Pagination\PageToken;
use DreaM1\EfakturaConnector\Domain\EFaktura\ReceivedDocumentMetadata;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** One page of inbox metadata and the token used to request the next page. */
final class ReceivedDocumentPage
{
    /** @var ReceivedDocumentMetadata[] */
    private array $documents;
    private PageCursor $cursor;

    /**
     * Creates an inbox page from document metadata and its continuation token.
     * @param ReceivedDocumentMetadata[] $documents     Received documents to include in the page.
     * @param PageToken|null             $nextPageToken Continuation token for the next page, when available.
     */
    public function __construct(array $documents, ?PageToken $nextPageToken)
    {
        $this->documents = TypedArray::valuesOfType(
            $documents,
            ReceivedDocumentMetadata::class,
            'Received document pages must contain only metadata values.'
        );
        $this->cursor = new PageCursor($nextPageToken);
    }

    /**
     * Returns the documents.
     * @return ReceivedDocumentMetadata[]
     */
    public function documents(): array
    {
        return $this->documents;
    }

    /**
     * Returns the next page token.
     *
     * @return PageToken|null
     */
    public function nextPageToken(): ?PageToken
    {
        return $this->cursor->nextPageToken();
    }
}
