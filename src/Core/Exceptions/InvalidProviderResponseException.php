<?php

namespace DreaM1\EfakturaConnector\Core\Exceptions;

use Throwable;

/**
 * The provider answered 2xx, but the body is not what the API documents: malformed JSON or XML,
 * or a field that is missing or has the wrong type.
 */
final class InvalidProviderResponseException extends ProviderApiException
{
    private ?string $schema;
    private ?string $field;

    /**
     * @param string                $message         Message or diagnostic text associated with the result.
     * @param string|null           $schema          Response schema name used to identify validation errors.
     * @param string|null           $field           Response field name to read or validate.
     * @param Throwable|null        $previous        Previous exception that caused this failure, when available.
     * @param int|null              $statusCode      HTTP status code received from the provider, when available.
     * @param array<string, string> $responseHeaders HTTP response headers retained from the provider.
     */
    public function __construct(
        string $message,
        ?string $schema = null,
        ?string $field = null,
        ?Throwable $previous = null,
        ?int $statusCode = null,
        array $responseHeaders = []
    ) {
        parent::__construct($message, $statusCode, $responseHeaders, $previous);
        $this->schema = $schema;
        $this->field  = $field;
    }

    /**
     * Creates an exception for a malformed JSON response body.
     *
     * @param  int|null              $statusCode      HTTP status code received from the provider, when available.
     * @param  array<string, string> $responseHeaders HTTP response headers retained from the provider.
     * @return self
     */
    public static function malformedJson(?int $statusCode = null, array $responseHeaders = []): self
    {
        return new self('API response body is not a valid JSON object.', null, null, null, $statusCode, $responseHeaders);
    }

    /**
     * Creates an exception for a successful response that omitted its documented JSON body.
     *
     * @param  int|null              $statusCode      HTTP status code received from the provider, when available.
     * @param  array<string, string> $responseHeaders HTTP response headers retained from the provider.
     * @return self
     */
    public static function missingJson(?int $statusCode = null, array $responseHeaders = []): self
    {
        return new self('API response body is missing the documented JSON document.', null, null, null, $statusCode, $responseHeaders);
    }

    /**
     * Creates an exception for malformed or unsafe XML in the response body.
     *
     * @param  Throwable|null        $previous        Previous exception that caused this failure, when available.
     * @param  int|null              $statusCode      HTTP status code received from the provider, when available.
     * @param  array<string, string> $responseHeaders HTTP response headers retained from the provider.
     * @return self
     */
    public static function malformedXml(
        ?Throwable $previous = null,
        ?int $statusCode = null,
        array $responseHeaders = []
    ): self {
        return new self('API response body is not well-formed, safe XML.', null, null, $previous, $statusCode, $responseHeaders);
    }

    /**
     * Returns a copy enriched with the HTTP response context.
     * @param  int                   $statusCode      HTTP status code received from the provider, when available.
     * @param  array<string, string> $responseHeaders HTTP response headers retained from the provider.
     * @return self
     */
    public function withResponseContext(int $statusCode, array $responseHeaders): self
    {
        return new self(
            $this->getMessage(),
            $this->schema,
            $this->field,
            $this->getPrevious(),
            $statusCode,
            $responseHeaders
        );
    }

    /**
     * OpenAPI schema of the invalid response, when known.
     *
     * @return string|null
     */
    public function schema(): ?string
    {
        return $this->schema;
    }

    /**
     * Field of the schema that is missing or invalid, when known.
     *
     * @return string|null
     */
    public function field(): ?string
    {
        return $this->field;
    }
}
