<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Validation\Schema;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\InvalidXmlException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\SchemaValidationException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\UnsupportedDocumentTypeException;
use DreaM1\EfakturaConnector\Core\Ubl\UblVersion;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schema\UblSchemaValidator;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Tests\Support\ReflectionAccess;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** Tests local UBL schema validation behavior. */
class UblSchemaValidatorTest extends TestCase
{
    public function testReportsStructuredXsdErrorsAndRestoresLibxmlErrorMode(): void
    {
        $document  = $this->document('<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" />');
        $validator = new UblSchemaValidator();
        $this->assertSame(['ubl_schema' => '2.1'], $validator->ruleVersions());
        $previous = libxml_use_internal_errors(false);

        try {
            try {
                $validator->validateDocument($document);
                $this->fail('Expected an incomplete UBL invoice to fail schema validation.');
            } catch (SchemaValidationException $exception) {
                $issue = $exception->issues()[0]->toArray();
                $this->assertSame('2.1', $exception->version());
                $this->assertStringContainsString('Missing child element', $issue['message']);
                $this->assertGreaterThanOrEqual(0, $issue['line']);
                $this->assertGreaterThanOrEqual(0, $issue['column']);
            }

            $this->assertFalse(libxml_use_internal_errors());
        } finally {
            libxml_use_internal_errors($previous);
        }
    }

    public function testUblXmlDocumentRejectsUnsupportedRootsBeforeAValidatorCanResolveResources(): void
    {
        $this->expectException(UnsupportedDocumentTypeException::class);

        $this->document('<Order />');
    }

    public function testUblXmlDocumentRejectsAnInvoiceWithAnUnsupportedNamespace(): void
    {
        $this->expectException(UnsupportedDocumentTypeException::class);

        $this->document('<Invoice xmlns="urn:example:invoice" />');
    }

    public function testRecognizesCreditNoteNamespaceAndSelectsItsSchema(): void
    {
        $document = $this->document(
            '<CreditNote xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2" />'
        );
        $validator = new UblSchemaValidator();

        try {
            $validator->validateDocument($document);
            $this->fail('Expected the incomplete CreditNote to fail schema validation.');
        } catch (SchemaValidationException $exception) {
            $this->assertSame(UblVersion::VERSION, $exception->version());
            $this->assertNotEmpty($exception->issues());
            $this->assertStringContainsString(
                'Missing child element',
                $exception->issues()[0]->toArray()['message']
            );
        }
    }

    public function testRejectsDocumentWithoutRootElementAtTheFactoryBoundary(): void
    {
        $this->expectException(InvalidXmlException::class);
        UblXmlDocument::fromXml('');
    }

    public function testReportsMissingUblSchemaResources(): void
    {
        $document = $this->document(
            '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" />'
        );
        $resourceDirectory = ResourcePath::join(sys_get_temp_dir(), 'efaktura-missing-xsd-' . uniqid());

        $this->expectException(LocalValidationInfrastructureException::class);
        $this->expectExceptionMessage('XSD schema is unavailable');

        (new UblSchemaValidator($resourceDirectory))->validateDocument($document);
    }

    public function testIgnoresBlankMessagesFromLibxmlErrors(): void
    {
        $blank        = (object) ['message' => " \t", 'line' => 1, 'column' => 2];
        $error        = (object) ['message' => 'Synthetic schema failure', 'line' => 3, 'column' => 4];
        $issuesMethod = new ReflectionMethod(UblSchemaValidator::class, 'limitedIssues');
        ReflectionAccess::makeAccessible($issuesMethod);

        $limited = $issuesMethod->invoke(null, [$blank, $error], 10);
        $issues  = $limited->issues();

        $this->assertCount(1, $issues);
        $this->assertSame([
            'line'     => 3,
            'column'   => 4,
            'message'  => 'Synthetic schema failure',
            'severity' => 'error',
        ], $issues[0]->toArray());
    }

    public function testNormalizesWhitespaceInValidationMessages(): void
    {
        $error        = (object) ['message' => "  Synthetic\n schema\t failure  ", 'line' => 3, 'column' => 4];
        $issuesMethod = new ReflectionMethod(UblSchemaValidator::class, 'limitedIssues');
        ReflectionAccess::makeAccessible($issuesMethod);

        $issues = $issuesMethod->invoke(null, [$error], 10)->issues();

        $this->assertSame('Synthetic schema failure', $issues[0]->toArray()['message']);
    }

    private function document(string $xml): UblXmlDocument
    {
        return UblXmlDocument::fromXml($xml);
    }
}
