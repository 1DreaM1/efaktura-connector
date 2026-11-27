<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

/** Item commodity classification code with its code list identifiers. */
final class UblDraftCommodityClassification
{
    /** Item classification value written to cbc:ItemClassificationCode. */
    private string $code;
    /** Classification list identifier written to ItemClassificationCode/@listID. */
    private string $listId;
    /** Optional code-list version written to ItemClassificationCode/@listVersionID. */
    private ?string $listVersionId;

    /**
     * Creates a commodity classification with the code-list metadata written to UBL.
     *
     * @param string      $code          Item classification value.
     * @param string      $listId        Identifier of the classification code list.
     * @param string|null $listVersionId Version of the code list, when known.
     */
    public function __construct(string $code, string $listId, ?string $listVersionId = null)
    {
        $this->code          = $code;
        $this->listId        = $listId;
        $this->listVersionId = UblDraftText::emptyToNull($listVersionId);
    }

    /** Returns the item classification value. */
    public function code(): string
    {
        return $this->code;
    }

    /** Returns the classification code-list identifier. */
    public function listId(): string
    {
        return $this->listId;
    }

    /** Returns the code-list version, or null when it is not supplied. */
    public function listVersionId(): ?string
    {
        return $this->listVersionId;
    }
}
