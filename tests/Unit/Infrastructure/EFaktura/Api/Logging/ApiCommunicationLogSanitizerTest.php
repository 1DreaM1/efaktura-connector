<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Logging;

use DreaM1\EfakturaConnector\Core\ProviderErrors\ProviderErrorCategory;
use DreaM1\EfakturaConnector\Core\ProviderErrors\ProviderErrorEnvelope;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Logging\ApiCommunicationLogSanitizer;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Logging\ApiLogSanitizationPolicy;
use PHPUnit\Framework\TestCase;

/** Tests API communication log sanitization behavior. */
class ApiCommunicationLogSanitizerTest extends TestCase
{
    public function testRequestDataKeepsPayloadAndRedactsCredentials(): void
    {
        $payload   = '<Invoice>synthetic</Invoice>';
        $sanitizer = new ApiCommunicationLogSanitizer();
        $request   = $sanitizer->sanitizeRequestBody(json_encode([
            'metadata'     => ['documentId' => 'SYNTHETIC-1'],
            'payload'      => $payload,
            'access_token' => 'synthetic-access-token',
            'password'     => 'synthetic-password',
            'apiKey'       => 'synthetic-api-key',
            'credentials'  => ['client_secret' => 'synthetic-client-secret'],
        ]));

        $this->assertSame([
            'redacted' => true,
            'bytes'    => strlen($payload),
            'sha256'   => hash('sha256', $payload),
            'content'  => '<Invoice>[redacted]</Invoice>',
        ], $request['payload']);
        $this->assertArrayNotHasKey('payloadBytes', $request);
        $this->assertArrayNotHasKey('payloadSha256', $request);
        $this->assertSame('[redacted]', $request['access_token']);
        $this->assertSame('[redacted]', $request['password']);
        $this->assertSame('[redacted]', $request['apiKey']);
        $this->assertSame('[redacted]', $request['credentials']['client_secret']);
    }

    public function testRequestRedactionHandlesEscapedQuotesInsideEmbeddedCredentials(): void
    {
        $description = 'client_secret="prefix\"suffix"';
        $request     = (new ApiCommunicationLogSanitizer())->sanitizeRequestBody(json_encode([
            'description' => $description,
        ]));

        $this->assertSame('client_secret="[redacted]"', $request['description']);
        $this->assertStringNotContainsString('suffix', json_encode($request));
    }

    public function testSanitizationRedactsUnsafeDynamicFieldNames(): void
    {
        $personalDataKeys = ['customer@example.test', 'person@example.test'];
        $dynamicFields    = array_fill_keys($personalDataKeys, 'synthetic-value');
        $sanitizer        = new ApiCommunicationLogSanitizer();

        $request  = $sanitizer->sanitizeRequestBody(json_encode($dynamicFields));
        $response = $sanitizer->sanitizeResponseBody($dynamicFields);

        foreach ($personalDataKeys as $personalDataKey) {
            $this->assertArrayNotHasKey($personalDataKey, $request);
            $this->assertArrayNotHasKey($personalDataKey, $response);
            $this->assertStringNotContainsString($personalDataKey, json_encode($request));
            $this->assertStringNotContainsString($personalDataKey, json_encode($response));
        }

        $this->assertSame(['synthetic-value', 'synthetic-value'], array_values($request));
        $this->assertSame(['[redacted]', '[redacted]'], array_values($response));
    }

    public function testRequestHeadersRedactAuthorizationAndHashIdempotencyKey(): void
    {
        $idempotencyKey = 'synthetic-idempotency-key';
        $headers        = (new ApiCommunicationLogSanitizer())->sanitizeRequestHeaders([
            'Authorization: Bearer synthetic-access-token',
            'X-Peppol-Participant-Id: 0245:2012345676',
            'Idempotency-Key: ' . $idempotencyKey,
            'Accept: application/json',
            'X-Integrator-Token: synthetic-integrator-token',
        ]);

        $this->assertSame('Bearer [redacted]', $headers['authorization']);
        $this->assertSame('0245:2012345676', $headers['x-peppol-participant-id']);
        $this->assertSame('sha256:' . substr(hash('sha256', $idempotencyKey), 0, 16), $headers['idempotency-key']);
        $this->assertSame('application/json', $headers['accept']);
        $this->assertArrayNotHasKey('x-integrator-token', $headers);
    }

