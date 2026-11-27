<?php

namespace DreaM1\EfakturaConnector\Tests\Integration;

use DreaM1\EfakturaConnector\Core\Documents\DocumentRequestContext;
use DreaM1\EfakturaConnector\Core\Documents\DocumentSubmissionResult;
use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use DreaM1\EfakturaConnector\Core\Documents\OutboundDocumentPayload;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentRequest;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentReference;
use DreaM1\EfakturaConnector\Core\Documents\Sha256Checksum;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Core\MessageStatuses\ProviderMessageLevelStatusResponse;
use DreaM1\EfakturaConnector\Core\Retry\RetryPolicy;
use DreaM1\EfakturaConnector\Domain\EFaktura\MessageLevelStatusCode;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\SubmittedDocumentId;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\CountryCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\CurrencyCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\UnitCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\TaxCategoryCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDecimal;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentDraft;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentNumber;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftIdentifier;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftLine;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftParty;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftTaxCategory;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftTaxSubtotal;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftTotals;
use DreaM1\EfakturaConnector\Domain\Ubl\LocalDate;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentRoot;
use DreaM1\EfakturaConnector\Domain\Ubl\UblEndpointIdentifier;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetMessageLevelStatusContentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetSentDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\ListMessageLevelStatusesEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\SendDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\RetryingHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\PeppolGatewayDocumentTrackingProvider;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\SapiDocumentSubmissionGateway;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\UblDraftSubmission;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Writer\UblDocumentWriter;
use DreaM1\EfakturaConnector\Support\Timestamp;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;

/** Exercises UBL submission, provider outcomes, tracking and the no-retry rule for ambiguous sends. */
class SendDocumentWorkflowTest extends WorkflowTestCase
{
    // ── 6a Happy path ────────────────────────────────────────────────────────

    public function testDocumentSendFlowAcquiresTokenAndSubmitsPayload(): void
    {
        $executor = $this->queuedExecutor([
            $this->jsonExchange(200, ApiFixtures::token(['token_type' => 'bearer'])),
            $this->jsonExchange(202, ApiFixtures::sendDocument('provider-send-1')),
        ]);

        $result = (new SapiDocumentSubmissionGateway(new SendDocumentEndpoint($this->buildConnector($executor))))
            ->send(new SendDocumentRequest(
                $this->participant(),
                $this->testPayload(),
                IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001')
            ));

        $this->assertInstanceOf(DocumentSubmissionResult::class, $result);
        $this->assertSame('provider-send-1', $result->providerDocumentId()->value());
        $this->assertSame('ACCEPTED', $result->status()->value());
    }

