<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\CheckReceivingCapabilityEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\DeleteWebhookEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetMessageLevelStatusContentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetParticipantProfileEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetSentDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetWebhookEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\ListMessageLevelStatusesEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\ListWebhooksEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\RegisterWebhookEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\SendDocumentsEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\UpdateWebhookEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\ValidateDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\AcknowledgeReceivedDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\GetReceivedDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\GetTokenStatusEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\ListReceivedDocumentsEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\RequestTokenEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\SendDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use DreaM1\EfakturaConnector\Tests\Support\OpenApiContract;
use PHPUnit\Framework\TestCase;

/** Checks successful response fixtures against the published OpenAPI schemas and status-specific fields. */
class OpenApiApiContractTest extends TestCase
{
    public function testSuccessfulApiResponseFixturesMatchTheirPublishedOpenApiSchemas(): void
    {
        $contract = new OpenApiContract();
        $cases    = [
            RequestTokenEndpoint::class                => ['/sapi/auth/token', 'post', 200, ApiFixtures::token()],
            GetTokenStatusEndpoint::class              => ['/sapi/auth/token/status', 'get', 200, ApiFixtures::tokenStatus()],
            SendDocumentEndpoint::class                => ['/sapi/document/send', 'post', 202, ApiFixtures::sendDocument('provider-1')],
            ListReceivedDocumentsEndpoint::class       => ['/sapi/document/receive', 'get', 200, ApiFixtures::receivedDocumentList(['document-1'], 'next')],
            GetReceivedDocumentEndpoint::class         => ['/sapi/document/receive/{documentId}', 'get', 200, ApiFixtures::receivedDocumentDetail('<Invoice/>')],
            AcknowledgeReceivedDocumentEndpoint::class => ['/sapi/document/receive/{documentId}/acknowledge', 'post', 200, ApiFixtures::acknowledge()],
            ValidateDocumentEndpoint::class            => ['/v1/validate-document', 'post', 200, ['valid' => true]],
            CheckReceivingCapabilityEndpoint::class    => ['/v1/discovery/receives', 'post', 200, ApiFixtures::discovery('0245:receiver')],
            GetParticipantProfileEndpoint::class       => ['/v1/participants/{participantId}', 'get', 200, ApiFixtures::participant('0245:receiver', true, [ApiFixtures::capability()])],
            GetSentDocumentEndpoint::class             => ['/v1/sent-documents/{identifier}', 'get', 200, ApiFixtures::sentDocument('DELIVERY_FAILED', [
                'error' => [
                    'message' => 'Synthetic delivery failure',
                    'details' => [['field' => 'receiver', 'issue' => 'offline', 'value' => '0245:2012345699']],
                ],
            ])],
            SendDocumentsEndpoint::class => ['/v1/send-documents', 'post', 200, [
                'count'       => 1,
                'identifiers' => ['provider-1'],
            ]],
            ListMessageLevelStatusesEndpoint::class => ['/v1/sent-documents/{identifier}/message-level-statuses', 'get', 200, [
                'items' => [ApiFixtures::messageLevelStatus()],
            ]],
            GetMessageLevelStatusContentEndpoint::class => ['/v1/sent-documents/{identifier}/message-level-statuses/{statusIdentifier}/content', 'get', 200, '<ApplicationResponse/>', 'application/xml'],
            ListWebhooksEndpoint::class                 => ['/v1/webhooks', 'get', 200, ApiFixtures::webhookList([ApiFixtures::webhook()], 'next')],
            RegisterWebhookEndpoint::class              => ['/v1/webhooks', 'post', 201, ApiFixtures::webhookCreate()],
            GetWebhookEndpoint::class                   => ['/v1/webhooks/{webhookId}', 'get', 200, ApiFixtures::webhook()],
            UpdateWebhookEndpoint::class                => ['/v1/webhooks/{webhookId}', 'patch', 200, ApiFixtures::webhook()],
            DeleteWebhookEndpoint::class                => ['/v1/webhooks/{webhookId}', 'delete', 204, null],
        ];

        $endpointDirectory    = ResourcePath::join(dirname(__DIR__, 5), 'src/Infrastructure/EFaktura/Api/Endpoints');
        $implementedEndpoints = array_merge(
            glob(ResourcePath::join($endpointDirectory, 'Sapi/*Endpoint.php')) ?: [],
            glob(ResourcePath::join($endpointDirectory, 'PeppolGateway/*Endpoint.php')) ?: []
        );
        $implementedEndpointClasses = [];

        foreach ($implementedEndpoints as $file) {
            $implementedEndpointClasses[] = 'DreaM1\\EfakturaConnector\\Infrastructure\\EFaktura\\Api\\Endpoints\\'
                . basename(dirname($file)) . '\\' . basename($file, '.php');
        }

        $this->assertEqualsCanonicalizing($implementedEndpointClasses, array_keys($cases));

        foreach ($cases as $endpointClass => $case) {
            $this->assertTrue(class_exists($endpointClass), $endpointClass . ' must be an implemented endpoint.');
            $path      = $case[0];
            $method    = $case[1];
            $status    = $case[2];
            $value     = $case[3];
            $mediaType = $case[4] ?? 'application/json';
            $errors    = $contract->validateResponse($path, $method, $status, $value, $mediaType);

            $this->assertSame([], $errors, sprintf('%s %s (%d): %s', strtoupper($method), $path, $status, implode('; ', $errors)));
        }

        $errorResponseErrors = $contract->validateResponse(
            '/v1/webhooks',
            'get',
            400,
            ApiFixtures::error('Synthetic invalid request'),
            'application/json'
        );
        $this->assertSame([], $errorResponseErrors, implode('; ', $errorResponseErrors));
    }

    public function testSendDocumentOpenApiSchemaRequiresReceivedAtOnlyForAcceptedStatus(): void
    {
        $contract = new OpenApiContract();
        $schema   = $contract->document()['components']['schemas']['SapiSendDocumentResponse'];
        $accepted = ApiFixtures::sendDocument('provider-1');
        $rejected = ApiFixtures::sendDocument('provider-2', 'REJECTED');
        unset($accepted['receivedAt']);

        $this->assertNotSame([], $contract->validate($accepted, $schema));
        $this->assertSame([], $contract->validate($rejected, $schema));
    }
}
