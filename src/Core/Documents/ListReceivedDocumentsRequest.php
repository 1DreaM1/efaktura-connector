<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Core\Pagination\PageSize;
use DreaM1\EfakturaConnector\Core\Pagination\PageToken;
use DreaM1\EfakturaConnector\Domain\EFaktura\EFakturaProviderDataLimits;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Input for listing one page of a participant's received documents. */
final class ListReceivedDocumentsRequest
{
    private Participant $participant;
    private ?PageToken $pageToken;
    private ?PageSize $limit;
    private ?ProviderDocumentStatus $status;

    /**
     * Creates a received-document list request with optional pagination and status filtering.
     *
     * @param  Participant                 $participant Participant whose inbox is queried.
     * @param  PageToken|null              $pageToken   Continuation token from the previous page.
     * @param  PageSize|null               $limit       Maximum number of documents in the page.
     * @param  ProviderDocumentStatus|null $status      Optional received-document status filter.
     * @throws InvalidInputException       when the page size or status is unsupported
     */
    public function __construct(
        Participant $participant,
        ?PageToken $pageToken = null,
        ?PageSize $limit = null,
        ?ProviderDocumentStatus $status = null
    ) {
        if ($limit !== null && $limit->value() > EFakturaProviderDataLimits::PAGE_SIZE_MAX) {
            throw new InvalidInputException('Page size is outside the supported range.');
        }

        if (
            $status !== null
            && ! in_array($status->value(), ProviderDocumentStatus::RECEIVED_DOCUMENT_STATUSES, true)
        ) {
            throw new InvalidInputException('Unsupported received document status filter.');
        }

        $this->participant = $participant;
        $this->pageToken   = $pageToken;
        $this->limit       = $limit;
        $this->status      = $status;
    }

    /** Returns the participant whose received documents are listed. */
    public function participant(): Participant
    {
        return $this->participant;
    }

    /** Returns the continuation token, or null for the first page. */
    public function pageToken(): ?PageToken
    {
        return $this->pageToken;
    }

    /** Returns the requested page size, or null to use the provider default. */
    public function limit(): ?PageSize
    {
        return $this->limit;
    }

    /** Returns the optional received-document status filter. */
    public function status(): ?ProviderDocumentStatus
    {
        return $this->status;
    }
}
