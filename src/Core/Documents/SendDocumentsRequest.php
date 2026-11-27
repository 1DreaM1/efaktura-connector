<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Domain\EFaktura\CodeList\ParticipantIdentifierSchemeCodeList;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** Input for submitting a batch of outbound documents through the Peppol Gateway. */
final class SendDocumentsRequest
{
    /** Maximum number of documents accepted by one Gateway batch request. */
    public const MAX_DOCUMENTS = 100;

    private Participant $participant;
    /** @var OutboundGatewayDocument[] */
    private array $documents;
    private IdempotencyKey $idempotencyKey;

    /**
     * Creates a participant-scoped batch request with a stable idempotency key.
     *
     * @param  Participant               $participant    Sender on whose behalf the batch is submitted.
     * @param  OutboundGatewayDocument[] $documents      Documents to submit.
     * @param  IdempotencyKey            $idempotencyKey Stable key reused for retries of this logical batch.
     * @throws InvalidInputException     when the sender scheme, batch size (1 to MAX_DOCUMENTS), or an item is invalid
     */
    public function __construct(Participant $participant, array $documents, IdempotencyKey $idempotencyKey)
    {
        if (! ParticipantIdentifierSchemeCodeList::isAllowedForNewDocument($participant->scheme())) {
            throw new InvalidInputException('Sender must use an active, registrable Peppol participant identifier scheme.');
        }

        if ($documents === []) {
            throw new InvalidInputException('A Gateway batch must contain at least one document.');
        }

        if (count($documents) > self::MAX_DOCUMENTS) {
            throw new InvalidInputException(sprintf(
                'A Gateway batch may contain at most %d documents.',
                self::MAX_DOCUMENTS
            ));
        }

        $documents = TypedArray::valuesOfType(
            $documents,
            OutboundGatewayDocument::class,
            'Gateway batch items must be OutboundGatewayDocument instances.'
        );

        $this->participant    = $participant;
        $this->documents      = $documents;
        $this->idempotencyKey = $idempotencyKey;
    }

    /** Returns the sender on whose behalf the batch is submitted. */
    public function participant(): Participant
    {
        return $this->participant;
    }

    /** @return OutboundGatewayDocument[] Documents in submission order. */
    public function documents(): array
    {
        return $this->documents;
    }

    /** Returns the stable key identifying this logical batch. */
    public function idempotencyKey(): IdempotencyKey
    {
        return $this->idempotencyKey;
    }
}
