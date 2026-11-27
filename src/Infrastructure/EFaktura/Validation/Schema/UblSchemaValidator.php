<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schema;

use DreaM1\EfakturaConnector\Core\Contracts\UblSchemaValidationInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationProvenanceProviderInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\SchemaValidationException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\UnsupportedDocumentTypeException;
use DreaM1\EfakturaConnector\Core\Ubl\UblVersion;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\LimitedValidationIssues;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblSchemaValidationIssue;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationProvenance;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationIssueLimiter;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationMessageNormalizer;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationResources;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\UblDocumentType;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Xml\LibxmlErrorScope;
use LibXMLError;

/**
 * Validates UBL XML against the package's bundled UBL 2.1 XSD schemas.
 *
 * @see https://docs.oasis-open.org/ubl/os-UBL-2.1/xsd/maindoc/UBL-Invoice-2.1.xsd
 * @see https://docs.oasis-open.org/ubl/os-UBL-2.1/xsd/maindoc/UBL-CreditNote-2.1.xsd
 */
class UblSchemaValidator implements UblSchemaValidationInterface, UblValidationProvenanceProviderInterface
{
    private string $resourceDirectory;
    private int $maxIssues;

    /**
     * @param  string|null           $resourceDirectory Directory containing validation resources.
     * @param  int                   $maxIssues         Maximum number of validation findings retained.
     * @throws InvalidInputException when the finding limit is outside the supported range.
     */
    public function __construct(?string $resourceDirectory = null, int $maxIssues = ValidationIssueLimiter::DEFAULT_LIMIT)
    {
        ValidationIssueLimiter::assertLimit($maxIssues);

        $this->resourceDirectory = $resourceDirectory ?? ValidationResources::directory();
        $this->maxIssues         = $maxIssues;
    }

    /**
     * Validates the supplied UBL document.
     *
     * @param  UblXmlDocument                         $document Parsed UBL document to validate against its XSD schema.
     * @return void
     * @throws LocalValidationInfrastructureException when the bundled schema is unavailable.
     * @throws SchemaValidationException              when the document does not satisfy the schema.
     */
    public function validateDocument(UblXmlDocument $document): void
    {
        $domDocument = $document->domDocument();
        $schema      = $this->schemaPath($document);
        $version     = UblVersion::VERSION;

        if (! is_file($schema) || ! is_readable($schema)) {
            throw new LocalValidationInfrastructureException(sprintf(
                'Local UBL %s XSD schema is unavailable.',
                $version
            ));
        }

        // Capture libxml diagnostics only for this validation call and disable network access for schema loading.
        $maxIssues = $this->maxIssues;
        // The selected Invoice/CreditNote XSD checks XML structure, element order, namespaces, attributes, and data types.
        $result = LibxmlErrorScope::run(
            static fn (): XmlSchemaValidationResult => new XmlSchemaValidationResult(
                @$domDocument->schemaValidate($schema, LIBXML_NONET),
                self::limitedIssues(libxml_get_errors(), $maxIssues)
            )
        );

        if ($result->isValid()) {
            return;
        }

        throw new SchemaValidationException(
            $version,
            $result->issues(),
            $result->omittedCount()
        );
    }

    /**
     * Returns the UBL schema version used by this validator.
     *
     * @return array<string, string>
     */
    public function ruleVersions(): array
    {
        return ['ubl_schema' => UblVersion::VERSION];
    }

    /** @return array<string, string> */
    public function provenance(UblXmlDocument $document): array
    {
        return UblValidationProvenance::withFileHashes([], [
            'artifact.ubl_schema.sha256'                          => $this->schemaPath($document),
            ValidationResources::MANIFEST_ARTIFACT_PROVENANCE_KEY => ResourcePath::join(
                $this->resourceDirectory,
                ValidationResources::RULES_MANIFEST_FILENAME
            ),
        ]);
    }

    /** Resolves the local XSD selected for the supplied Invoice or CreditNote. */
    private function schemaPath(UblXmlDocument $document): string
    {
        // UblXmlDocument::fromXml guarantees a supported root, so its kind always resolves to a type.
        $type = UblDocumentType::fromKind($document->kind());

        $version = UblVersion::VERSION;
        $kind    = $type->rootElement();

        return ResourcePath::join(
            $this->resourceDirectory,
            sprintf('ubl-%s/xsd/maindoc/UBL-%s-%s.xsd', $version, $kind, $version)
        );
    }

    /**
     * Maps libxml errors while retaining only the configured number of issue objects.
     *
     * @param  LibXMLError[]                                     $errors Errors collected from schema validation.
     * @param  int                                               $limit  Maximum number of findings to retain.
     * @return LimitedValidationIssues<UblSchemaValidationIssue>
     */
    private static function limitedIssues(array $errors, int $limit): LimitedValidationIssues
    {
        $collector = new ValidationIssueLimiter($limit, UblSchemaValidationIssue::class);

        foreach ($errors as $error) {
            // Normalize libxml's multiline text before exposing it as a stable validation finding.
            $message = ValidationMessageNormalizer::normalize($error->message);

            if ($message === '') {
                continue;
            }

            $collector->addLazy(static function () use ($error, $message): UblSchemaValidationIssue {
                return new UblSchemaValidationIssue((int) $error->line, (int) $error->column, $message);
            });
        }

        return $collector->result();
    }
}