    public function testResponseDataRedactsPayloadsCredentialsAndBearerTokens(): void
    {
        $response = (new ApiCommunicationLogSanitizer())->sanitizeResponseBody([
            'access_token' => 'synthetic-access-token',
            'message'      => 'Provider token: synthetic-token; Bearer synthetic-bearer-token',
            'nested'       => ['private_key' => 'synthetic-private-key'],
            'payload'      => '<Invoice>synthetic</Invoice>',
            'content'      => '<ApplicationResponse>synthetic</ApplicationResponse>',
        ]);

        $this->assertSame('[redacted]', $response['access_token']);
        $this->assertSame('[redacted]', $response['message']);
        $this->assertSame('[redacted]', $response['nested']['private_key']);
        $this->assertSame([
            'redacted' => true,
            'bytes'    => strlen('<Invoice>synthetic</Invoice>'),
            'sha256'   => hash('sha256', '<Invoice>synthetic</Invoice>'),
            'content'  => '<Invoice>[redacted]</Invoice>',
        ], $response['payload']);
        $this->assertSame([
            'redacted' => true,
            'bytes'    => strlen('<ApplicationResponse>synthetic</ApplicationResponse>'),
            'sha256'   => hash('sha256', '<ApplicationResponse>synthetic</ApplicationResponse>'),
            'content'  => '<ApplicationResponse>[redacted]</ApplicationResponse>',
        ], $response['content']);
    }

    public function testResponseDataKeepsSafeOperationalFieldsAndRedactsUnknownProviderData(): void
    {
        $response = (new ApiCommunicationLogSanitizer())->sanitizeResponseBody([
            'status'            => 'ACCEPTED',
            'category'          => 'VALIDATION',
            'valid'             => true,
            'expires_in'        => 900,
            'senderName'        => 'Synthetic patient name',
            'patientIdentifier' => 'synthetic-person-id',
            'amount'            => 123.45,
            'details'           => [
                ['message' => 'Synthetic patient detail'],
            ],
        ]);

        $this->assertSame('ACCEPTED', $response['status']);
        $this->assertSame('VALIDATION', $response['category']);
        $this->assertTrue($response['valid']);
        $this->assertSame(900, $response['expires_in']);
        $this->assertSame('[redacted]', $response['senderName']);
        $this->assertSame('[redacted]', $response['patientIdentifier']);
        $this->assertSame('[redacted]', $response['amount']);
        $this->assertSame('[redacted]', $response['details'][0]['message']);
    }

    public function testResponseHeadersKeepAllSupportedRetryAfterDateFormats(): void
    {
        $sanitizer = new ApiCommunicationLogSanitizer();
        $dates     = [
            'Wed, 21 Oct 2015 07:28:00 GMT',
            'Wednesday, 21-Oct-15 07:28:00 GMT',
            'Wed Oct 21 07:28:00 2015',
        ];

        foreach ($dates as $date) {
            $this->assertSame(
                ['retry-after' => $date],
                $sanitizer->sanitizeResponseHeaders(['retry-after' => $date])
            );
        }
    }

    public function testResponseHeadersRedactInvalidRetryAfterValues(): void
    {
        $sanitizer = new ApiCommunicationLogSanitizer();

        $this->assertSame(
            ['retry-after' => '[redacted]'],
            $sanitizer->sanitizeResponseHeaders(['retry-after' => 'tomorrow'])
        );
        $this->assertSame(
            ['retry-after' => '[redacted]'],
            $sanitizer->sanitizeResponseHeaders(['retry-after' => "\r\nWed, 21 Oct 2015 07:28:00 GMT"])
        );
    }

    public function testResponseDataKeepsValidatedMessageStatusMetadata(): void
    {
        $response = [
            'items' => [[
                'identifier'            => 'mls-log-synthetic-1',
                'messageId'             => 'peppol-message-log-synthetic-1',
                'responseCode'          => 'AP',
                'responseDescription'   => 'Accepted',
                'senderParticipantId'   => '0245:2012345676',
                'receiverParticipantId' => '0245:2012345687',
                'documentTypeId'        => 'synthetic-document-type',
                'receivedAt'            => '2026-11-12T10:30:00+00:00',
            ]],
        ];

        $this->assertSame($response, (new ApiCommunicationLogSanitizer())->sanitizeResponseBody($response));
    }

