<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\Configuration\HttpRequestBodyLimit;
use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use DreaM1\EfakturaConnector\Core\Documents\OutboundGatewayDocument;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentsRequest;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestBodyTooLargeException;
use DreaM1\EfakturaConnector\Core\Xml\XmlPayloadFormat;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\SendDocumentsEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization\ApiJsonEncoder;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization\SendDocumentsRequestMapper;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use DreaM1\EfakturaConnector\Tests\Support\RecordingTransport;
use PHPUnit\Framework\TestCase;

/** Tests the Peppol Gateway batch submission request and response mapping. */
final class SendDocumentsEndpointTest extends TestCase
{
    private const SENDER_ID       = '0245:2012345676';
    private const RECEIVER_ID     = '0245:2012345687';
    private const IDEMPOTENCY_KEY = '00000000-0000-4000-8000-000000000001';

    public function testSubmitsBatchWithGatewayFieldsAndDisablesAutomaticRetry(): void
    {
        $transport = RecordingTransport::returning(ApiFixtures::jsonExchange([
            'count'       => 2,
            'identifiers' => ['gateway-1', 'gateway-2'],
        ]));
        $endpoint = new SendDocumentsEndpoint(new ApiConnector($transport));

        $response = $endpoint->send($this->request());

        $this->assertSame(2, $response->count());
        $this->assertSame(['gateway-1', 'gateway-2'], array_map(
            static function (ProviderDocumentId $identifier): string {
                return $identifier->value();
            },
            $response->identifiers()
        ));

        $request = $transport->lastRequest();
        $this->assertSame('POST', $request->httpMethod()->value());
        $this->assertSame('/v1/send-documents', $request->path());
        $this->assertFalse($request->isRetrySafe());
        $this->assertSame([
            'Idempotency-Key: ' . self::IDEMPOTENCY_KEY,
            'X-Peppol-Participant-Id: ' . self::SENDER_ID,
        ], array_map(
            static function (HttpHeaderLine $header): string {
                return $header->toString();
            },
            $request->headers()->all()
        ));
        $this->assertSame([
            'documents' => [
                [
                    'type'                  => XmlPayloadFormat::UBL_XML,
                    'payload'               => '<Invoice/>',
                    'receiverParticipantId' => self::RECEIVER_ID,
                ],
                [
                    'type'    => XmlPayloadFormat::SBDH_XML,
                    'payload' => '<StandardBusinessDocument/>',
                ],
            ],
        ], json_decode((string) $request->body(), true));
    }

    public function testRejectsNegativeAcceptedDocumentCountAsInvalidProviderResponse(): void
    {
        $transport = RecordingTransport::returning(ApiFixtures::jsonExchange([
            'count'       => -1,
            'identifiers' => [],
        ]));
        $endpoint = new SendDocumentsEndpoint(new ApiConnector($transport));

        try {
            $endpoint->send($this->request());
            $this->fail('A negative accepted-document count must be rejected.');
        } catch (InvalidProviderResponseException $exception) {
            $this->assertSame('SendDocumentsResponse', $exception->schema());
            $this->assertSame('count', $exception->field());
        }
    }

    public function testRejectsAcceptedCountThatDiffersFromReturnedIdentifiers(): void
    {
        $transport = RecordingTransport::returning(ApiFixtures::jsonExchange([
            'count'       => 2,
            'identifiers' => ['gateway-1'],
        ]));
        $endpoint = new SendDocumentsEndpoint(new ApiConnector($transport));

        try {
            $endpoint->send($this->request());
            $this->fail('Accepted count must match the number of returned identifiers.');
        } catch (InvalidProviderResponseException $exception) {
            $this->assertSame('SendDocumentsResponse', $exception->schema());
            $this->assertSame('count', $exception->field());
        }
    }

    public function testRejectsAcceptedCountGreaterThanSubmittedBatch(): void
    {
        $transport = RecordingTransport::returning(ApiFixtures::jsonExchange([
            'count'       => 3,
            'identifiers' => ['gateway-1', 'gateway-2', 'gateway-3'],
        ]));
        $endpoint = new SendDocumentsEndpoint(new ApiConnector($transport));

        try {
            $endpoint->send($this->request());
            $this->fail('The Gateway cannot accept more documents than were submitted.');
        } catch (InvalidProviderResponseException $exception) {
            $this->assertSame('SendDocumentsResponse', $exception->schema());
            $this->assertSame('count', $exception->field());
        }
    }

    public function testAllowsBodyExactlyAtConfiguredSerializedSizeLimit(): void
    {
        $request      = $this->request();
        $expectedBody = (new ApiJsonEncoder())->encode(SendDocumentsRequestMapper::fromRequest($request));
        $transport    = RecordingTransport::returning(ApiFixtures::jsonExchange([
            'count'       => 2,
            'identifiers' => ['gateway-1', 'gateway-2'],
        ]));
        $endpoint = new SendDocumentsEndpoint(
            new ApiConnector($transport),
            HttpRequestBodyLimit::fromBytes(strlen($expectedBody))
        );

        $endpoint->send($request);

        $this->assertSame($expectedBody, (string) $transport->lastRequest()->body());
    }

    public function testRejectsOversizedSerializedBatchBeforeSendingRequest(): void
    {
        $request      = $this->request();
        $expectedBody = (new ApiJsonEncoder())->encode(SendDocumentsRequestMapper::fromRequest($request));
        $transport    = RecordingTransport::returning(ApiFixtures::jsonExchange([
            'count'       => 2,
            'identifiers' => ['gateway-1', 'gateway-2'],
        ]));
        $maxBytes = strlen($expectedBody) - 1;
        $endpoint = new SendDocumentsEndpoint(
            new ApiConnector($transport),
            HttpRequestBodyLimit::fromBytes($maxBytes)
        );

        try {
            $endpoint->send($request);
            $this->fail('A batch larger than the configured body limit must not be sent.');
        } catch (RequestBodyTooLargeException $exception) {
            $this->assertSame($maxBytes, $exception->maxBytes());
        }

        $this->assertSame([], $transport->requests());
    }

    private function request(): SendDocumentsRequest
    {
        return new SendDocumentsRequest(
            Participant::parse(self::SENDER_ID),
            [
                new OutboundGatewayDocument(
                    XmlPayloadFormat::UBL_XML,
                    '<Invoice/>',
                    Participant::parse(self::RECEIVER_ID)
                ),
                new OutboundGatewayDocument(XmlPayloadFormat::SBDH_XML, '<StandardBusinessDocument/>'),
            ],
            IdempotencyKey::fromString(self::IDEMPOTENCY_KEY)
        );
    }
}
