<?php

namespace DreaM1\EfakturaConnector\Testing;

use DreaM1\EfakturaConnector\Core\Contracts\DocumentSubmissionGatewayInterface;
use DreaM1\EfakturaConnector\Core\Documents\DocumentSubmissionResult;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentRequest;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Support\Timestamp;
use Throwable;

/**
 * In-memory stand-in for document submission, for host application tests. It contacts no provider.
 *
 * Every call is recorded. Queued outcomes are consumed in order; once the queue is empty each new submission is
 * accepted with a generated document ID. Like the provider, a repeated idempotency key replays the first outcome
 * without consuming the queue and without recording a second submission.
 *
 * @api
 */
final class FakeDocumentSubmissionGateway implements DocumentSubmissionGatewayInterface
{
    private const DEFAULT_TIMESTAMP = '2000-01-01T00:00:00.000000+00:00';

    /** @var list<DocumentSubmissionResult|Throwable> */
    private array $queue = [];

    /** @var list<SendDocumentRequest> */
    private array $sent = [];

    /** @var array<string, DocumentSubmissionResult|Throwable> */
    private array $outcomesByKey = [];

    /** Queues a result for the next new submission. */
    public function willReturn(DocumentSubmissionResult $result): self
    {
        $this->queue[] = $result;

        return $this;
    }

    /** Queues a failure for the next new submission, for example a ProviderConnectionException. */
    public function willThrow(Throwable $failure): self
    {
        $this->queue[] = $failure;

        return $this;
    }

    public function send(SendDocumentRequest $request): DocumentSubmissionResult
    {
        $key = $request->idempotencyKey()->value();

        if (isset($this->outcomesByKey[$key])) {
            return $this->resolve($this->outcomesByKey[$key]);
        }

        $this->sent[] = $request;
        $outcome      = array_shift($this->queue) ?? $this->acceptedResult(count($this->sent));

        // A failed submission may or may not have been recorded by the provider, so only successes are replayed.
        if ($outcome instanceof DocumentSubmissionResult) {
            $this->outcomesByKey[$key] = $outcome;
        }

        return $this->resolve($outcome);
    }

    /**
     * Returns the distinct submissions the fake saw, in call order.
     *
     * @return list<SendDocumentRequest>
     */
    public function sent(): array
    {
        return $this->sent;
    }

    /** Returns the most recent distinct submission, or null when nothing was sent. */
    public function lastRequest(): ?SendDocumentRequest
    {
        return $this->sent === [] ? null : $this->sent[count($this->sent) - 1];
    }

    /**
     * @param  DocumentSubmissionResult|Throwable $outcome
     * @throws Throwable                          when the outcome is a queued failure
     */
    private function resolve($outcome): DocumentSubmissionResult
    {
        if ($outcome instanceof Throwable) {
            throw $outcome;
        }

        return $outcome;
    }

    private function acceptedResult(int $sequence): DocumentSubmissionResult
    {
        return new DocumentSubmissionResult(
            ProviderDocumentId::fromString('fake-document-' . $sequence),
            ProviderDocumentStatus::fromString(ProviderDocumentStatus::ACCEPTED),
            Timestamp::fromIso8601(self::DEFAULT_TIMESTAMP),
            Timestamp::fromIso8601(self::DEFAULT_TIMESTAMP)
        );
    }
}
