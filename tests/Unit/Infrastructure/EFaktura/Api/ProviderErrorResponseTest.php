<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use DreaM1\EfakturaConnector\Tests\Support\RecordingTransport;
use PHPUnit\Framework\TestCase;

/**
 * Failed calls preserve the provider's raw response and attach a typed envelope when one is recognized.
 */
class ProviderErrorResponseTest extends TestCase
{
    public function testFailedCallCarriesTheResponseUnchanged(): void
    {
        $json = (string) json_encode(ApiFixtures::error('client_secret=synthetic-value is shown as sent', [], 'AUTH'));

        $exception = $this->failure(new HttpExchangeResult(400, $json, ['content-type' => 'application/json'], 0.0));

        $this->assertSame(400, $exception->httpStatusCode()->value());
        $this->assertSame(['content-type' => 'application/json'], $exception->responseHeaders());
        $this->assertSame($json, $exception->body());
    }

    public function testBodyOutsideTheErrorEnvelopeStaysRaw(): void
    {
        $exception = $this->failure(new HttpExchangeResult(502, '<html>Bad gateway</html>', [], 0.0));

        $this->assertSame('<html>Bad gateway</html>', $exception->body());
    }

    public function testConnectorAttachesParsedEnvelopeAndRetainsOriginalBody(): void
    {
        $body = (string) json_encode(ApiFixtures::error('Synthetic validation failure.', [
            ['field' => 'invoice.total', 'issue' => 'Does not match line totals.'],
        ], 'VALIDATION'));
        $exception = $this->failure(new HttpExchangeResult(422, $body, [], 0.0));

        $error = $exception->errorEnvelope();

        $this->assertNotNull($error);
        $this->assertSame('VALIDATION', $error->category()->value());
        $this->assertSame('Synthetic validation failure.', $error->message());
        $this->assertCount(1, $error->details());
        $this->assertSame($error, $exception->errorEnvelope());
        $this->assertSame($body, $exception->body());
    }

    public function testUnrecognizedProviderErrorEnvelopeIsAbsentOnDirectException(): void
    {
        $exception = new ProviderErrorResponseException(502, '<html>Bad gateway</html>');

        $this->assertNull($exception->errorEnvelope());
        $this->assertNull($exception->errorEnvelope());
    }

    /**
     * Preserves non-success provider responses at each public connector entry point.
     *
     * @param string $entryPoint Connector method exercised by the test.
     * @dataProvider connectorEntryPointProvider
     */
    public function testEveryConnectorEntryPointPreservesNonSuccessfulResponses(string $entryPoint): void
    {
        $body    = '{malformed provider response';
        $headers = [
            'content-type'     => 'application/json',
            'x-correlation-id' => 'synthetic-correlation-id',
        ];
        $connector = new ApiConnector(RecordingTransport::returning(
            new HttpExchangeResult(302, $body, $headers, 0.0)
        ));
        $request = ApiRequest::get('/v1/test');

        try {
            switch ($entryPoint) {
                case 'json':
                    $connector->json($request, $this->mapper());
                    break;
                case 'xml':
                    $connector->xml($request->acceptXml());
                    break;
                case 'send':
                    $connector->send($request);
                    break;
                default:
                    $this->fail('Unknown connector entry point: ' . $entryPoint);
            }

            $this->fail('Expected the connector to report the non-successful status.');
        } catch (ProviderErrorResponseException $exception) {
            $this->assertSame(302, $exception->httpStatusCode()->value());
            $this->assertSame($headers, $exception->responseHeaders());
            $this->assertSame($body, $exception->body());
        }
    }

    /**
     * Provides connector entry points that must preserve provider error responses.
     *
     * @return array<array-key, array<array-key, mixed>> Entry point and expected result cases.
     */
    public function connectorEntryPointProvider(): array
    {
        return [
            'JSON response'       => ['json'],
            'XML response'        => ['xml'],
            'no-content response' => ['send'],
        ];
    }

    /** Returns the provider exception raised for the supplied failed exchange. */
    private function failure(HttpExchangeResult $exchange): ProviderErrorResponseException
    {
        try {
            (new ApiConnector(RecordingTransport::returning($exchange)))->json(
                ApiRequest::get('/v1/test'),
                $this->mapper()
            );
        } catch (ProviderErrorResponseException $exception) {
            return $exception;
        }

        $this->fail('Expected the connector to report the failed status.');
    }

    /** @return callable(array<array-key, mixed>): object Simple response mapper used by connector tests. */
    private function mapper(): callable
    {
        return static function (array $data): object {
            return (object) $data;
        };
    }
}