    public function testResponseDataDoesNotApplyMaximumLengthLimitsToProviderFields(): void
    {
        $response = [
            'identifier'          => str_repeat('i', 300),
            'senderParticipantId' => '0245:' . str_repeat('s', 150),
            'documentTypeId'      => str_repeat('D', 300),
            'responseCode'        => str_repeat('A', 100),
            'responseDescription' => str_repeat('D', 200),
            'status'              => str_repeat('S', 100),
            'retry-after'         => str_repeat('9', 20),
        ];

        $this->assertSame($response, (new ApiCommunicationLogSanitizer())->sanitizeResponseBody($response));
    }

    public function testResponseDataRedactsInvalidReceivedAtTimestamp(): void
    {
        $response = (new ApiCommunicationLogSanitizer())->sanitizeResponseBody([
            'receivedAt' => '2026-13-40T25:99:99+99:99',
        ]);

        $this->assertSame('[redacted]', $response['receivedAt']);
    }

    public function testSanitizationStopsAtTheConfiguredNestingDepth(): void
    {
        $nested = ['status' => 'ACCEPTED'];
        for ($depth = 0; $depth < 20; $depth++) {
            $nested = ['nested' => $nested];
        }

        $response = (new ApiCommunicationLogSanitizer())->sanitizeResponseBody($nested);
        for ($depth = 0; $depth < 16; $depth++) {
            $response = $response['nested'];
        }

        $this->assertSame('[truncated]', $response['nested']);
    }

    public function testTruncationMarkerDoesNotExceedTheMaximumArrayItemCount(): void
    {
        $sanitizer = new ApiCommunicationLogSanitizer();
        $list      = $sanitizer->sanitizeResponseBody(array_fill(
            0,
            ApiLogSanitizationPolicy::MAX_ARRAY_ITEMS + 1,
            'synthetic-value'
        ));

        $this->assertCount(ApiLogSanitizationPolicy::MAX_ARRAY_ITEMS, $list);
        $this->assertSame('[redacted]', $list[0]);
        $this->assertSame(ApiLogSanitizationPolicy::TRUNCATED_VALUE, $list[ApiLogSanitizationPolicy::MAX_ARRAY_ITEMS - 1]);

        $associative = [ApiLogSanitizationPolicy::TRUNCATION_MARKER_KEY => 'synthetic-value'];
        for ($index = 0; $index < ApiLogSanitizationPolicy::MAX_ARRAY_ITEMS; $index++) {
            $associative['field-' . $index] = 'synthetic-value';
        }

        $sanitizedAssociative = $sanitizer->sanitizeResponseBody($associative);

        $this->assertCount(ApiLogSanitizationPolicy::MAX_ARRAY_ITEMS, $sanitizedAssociative);
        $this->assertSame('[redacted]', $sanitizedAssociative[ApiLogSanitizationPolicy::TRUNCATION_MARKER_KEY]);
        $this->assertSame(
            ApiLogSanitizationPolicy::TRUNCATED_VALUE,
            $sanitizedAssociative[ApiLogSanitizationPolicy::TRUNCATION_MARKER_KEY . '_']
        );
    }

    public function testPayloadLogPreservesUblShapeAndRedactsSensitiveValues(): void
    {
        $cbcNamespace = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
        $payload      = '<Invoice xmlns:cbc="' . $cbcNamespace . '">'
            . '<cbc:ID>INV-SYNTHETIC-1</cbc:ID>'
            . '<cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>'
            . '<cbc:PayableAmount currencyID="EUR">123.45</cbc:PayableAmount>'
            . '<cbc:Note>Synthetic patient note</cbc:Note>'
            . '</Invoice>';

        $loggedPayload = (new ApiCommunicationLogSanitizer())->sanitizeRequestBody(json_encode([
            'payload' => $payload,
        ]))['payload'];

        $this->assertSame([
            'redacted' => true,
            'bytes'    => strlen($payload),
            'sha256'   => hash('sha256', $payload),
            'content'  => '<Invoice xmlns:cbc="' . $cbcNamespace . '">'
                . '<cbc:ID>[redacted]</cbc:ID>'
                . '<cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>'
                . '<cbc:PayableAmount currencyID="EUR">123.45</cbc:PayableAmount>'
                . '<cbc:Note>[redacted]</cbc:Note>'
                . '</Invoice>',
        ], $loggedPayload);
        $this->assertStringNotContainsString('INV-SYNTHETIC-1', json_encode($loggedPayload));
        $this->assertStringNotContainsString('Synthetic patient note', json_encode($loggedPayload));
    }

