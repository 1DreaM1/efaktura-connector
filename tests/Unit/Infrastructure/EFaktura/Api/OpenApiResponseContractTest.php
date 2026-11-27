<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use DreaM1\EfakturaConnector\Tests\Support\OpenApiContract;
use PHPUnit\Framework\TestCase;

/** Checks supported SAPI and Peppol Gateway response fixtures against the published OpenAPI schemas. */
class OpenApiResponseContractTest extends TestCase
{
    public function testSupportedProviderResponsesMatchTheirOpenApiSchemas(): void
    {
        $contract  = new OpenApiContract();
        $responses = [
            ['POST', '/sapi/auth/token', 200, ApiFixtures::token(), 'application/json'],
            ['GET', '/sapi/auth/token/status', 200, ApiFixtures::tokenStatus(), 'application/json'],
            ['POST', '/sapi/document/send', 202, ApiFixtures::sendDocument('provider-doc-1'), 'application/json'],
            [
                'GET',
                '/sapi/document/receive',
                200,
                ApiFixtures::receivedDocumentList(['provider-doc-1']),
                'application/json',
            ],
            [
                'GET',
                '/sapi/document/receive/provider-doc-1',
                200,
                ApiFixtures::receivedDocumentDetail('<Invoice/>'),
                'application/json',
            ],
            [
                'POST',
                '/sapi/document/receive/provider-doc-1/acknowledge',
                200,
                ApiFixtures::acknowledge(),
                'application/json',
            ],
            ['POST', '/v1/discovery/receives', 200, ApiFixtures::discovery('0245:receiver'), 'application/json'],
            [
                'GET',
                '/v1/participants/0088%3Asynthetic-id',
                200,
                ApiFixtures::participant('0088:synthetic-id', true, [ApiFixtures::capability()]),
                'application/json',
            ],
            ['POST', '/v1/validate-document', 200, ['valid' => true], 'application/json'],
            ['GET', '/v1/sent-documents/provider-doc-1', 200, ApiFixtures::sentDocument(), 'application/json'],
            [
                'GET',
                '/v1/sent-documents/provider-doc-1/message-level-statuses',
                200,
                ['items' => [ApiFixtures::messageLevelStatus()]],
                'application/json',
            ],
            [
                'GET',
                '/v1/sent-documents/provider-doc-1/message-level-statuses/status-1/content',
                200,
                '<ApplicationResponse/>',
                'application/xml',
            ],
            ['GET', '/v1/webhooks', 200, ApiFixtures::webhookList([ApiFixtures::webhook()]), 'application/json'],
            ['POST', '/v1/webhooks', 201, ApiFixtures::webhookCreate(), 'application/json'],
            ['GET', '/v1/webhooks/webhook-1', 200, ApiFixtures::webhook(), 'application/json'],
            ['PATCH', '/v1/webhooks/webhook-1', 200, ApiFixtures::webhook(), 'application/json'],
            ['DELETE', '/v1/webhooks/webhook-1', 204, null, 'application/json'],
            [
                'POST',
                '/sapi/document/send',
                400,
                ApiFixtures::error('Synthetic provider validation error.'),
                'application/json',
            ],
        ];

        foreach ($responses as [$method, $path, $status, $body, $mediaType]) {
            $errors = $contract->validateResponse($path, strtolower($method), $status, $body, $mediaType);

            $this->assertSame([], $errors, sprintf(
                '%s %s (%d) must match OpenAPI: %s',
                $method,
                $path,
                $status,
                implode('; ', $errors)
            ));
        }
    }

    public function testRejectsAnInvalidResponseFixtureAgainstTheOpenApiSchema(): void
    {
        $errors = (new OpenApiContract())->validateResponse(
            '/sapi/auth/token/status',
            'get',
            200,
            ['valid' => 'yes']
        );

        $this->assertNotSame([], $errors);
        $this->assertStringContainsString('JSON type boolean', implode('; ', $errors));
    }
}