    public function testSubmissionPreservesAnUnknownProviderStatus(): void
    {
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(202, ApiFixtures::sendDocument('provider-future-1', 'FUTURE_STATUS')),
        ]);

        $result = (new SapiDocumentSubmissionGateway(new SendDocumentEndpoint($this->buildConnector($executor))))
            ->send(new SendDocumentRequest(
                $this->participant(),
                $this->testPayload(),
                IdempotencyKey::fromString('00000000-0000-4000-8000-000000000021')
            ));

        $this->assertSame('FUTURE_STATUS', $result->status()->value());
        $this->assertFalse($result->status()->isKnown());
        $this->assertNull($result->receivedAt());
    }

    public function testTrackingPreservesUnknownStatusAndPayloadFormat(): void
    {
        $connector = $this->buildConnector($this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(200, ApiFixtures::sentDocument('FUTURE_DELIVERY_STATUS', [
                'payloadFormat' => 'FUTURE_XML_PROFILE',
            ])),
        ]));
        $tracking = new PeppolGatewayDocumentTrackingProvider(
            new GetSentDocumentEndpoint($connector),
            new ListMessageLevelStatusesEndpoint($connector),
            new GetMessageLevelStatusContentEndpoint($connector)
        );
        $status = $tracking->getSentDocumentStatus(new SentDocumentReference(
            $this->participant(),
            ProviderDocumentId::fromString('provider-future-2'),
            new DocumentRequestContext('22', 'INV-SYNTHETIC-2')
        ));

        $this->assertSame('FUTURE_DELIVERY_STATUS', $status->status()->value());
        $this->assertFalse($status->status()->isKnown());
        $this->assertSame('FUTURE_XML_PROFILE', $status->payloadFormat());
    }

    public function testUblDraftIsWrittenAndIncludedInRecordedSendRequest(): void
    {
        $draft    = $this->invoiceDraftForSubmission();
        $writer   = new UblDocumentWriter();
        $xml      = $writer->write($draft);
        $requests = [];

        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function (HttpExchangeRequest $request) use (&$requests): HttpExchangeResult {
                $requests[] = $request;

                if ($request->path() === '/sapi/auth/token') {
                    return $this->tokenExchange();
                }

                if ($request->path() === '/sapi/document/send') {
                    return $this->jsonExchange(202, ApiFixtures::sendDocument('provider-send-ubl-1'));
                }

                $this->fail('Unexpected HTTP request to: ' . $request->path());
            });

        $submission = new UblDraftSubmission(
            $writer,
            new SapiDocumentSubmissionGateway(new SendDocumentEndpoint($this->buildConnector($executor)))
        );
        $result = $submission->send(
            $this->participant(),
            Participant::parse(self::RECEIVER_ID),
            $draft,
            Timestamp::fromIso8601('2026-11-24T12:30:00Z'),
            IdempotencyKey::fromString('00000000-0000-4000-8000-000000000010')
        );

        $this->assertSame('provider-send-ubl-1', $result->providerDocumentId()->value());
        $this->assertCount(2, $requests);
        $this->assertSame(['/sapi/auth/token', '/sapi/document/send'], array_map(
            static function (HttpExchangeRequest $request): string {
                return $request->path();
            },
            $requests
        ));

        $sendRequest = $requests[1];
        $this->assertSame('/sapi/document/send', $sendRequest->path());
        $this->assertSame('POST', $sendRequest->httpMethod()->value());
        $this->assertFalse($sendRequest->isRetrySafe());

        $headers = [];

        foreach ($sendRequest->headers()->all() as $header) {
            $headers[$header->name()] = $header->value();
        }

        $this->assertSame('Bearer synthetic-access-token', $headers['authorization']);
        $this->assertSame('00000000-0000-4000-8000-000000000010', $headers['idempotency-key']);
        $this->assertSame(self::PARTICIPANT_ID, $headers['x-peppol-participant-id']);

        $body = json_decode((string) $sendRequest->body(), true);
        $this->assertIsArray($body);
        $this->assertSame('INV-UBL-API-1', $body['metadata']['documentId']);
        $this->assertSame(
            'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##'
            . 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0::2.1',
            $body['metadata']['documentTypeId']
        );
        $this->assertSame($draft->profileId()->value(), $body['metadata']['processId']);
        $this->assertSame(self::PARTICIPANT_ID, $body['metadata']['senderParticipantId']);
        $this->assertSame(self::RECEIVER_ID, $body['metadata']['receiverParticipantId']);
        $this->assertSame('2026-11-24T12:30:00Z', $body['metadata']['creationDateTime']);
        $this->assertSame($xml, $body['payload']);
        $this->assertStringContainsString('<cbc:ID>INV-UBL-API-1</cbc:ID>', $body['payload']);
        $this->assertSame('XML', $body['payloadFormat']);
        $this->assertSame('UTF-8', $body['payloadEncoding']);
        $this->assertSame(hash('sha256', $xml), $body['checksum']);
    }

    public function testAcceptedInvoiceCanBeTrackedUntilDelivery(): void
    {
        $providerDocumentId = 'provider-send-tracked-1';
        $processingStatuses = [
            'RECEIVED_BY_GATEWAY',
            'VALIDATING',
            'QUEUED',
            'SENDING_TO_ACCESS_POINT',
            'SENT_TO_ACCESS_POINT',
        ];
        $responses = [
            $this->tokenExchange(),
            $this->jsonExchange(202, ApiFixtures::sendDocument($providerDocumentId)),
        ];

        foreach ($processingStatuses as $status) {
            $responses[] = $this->jsonExchange(200, ApiFixtures::sentDocument($status, [
                'identifier' => $providerDocumentId,
            ]));
        }

        $responses[] = $this->jsonExchange(200, ['items' => []]);
        $responses[] = $this->jsonExchange(200, [
            'items' => [ApiFixtures::messageLevelStatus('delivery-forwarded-1', 'AB')],
        ]);
        $responses[] = $this->jsonExchange(200, [
            'items' => [ApiFixtures::messageLevelStatus('delivery-confirmed-1', 'AP')],
        ]);

        $connector  = $this->buildConnector($this->queuedExecutor($responses));
        $submission = (new SapiDocumentSubmissionGateway(new SendDocumentEndpoint($connector)))
            ->send(new SendDocumentRequest(
                $this->participant(),
                $this->testPayload(),
                IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001')
            ));
        $tracking = new PeppolGatewayDocumentTrackingProvider(
            new GetSentDocumentEndpoint($connector),
            new ListMessageLevelStatusesEndpoint($connector),
            new GetMessageLevelStatusContentEndpoint($connector)
        );
        $context   = new DocumentRequestContext('21', 'INV-SYNTHETIC-1');
        $reference = new SentDocumentReference($this->participant(), $submission->providerDocumentId(), $context);

        $this->assertSame($providerDocumentId, $submission->providerDocumentId()->value());
        $this->assertSame('ACCEPTED', $submission->status()->value());

        foreach ($processingStatuses as $expectedStatus) {
            $status = $tracking->getSentDocumentStatus($reference);
            $this->assertSame($expectedStatus, $status->status()->value());
            $this->assertNull($status->error());
        }

        $messageStatuses = $tracking->getMessageLevelStatuses($reference);
        $this->assertInstanceOf(ProviderMessageLevelStatusResponse::class, $messageStatuses);
        $this->assertCount(0, $messageStatuses->items());

        $messageStatuses = $tracking->getMessageLevelStatuses($reference);
        $this->assertTrue(MessageLevelStatusCode::isForwarded($messageStatuses->items()[0]->responseCode()->value()));
        $this->assertFalse(MessageLevelStatusCode::isSuccess($messageStatuses->items()[0]->responseCode()->value()));

        $messageStatuses = $tracking->getMessageLevelStatuses($reference);
        $this->assertTrue(MessageLevelStatusCode::isSuccess($messageStatuses->items()[0]->responseCode()->value()));
    }

    public function testRecipientRejectionIsReportedAsFailedDelivery(): void
    {
        $providerDocumentId = 'provider-send-rejected-1';
        $connector          = $this->buildConnector($this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(202, ApiFixtures::sendDocument($providerDocumentId)),
            $this->jsonExchange(200, ApiFixtures::sentDocument('SENT_TO_ACCESS_POINT', [
                'identifier' => $providerDocumentId,
            ])),
            $this->jsonExchange(200, [
                'items' => [ApiFixtures::messageLevelStatus('delivery-rejected-1', 'RE', [
                    'responseDescription' => 'Recipient rejected the synthetic invoice.',
                    'reasons'             => [[
                        'lineId'      => '1',
                        'code'        => 'BR-CO-10',
                        'description' => 'Recipient data did not pass validation.',
                    ]],
                ])],
            ]),
        ]));
        $submission = (new SapiDocumentSubmissionGateway(new SendDocumentEndpoint($connector)))
            ->send(new SendDocumentRequest(
                $this->participant(),
                $this->testPayload(),
                IdempotencyKey::fromString('00000000-0000-4000-8000-000000000002')
            ));
        $tracking = new PeppolGatewayDocumentTrackingProvider(
            new GetSentDocumentEndpoint($connector),
            new ListMessageLevelStatusesEndpoint($connector),
            new GetMessageLevelStatusContentEndpoint($connector)
        );
        $context   = new DocumentRequestContext('21', 'INV-SYNTHETIC-1');
        $reference = new SentDocumentReference($this->participant(), $submission->providerDocumentId(), $context);

        $documentStatus = $tracking->getSentDocumentStatus($reference);
        $this->assertSame('SENT_TO_ACCESS_POINT', $documentStatus->status()->value());

        $messageStatuses = $tracking->getMessageLevelStatuses($reference);
        $this->assertTrue($messageStatuses->isValid());
        $this->assertCount(1, $messageStatuses->items());
        $rejection = $messageStatuses->items()[0];
        $this->assertSame('RE', $rejection->responseCode()->value());
        $this->assertTrue(MessageLevelStatusCode::isFailure($rejection->responseCode()->value()));
        $this->assertFalse(MessageLevelStatusCode::isSuccess($rejection->responseCode()->value()));
        $this->assertSame('Recipient rejected the synthetic invoice.', $rejection->responseDescription());
        $this->assertSame('Recipient data did not pass validation.', $rejection->reasons()[0]->description());
    }

    // ── 6a Provider rejection ────────────────────────────────────────────────

    public function testProviderRejectionResponseIsReturnedAsResult(): void
    {
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(202, ApiFixtures::sendDocument('provider-send-2', 'REJECTED')),
        ]);

        $result = (new SapiDocumentSubmissionGateway(new SendDocumentEndpoint($this->buildConnector($executor))))
            ->send(new SendDocumentRequest(
                $this->participant(),
                $this->testPayload(),
                IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001')
            ));

        $this->assertSame('provider-send-2', $result->providerDocumentId()->value());
        $this->assertSame('REJECTED', $result->status()->value());
    }

    public function testProviderHttpErrorDuringDocumentSendThrowsTypedProviderError(): void
    {
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(422, ApiFixtures::error('Synthetic validation failure', [
                ['field' => 'metadata.documentId', 'issue' => 'Synthetic issue'],
            ])),
        ]);

        try {
            (new SapiDocumentSubmissionGateway(new SendDocumentEndpoint($this->buildConnector($executor))))
                ->send(new SendDocumentRequest(
                    $this->participant(),
                    $this->testPayload(),
                    IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001')
                ));
            $this->fail('Expected provider HTTP error.');
        } catch (ProviderErrorResponseException $exception) {
            $this->assertSame(422, $exception->httpStatusCode()->value());
            $this->assertStringContainsString('Synthetic validation failure', $exception->body());
            $this->assertStringContainsString('metadata.documentId', $exception->body());
        }
    }

    public function testMalformedSuccessfulSendResponseIsReportedAsInvalidProviderResponse(): void
    {
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(202, [
                'status'    => 'ACCEPTED',
                'timestamp' => ApiFixtures::TIMESTAMP,
            ]),
        ]);

        try {
            (new SapiDocumentSubmissionGateway(new SendDocumentEndpoint($this->buildConnector($executor))))
                ->send(new SendDocumentRequest(
                    $this->participant(),
                    $this->testPayload(),
                    IdempotencyKey::fromString('00000000-0000-4000-8000-000000000003')
                ));
            $this->fail('A successful response without the provider document ID must be rejected.');
        } catch (InvalidProviderResponseException $exception) {
            $this->assertSame('SapiSendDocumentResponse', $exception->schema());
            $this->assertSame('providerDocumentId', $exception->field());
            $this->assertSame(202, $exception->httpStatusCode()->value());
        }
    }

    // ── 6a Network failure + retry ───────────────────────────────────────────

    public function testNetworkFailureDuringSendIsNotRetried(): void
    {
        $delays  = [];
        $sleepFn = static function (int $ms) use (&$delays): void {
            $delays[] = $ms;
        };
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->curlFailure('Synthetic connection drop.'),
        ]);

        $retryingExecutor = new RetryingHttpRequestExecutor(
            $executor,
            RetryPolicy::default(),
            $sleepFn
        );

        try {
            (new SapiDocumentSubmissionGateway(new SendDocumentEndpoint($this->buildConnector($retryingExecutor))))
                ->send(new SendDocumentRequest(
                    $this->participant(),
                    $this->testPayload(),
                    IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001')
                ));
            $this->fail('An unknown submission outcome must not trigger another POST.');
        } catch (ProviderApiException $exception) {
            $this->assertSame('Synthetic connection drop.', $exception->getMessage());
        }

        $this->assertSame([], $delays);
    }

    /**
     * Verifies that documented server errors do not retry document submission.
     *
     * @param int $status HTTP status returned by the provider.
     * @dataProvider documentedServerErrorStatuses
     */
    public function testServerErrorDuringSendIsNotRetried(int $status): void
    {
        $delays  = [];
        $sleepFn = static function (int $ms) use (&$delays): void {
            $delays[] = $ms;
        };
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange($status, ApiFixtures::error('Synthetic temporary failure')),
        ]);

        $retryingExecutor = new RetryingHttpRequestExecutor(
            $executor,
            RetryPolicy::default(),
            $sleepFn
        );

        try {
            (new SapiDocumentSubmissionGateway(new SendDocumentEndpoint($this->buildConnector($retryingExecutor))))
                ->send(new SendDocumentRequest(
                    $this->participant(),
                    $this->testPayload(),
                    IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001')
                ));
            $this->fail('A server error with an unknown outcome must not trigger another POST.');
        } catch (ProviderApiException $exception) {
            $this->assertSame($status, $exception->httpStatusCode()->value());
        }

        $this->assertSame([], $delays);
    }

    public function testRetryableServerErrorDuringSendStillDoesNotRetrySubmission(): void
    {
        $delays                      = [];
        $error                       = ApiFixtures::error('Synthetic transient send failure.');
        $error['error']['retryable'] = true;
        $executor                    = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(503, $error),
        ]);
        $retryingExecutor = new RetryingHttpRequestExecutor(
            $executor,
            RetryPolicy::default(),
            static function (int $delayMs) use (&$delays): void {
                $delays[] = $delayMs;
            }
        );

        try {
            (new SapiDocumentSubmissionGateway(new SendDocumentEndpoint($this->buildConnector($retryingExecutor))))
                ->send(new SendDocumentRequest(
                    $this->participant(),
                    $this->testPayload(),
                    IdempotencyKey::fromString('00000000-0000-4000-8000-000000000004')
                ));
            $this->fail('A retryable provider response must not replay an uncertain document submission.');
        } catch (ProviderErrorResponseException $exception) {
            $this->assertSame(503, $exception->httpStatusCode()->value());
            $this->assertTrue($exception->errorEnvelope()->retryable());
        }

        $this->assertSame([], $delays);
    }

    /**
     * Provides server error statuses used to verify that submission is not retried.
     *
     * @return int[][] Rows containing one HTTP status code each.
     */
    public static function documentedServerErrorStatuses(): array
    {
        return [[500], [503]];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function testPayload(): OutboundDocumentPayload
    {
        return new OutboundDocumentPayload(
            SubmittedDocumentId::fromString('INV-SYNTHETIC-1'),
            PeppolDocumentTypeIdentifier::fromString('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0::2.1'),
            PeppolProcessIdentifier::fromString('urn:fdc:peppol.eu:2017:poacc:billing:03:1.0'),
            Participant::parse(self::PARTICIPANT_ID),
            Participant::parse(self::RECEIVER_ID),
            Timestamp::fromIso8601('2026-11-18T09:00:00Z'),
            '<Invoice>synthetic</Invoice>',
            Sha256Checksum::fromContent('<Invoice>synthetic</Invoice>')
        );
    }

    /** Builds the synthetic invoice draft serialized by the submission workflow test. */
    private function invoiceDraftForSubmission(): UblDocumentDraft
    {
        $taxCategory = new UblDraftTaxCategory(
            TaxCategoryCode::fromString(TaxCategoryCode::STANDARD),
            UblDecimal::fromString('23')
        );
        $taxAmount     = UblDecimal::fromString('23.00');
        $taxableAmount = UblDecimal::fromString('100.00');
        $totalAmount   = UblDecimal::fromString('123.00');
        $totals        = new UblDraftTotals(
            $taxableAmount,
            $taxableAmount,
            $totalAmount,
            null,
            $totalAmount,
            $taxAmount,
            [new UblDraftTaxSubtotal($taxableAmount, $taxAmount, $taxCategory)]
        );
        $line = new UblDraftLine(
            new UblDraftIdentifier('1'),
            UblDecimal::fromString('1'),
            UnitCode::fromCanonical('C62'),
            $taxableAmount,
            'Synthetic service',
            $taxCategory,
            $taxableAmount
        );

        return (new UblDocumentDraft(
            UblDocumentRoot::fromString(UblDocumentRoot::INVOICE),
            UblDocumentNumber::fromString('INV-UBL-API-1'),
            LocalDate::fromString('2026-11-24'),
            CurrencyCode::fromCanonical('EUR'),
            new UblDraftParty(
                UblEndpointIdentifier::fromSchemeAndValue('0245', '2012345676'),
                'Synthetic supplier',
                CountryCode::fromCanonical('SK')
            ),
            new UblDraftParty(
                UblEndpointIdentifier::fromSchemeAndValue('0245', '2012345699'),
                'Synthetic customer',
                CountryCode::fromCanonical('SK')
            ),
            $totals
        ))->withLines([$line]);
    }
}