    public function testPayloadRedactorDoesNotTrustSafeLocalNamesInForeignNamespaces(): void
    {
        $payload = '<Invoice xmlns:ext="urn:synthetic:extension">'
            . '<ext:PayableAmount currencyID="Synthetic patient identifier">'
            . 'Synthetic patient detail'
            . '</ext:PayableAmount></Invoice>';

        $loggedPayload = (new ApiCommunicationLogSanitizer())->sanitizeRequestBody(json_encode([
            'payload' => $payload,
        ]))['payload'];

        $this->assertStringNotContainsString('Synthetic patient identifier', json_encode($loggedPayload));
        $this->assertStringNotContainsString('Synthetic patient detail', json_encode($loggedPayload));
        $this->assertStringContainsString(
            '<ext:PayableAmount currencyID="[redacted]">[redacted]</ext:PayableAmount>',
            $loggedPayload['content']
        );
    }

    public function testMalformedRequestBodyIsKeptInTheApiLog(): void
    {
        $body = '{invalid-json';

        $this->assertSame(
            [
                'unparsed'      => true,
                'payload'       => '[redacted]',
                'bytes'         => strlen($body),
                'payloadSha256' => hash('sha256', $body),
            ],
            (new ApiCommunicationLogSanitizer())->sanitizeRequestBody($body)
        );
    }

    public function testProviderErrorsAreSanitizedThroughTypedErrorDetails(): void
    {
        $error = ProviderErrorEnvelope::fromArray([
            'error' => [
                'category' => ProviderErrorCategory::VALIDATION,
                'code'     => 'INVALID_DOCUMENT',
                'message'  => 'Synthetic customer name',
                'details'  => [[
                    'field' => '/Invoice/ID',
                    'issue' => '[BR-CO-25] Synthetic invoice identifier',
                    'value' => 'BR-CO-25',
                ]],
                'retryable'      => false,
                'correlation_id' => 'provider-call-1',
            ],
        ]);

        if ($error === null) {
            $this->fail('Expected a provider error body.');
        }

        $this->assertSame([
            'category' => ProviderErrorCategory::VALIDATION,
            'code'     => 'INVALID_DOCUMENT',
            'message'  => '[redacted]',
            'details'  => [[
                'field' => '/Invoice/ID',
                'issue' => '[redacted]',
                'value' => 'BR-CO-25',
            ]],
            'retryable'      => false,
            'correlation_id' => 'provider-call-1',
        ], (new ApiCommunicationLogSanitizer())->sanitizeProviderError($error));
    }

    public function testUnknownProviderErrorCategoryIsOmittedFromLogs(): void
    {
        $error = ProviderErrorEnvelope::fromArray([
            'error' => [
                'category' => 'FUTURE_PROVIDER_CATEGORY',
                'message'  => 'Synthetic provider failure',
            ],
        ]);

        if ($error === null) {
            $this->fail('Expected a provider error body.');
        }

        $this->assertSame('FUTURE_PROVIDER_CATEGORY', $error->category()->value());
        $this->assertSame([
            'message' => '[redacted]',
        ], (new ApiCommunicationLogSanitizer())->sanitizeProviderError($error));
    }

    public function testProviderErrorIdentifiersRemainBounded(): void
    {
        $error = ProviderErrorEnvelope::fromArray([
            'error' => [
                'code'           => str_repeat('A', 129),
                'message'        => 'Synthetic provider failure',
                'correlation_id' => str_repeat('i', 129),
            ],
        ]);

        if ($error === null) {
            $this->fail('Expected a provider error body.');
        }

        $this->assertSame(
            ['message' => '[redacted]'],
            (new ApiCommunicationLogSanitizer())->sanitizeProviderError($error)
        );
    }

    public function testProviderErrorDetailsReportWhenTheirLogListIsTruncated(): void
    {
        $details = [];
        for ($index = 0; $index <= ApiLogSanitizationPolicy::MAX_ARRAY_ITEMS; $index++) {
            $details[] = ['field' => '/Invoice/ID', 'issue' => 'Synthetic provider detail'];
        }

        $error = ProviderErrorEnvelope::fromArray([
            'error' => [
                'message' => 'Synthetic provider failure',
                'details' => $details,
            ],
        ]);

        if ($error === null) {
            $this->fail('Expected a provider error body.');
        }

        $sanitized = (new ApiCommunicationLogSanitizer())->sanitizeProviderError($error);

        $this->assertCount(ApiLogSanitizationPolicy::MAX_ARRAY_ITEMS, $sanitized['details']);
        $this->assertTrue($sanitized['details_truncated']);
    }
}
