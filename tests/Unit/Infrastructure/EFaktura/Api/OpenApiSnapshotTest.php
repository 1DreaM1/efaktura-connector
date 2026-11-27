<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use PHPUnit\Framework\TestCase;

/** Checks that the OpenAPI snapshot contains published routes, schemas and security definitions only. */
class OpenApiSnapshotTest extends TestCase
{
    public function testSnapshotContainsThePublishedOperationsAndNoRetiredAdminRoutes(): void
    {
        $snapshot       = $this->snapshot();
        $operationCount = 0;

        foreach ($snapshot['paths'] as $path => $operations) {
            if (strpos($path, '/v1/admin/') === 0) {
                $this->fail('The combined snapshot must not include unpublished admin routes.');
            }

            foreach ($operations as $method => $operation) {
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace'], true)) {
                    $operationCount++;
                }
            }
        }

        $this->assertSame(28, $operationCount);
    }

    public function testSnapshotIncludesTheBatchSubmissionResponse(): void
    {
        $snapshot  = $this->snapshot();
        $responses = $snapshot['paths']['/v1/send-documents']['post']['responses'];
        $response  = $responses['200'];

        $this->assertEqualsCanonicalizing(['200', '400', '401', '403', '409', '413', '500'], array_keys($responses));
        $this->assertSame(
            '#/components/schemas/SendDocumentsResponse',
            $response['content']['application/json']['schema']['$ref']
        );
        $this->assertSame(
            ['count', 'identifiers'],
            $snapshot['components']['schemas']['SendDocumentsResponse']['required']
        );
    }

    public function testSnapshotReferencesOnlyDefinedSchemasAndSecuritySchemes(): void
    {
        $snapshot = $this->snapshot();
        $schemas  = $snapshot['components']['schemas'];

        $this->assertSchemaReferencesResolve($snapshot, $schemas);

        foreach ($snapshot['paths'] as $operations) {
            foreach ($operations as $operation) {
                if (!isset($operation['security'])) {
                    continue;
                }

                foreach ($operation['security'] as $securityRequirement) {
                    foreach (array_keys($securityRequirement) as $schemeName) {
                        $this->assertArrayHasKey($schemeName, $snapshot['components']['securitySchemes']);
                    }
                }
            }
        }
    }

    public function testSnapshotDefinesIntegratorSecuritySchemes(): void
    {
        $snapshot = $this->snapshot();
        $schemes  = $snapshot['components']['securitySchemes'];

        $this->assertSame('X-Integrator-Token', $schemes['integratorToken']['name']);
        $this->assertSame('X-PDS-Secret', $schemes['integratorSecretSignature']['name']);
        $this->assertArrayHasKey(
            'integratorSecretSignature',
            $snapshot['paths']['/v1/integrators/{integratorId}/pds/subjects']['post']['security'][0]
        );
    }

    public function testIntegratorErrorsUseTheirPublishedNamespacedSchemas(): void
    {
        $snapshot        = $this->snapshot();
        $integratorError = $snapshot['paths']['/v1/integrators/{integratorId}/clients']['get']
            ['responses']['400']['content']['application/json']['schema']['$ref'];
        $gatewayError = $snapshot['paths']['/v1/webhooks']['get']
            ['responses']['400']['content']['application/json']['schema']['$ref'];

        $this->assertSame('#/components/schemas/IntegratorSapiErrorResponseEnvelope', $integratorError);
        $this->assertSame('#/components/schemas/SapiErrorResponseEnvelope', $gatewayError);
        $this->assertSame(
            '#/components/schemas/IntegratorSapiError',
            $snapshot['components']['schemas']['IntegratorSapiErrorResponseEnvelope']['properties']['error']['$ref']
        );
    }

    /** @return array<string, mixed> Decoded OpenAPI document used by the snapshot assertions. */
    private function snapshot(): array
    {
        $path     = ResourcePath::join(dirname(__DIR__, 5), 'docs/eFaktura/api/openapi.json');
        $snapshot = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($snapshot);

        return $snapshot;
    }

    /**
     * Recursively verifies that local component-schema references exist.
     *
     * @param array<array-key, mixed> $value   Schema fragment to inspect.
     * @param array<string, mixed>    $schemas Component schemas indexed by name.
     */
    private function assertSchemaReferencesResolve(array $value, array $schemas): void
    {
        foreach ($value as $key => $child) {
            if ($key === '$ref' && is_string($child) && strpos($child, '#/components/schemas/') === 0) {
                $schemaName = substr($child, strlen('#/components/schemas/'));
                $this->assertArrayHasKey($schemaName, $schemas);

                continue;
            }

            if (is_array($child)) {
                $this->assertSchemaReferencesResolve($child, $schemas);
            }
        }
    }
}
