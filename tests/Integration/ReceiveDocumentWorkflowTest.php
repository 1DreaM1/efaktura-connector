<?php

namespace DreaM1\EfakturaConnector\Tests\Integration;

use DreaM1\EfakturaConnector\Core\Documents\DocumentRequestContext;
use DreaM1\EfakturaConnector\Core\Documents\ListReceivedDocumentsRequest;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentPage;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentReference;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Core\Pagination\PageSize;
use DreaM1\EfakturaConnector\Core\Retry\RetryPolicy;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\AcknowledgeReceivedDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\GetReceivedDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\ListReceivedDocumentsEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\RetryingHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\SapiReceivedDocumentProvider;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;

/** Exercises paginated inbox retrieval, document download and acknowledgement through synthetic SAPI responses. */
class ReceiveDocumentWorkflowTest extends WorkflowTestCase
{
    // ── 6b List inbox page ───────────────────────────────────────────────────

    public function testListInboxReturnsDocumentsAndNextPageToken(): void
    {
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(200, [
                'documents' => [
                    $this->documentItem('doc-1'),
                    $this->documentItem('doc-2'),
                ],
                'nextPageToken' => 'next-page-token-1',
            ]),
        ]);

        $page = $this->buildProvider($executor)->getInboxPage(
            new ListReceivedDocumentsRequest(
                $this->participant(),
                null,
                PageSize::fromInt(50)
            )
        );

        $this->assertInstanceOf(ReceivedDocumentPage::class, $page);
        $this->assertCount(2, $page->documents());
        $this->assertSame('next-page-token-1', $page->nextPageToken()->value());
    }

    public function testListInboxEmptyPageHasNullNextPageToken(): void
    {
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(200, ['documents' => []]),
        ]);

        $page = $this->buildProvider($executor)->getInboxPage(
            new ListReceivedDocumentsRequest(
                $this->participant(),
                null,
                PageSize::fromInt(20)
            )
        );

        $this->assertCount(0, $page->documents());
        $this->assertNull($page->nextPageToken());
    }

    // ── 6b Pagination ────────────────────────────────────────────────────────

    public function testPaginatedInboxFetchesSecondPageWithTokenFromFirstPage(): void
    {
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(200, [
                'documents'     => [$this->documentItem('doc-p1')],
                'nextPageToken' => 'page-2-token',
            ]),
            // token is cached after first call — no second token request
            $this->jsonExchange(200, [
                'documents' => [$this->documentItem('doc-p2')],
            ]),
        ]);

        $provider = $this->buildProvider($executor);

        $page1 = $provider->getInboxPage(
            new ListReceivedDocumentsRequest(
                $this->participant(),
                null,
                PageSize::fromInt(1)
            )
        );
        $this->assertSame('page-2-token', $page1->nextPageToken()->value());

        $page2 = $provider->getInboxPage(
            new ListReceivedDocumentsRequest(
                $this->participant(),
                $page1->nextPageToken(),
                PageSize::fromInt(1)
            )
        );
        $this->assertNull($page2->nextPageToken());
        $this->assertSame('doc-p2', $page2->documents()[0]->documentId()->value());
    }

    public function testInboxGetRetriesAfterConnectionFailure(): void
    {
        $delays   = [];
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->curlFailure('Synthetic connection drop during inbox lookup.'),
            $this->jsonExchange(200, [
                'documents' => [$this->documentItem('provider-doc-get-retry-1')],
            ]),
        ]);
        $retryingExecutor = new RetryingHttpRequestExecutor(
            $executor,
            RetryPolicy::default(),
            static function (int $delayMs) use (&$delays): void {
                $delays[] = $delayMs;
            }
        );

        $page = $this->buildProvider($retryingExecutor)->getInboxPage(
            new ListReceivedDocumentsRequest(
                $this->participant(),
                null,
                PageSize::fromInt(10)
            )
        );

        $this->assertSame('provider-doc-get-retry-1', $page->documents()[0]->documentId()->value());
        $this->assertSame([500], $delays);
    }

    // ── 6b Fetch payload ─────────────────────────────────────────────────────

    public function testFetchDocumentPayloadReturnsXmlString(): void
    {
        $xml      = '<Invoice>synthetic</Invoice>';
        $format   = 'application/xml';
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(200, ApiFixtures::receivedDocumentDetail($xml, 'provider-doc-1', $format)),
        ]);

        $payload = $this->buildProvider($executor)->getDocumentPayload(new ReceivedDocumentReference(
            $this->participant(),
            ProviderDocumentId::fromString('provider-doc-1'),
            new DocumentRequestContext('21', 'INV-SYNTHETIC-21')
        ));

        $this->assertSame($xml, $payload->xml());
        $this->assertSame($format, $payload->payloadFormat());
        $this->assertSame(ApiFixtures::TIMESTAMP, $payload->metadata()->creationDateTime()->value());
    }

    public function testMalformedInboxResponseDoesNotBecomeAnEmptyPage(): void
    {
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(200, ['nextPageToken' => 'page-2']),
        ]);

        try {
            $this->buildProvider($executor)->getInboxPage(
                new ListReceivedDocumentsRequest(
                    $this->participant(),
                    null,
                    PageSize::fromInt(10)
                )
            );
            $this->fail('A successful inbox response without documents must be rejected.');
        } catch (InvalidProviderResponseException $exception) {
            $this->assertSame('SapiReceivedDocumentListResponse', $exception->schema());
            $this->assertSame('documents', $exception->field());
            $this->assertSame(200, $exception->httpStatusCode()->value());
        }
    }

    public function testPayloadDownloadProviderErrorPreservesStatusAndParsedDetails(): void
    {
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(410, ApiFixtures::error('Synthetic document is no longer available.')),
        ]);

        try {
            $this->buildProvider($executor)->getDocumentPayload(new ReceivedDocumentReference(
                $this->participant(),
                ProviderDocumentId::fromString('provider-doc-expired-1'),
                new DocumentRequestContext('21', 'INV-SYNTHETIC-EXPIRED')
            ));
            $this->fail('A provider error during payload download must be surfaced.');
        } catch (ProviderErrorResponseException $exception) {
            $this->assertSame(410, $exception->httpStatusCode()->value());
            $this->assertSame('Synthetic document is no longer available.', $exception->errorEnvelope()->message());
        }
    }

    // ── 6b Acknowledge ───────────────────────────────────────────────────────

    public function testAcknowledgeDocumentSendsAcknowledgeRequestToProvider(): void
    {
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(200, ApiFixtures::acknowledge('provider-doc-1')),
        ]);

        // No exception means success
        $this->buildProvider($executor)->acknowledge(new ReceivedDocumentReference(
            $this->participant(),
            ProviderDocumentId::fromString('provider-doc-1'),
            new DocumentRequestContext('21', 'INV-SYNTHETIC-21')
        ));
        $this->addToAssertionCount(1);
    }

    public function testReceiveWorkflowListsDownloadsAndAcknowledgesDocument(): void
    {
        $xml      = '<Invoice>synthetic-received-document</Invoice>';
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(200, [
                'documents' => [$this->documentItem('provider-doc-inbox-1')],
            ]),
            $this->jsonExchange(200, ApiFixtures::receivedDocumentDetail($xml, 'provider-doc-inbox-1')),
            $this->jsonExchange(200, ApiFixtures::acknowledge('provider-doc-inbox-1')),
        ]);
        $provider = $this->buildProvider($executor);
        $context  = new DocumentRequestContext('21', 'INV-SYNTHETIC-21');

        $page = $provider->getInboxPage(
            new ListReceivedDocumentsRequest(
                $this->participant(),
                null,
                PageSize::fromInt(50)
            )
        );
        $this->assertCount(1, $page->documents());
        $this->assertSame('provider-doc-inbox-1', $page->documents()[0]->documentId()->value());

        $reference = new ReceivedDocumentReference(
            $this->participant(),
            $page->documents()[0]->documentId(),
            $context
        );
        $payload = $provider->getDocumentPayload($reference);
        $this->assertSame($xml, $payload->xml());

        $provider->acknowledge($reference);
        $this->addToAssertionCount(1);
    }

    public function testAcknowledgeRetriesAfterConnectionFailure(): void
    {
        $delays   = [];
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(200, [
                'documents' => [$this->documentItem('provider-doc-retry-1')],
            ]),
            $this->jsonExchange(
                200,
                ApiFixtures::receivedDocumentDetail('<Invoice>synthetic-retry</Invoice>', 'provider-doc-retry-1')
            ),
            $this->curlFailure('Synthetic connection drop during acknowledgement.'),
            $this->jsonExchange(200, ApiFixtures::acknowledge('provider-doc-retry-1')),
        ]);
        $retryingExecutor = new RetryingHttpRequestExecutor(
            $executor,
            RetryPolicy::default(),
            static function (int $delayMs) use (&$delays): void {
                $delays[] = $delayMs;
            }
        );
        $provider = $this->buildProvider($retryingExecutor);
        $context  = new DocumentRequestContext('21', 'INV-SYNTHETIC-RETRY');

        $provider->getInboxPage(
            new ListReceivedDocumentsRequest(
                $this->participant(),
                null,
                PageSize::fromInt(10)
            )
        );
        $reference = new ReceivedDocumentReference(
            $this->participant(),
            ProviderDocumentId::fromString('provider-doc-retry-1'),
            $context
        );
        $provider->getDocumentPayload($reference);
        $provider->acknowledge($reference);

        $this->assertSame([500], $delays);
    }

    public function testNonRetryableAcknowledgeServerErrorIsSurfacedWithoutAnotherAttempt(): void
    {
        $delays   = [];
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(200, [
                'documents' => [$this->documentItem('provider-doc-ack-error-1')],
            ]),
            $this->jsonExchange(200, ApiFixtures::receivedDocumentDetail(
                '<Invoice>synthetic-ack-error</Invoice>',
                'provider-doc-ack-error-1'
            )),
            $this->jsonExchange(503, ApiFixtures::error('Synthetic acknowledgement outage.')),
        ]);
        $retryingExecutor = new RetryingHttpRequestExecutor(
            $executor,
            RetryPolicy::default(),
            static function (int $delayMs) use (&$delays): void {
                $delays[] = $delayMs;
            }
        );
        $provider = $this->buildProvider($retryingExecutor);
        $context  = new DocumentRequestContext('21', 'INV-SYNTHETIC-ACK-ERROR');

        $page = $provider->getInboxPage(
            new ListReceivedDocumentsRequest(
                $this->participant(),
                null,
                PageSize::fromInt(10)
            )
        );
        $reference = new ReceivedDocumentReference($this->participant(), $page->documents()[0]->documentId(), $context);
        $provider->getDocumentPayload($reference);

        try {
            $provider->acknowledge($reference);
            $this->fail('A non-retryable provider failure must be surfaced.');
        } catch (ProviderErrorResponseException $exception) {
            $this->assertSame(503, $exception->httpStatusCode()->value());
        }

        $this->assertSame([], $delays);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Creates one synthetic received-document metadata item.
     *
     * @param  string               $documentId Provider document identifier.
     * @return array<string, mixed> Received-document metadata response.
     */
    private function documentItem(string $documentId): array
    {
        return ApiFixtures::documentMetadata($documentId, self::RECEIVER_ID, self::PARTICIPANT_ID);
    }

    /**
     * Creates a received-document provider using the supplied HTTP executor.
     *
     * @param  HttpRequestExecutorInterface $executor Executor used for provider requests.
     * @return SapiReceivedDocumentProvider Configured received-document provider.
     */
    private function buildProvider($executor): SapiReceivedDocumentProvider
    {
        $connector = $this->buildConnector($executor);

        return new SapiReceivedDocumentProvider(
            new ListReceivedDocumentsEndpoint($connector),
            new GetReceivedDocumentEndpoint($connector),
            new AcknowledgeReceivedDocumentEndpoint($connector)
        );
    }
}
