<?php

namespace DreaM1\EfakturaConnector\Tests\Support;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Support\Timestamp;
use stdClass;

/** Small OpenAPI 3.1 contract checker used by API boundary tests. */
final class OpenApiContract
{
    /** @var array<string, mixed> */
    private array $document;

    /**
     * Loads the repository OpenAPI snapshot or accepts an already decoded document.
     *
     * @param array<string, mixed>|null $document Parsed OpenAPI document, or null to load the repository snapshot.
     */
    public function __construct(?array $document = null)
    {
        if ($document === null) {
            $path     = ResourcePath::join(dirname(__DIR__, 2), 'docs/eFaktura/api/openapi.json');
            $document = json_decode((string) file_get_contents($path), true);
        }

        $this->document = is_array($document) ? $document : [];
    }

    /**
     * Returns the decoded OpenAPI document used for contract checks.
     *
     * @return array<string, mixed> OpenAPI document, or an empty array when decoding failed.
     */
    public function document(): array
    {
        return $this->document;
    }

    /**
     * Validates a value against a JSON Schema subset used by the API contract tests.
     *
     * @param  mixed                $value  Value to validate.
     * @param  array<string, mixed> $schema Schema definition.
     * @return string[]             Validation errors; an empty array means the value matched.
     */
    public function validate($value, array $schema): array
    {
        return $this->validateSchema($value, $schema, '$');
    }

    /**
     * Validates a response fixture against the schema published for its operation and HTTP status.
     *
     * @param  string   $path      Request path used to locate the OpenAPI operation.
     * @param  string   $method    HTTP method for the operation.
     * @param  int      $status    HTTP response status code.
     * @param  mixed    $value     Decoded JSON value or raw text for a non-JSON response.
     * @param  string   $mediaType Response media type used to select the schema.
     * @return string[] Contract violations; an empty array means the response matched.
     */
    public function validateResponse(
        string $path,
        string $method,
        int $status,
        $value,
        string $mediaType = 'application/json'
    ): array {
        $operation = $this->operation($path, $method);

        if ($operation === null) {
            return [sprintf('OpenAPI has no %s operation for %s.', strtoupper($method), $path)];
        }

        $response = $operation['responses'][(string) $status] ?? null;

        if (! is_array($response)) {
            return [sprintf('OpenAPI has no %s response for %s %s.', $status, strtoupper($method), $path)];
        }

        $content = $response['content'] ?? [];

        if ($content === []) {
            return $value === null ? [] : ['The OpenAPI response does not define a response body.'];
        }

        $media = $content[$mediaType] ?? $content['*/*'] ?? null;

        if (! is_array($media) || ! isset($media['schema']) || ! is_array($media['schema'])) {
            return [sprintf('OpenAPI response does not define %s content.', $mediaType)];
        }

        return $this->validate($value, $media['schema']);
    }

    /**
     * Validates the route, parameters, Accept value and JSON body of an assembled transport request.
     * Authentication headers are handled by the separate authenticated transport decorator.
     *
     * @param  HttpExchangeRequest $request Assembled request to validate.
     * @return string[]            Contract violations; an empty array means the request matched.
     */
    public function validateRequest(HttpExchangeRequest $request): array
    {
        $operation = $this->operation($request->path(), strtolower($request->httpMethod()->value()));

        if ($operation === null) {
            return [sprintf(
                'OpenAPI has no %s operation for %s.',
                $request->httpMethod()->value(),
                $request->path()
            )];
        }

        $errors      = [];
        $parameters  = $operation['parameters'] ?? [];
        $queryString = parse_url($request->path(), PHP_URL_QUERY);
        parse_str(is_string($queryString) ? $queryString : '', $query);
        $headerValues    = $this->headers($request->headers()->all());
        $declaredQuery   = [];
        $declaredHeaders = [];

        foreach ($parameters as $parameter) {
            if (! is_array($parameter) || ! isset($parameter['in'], $parameter['name'])) {
                continue;
            }

            $location = $parameter['in'];
            $name     = $parameter['name'];
            $present  = false;
            $value    = null;

            if ($location === 'query') {
                $declaredQuery[$name] = true;
                $present              = array_key_exists($name, $query);
                $value                = $present ? $query[$name] : null;
            } elseif ($location === 'header') {
                $declaredHeaders[strtolower($name)] = true;
                $headerName                         = strtolower($name);
                $present                            = array_key_exists($headerName, $headerValues);
                $value                              = $present ? $headerValues[$headerName] : null;
            } elseif ($location === 'path') {
                // A matching path template already proves that every declared placeholder is present.
                $present = true;
            }

            if (! $present) {
                if (! empty($parameter['required'])) {
                    $errors[] = sprintf('Required %s parameter "%s" is missing.', $location, $name);
                }

                continue;
            }

            if ($location === 'path') {
                continue;
            }

            $parameterSchema = $parameter['schema'] ?? [];

            if ($location === 'query' && isset($parameterSchema['type']) && $parameterSchema['type'] === 'integer') {
                if (is_string($value) && preg_match('/\A-?(?:0|[1-9][0-9]*)\z/', $value) === 1) {
                    $value = (int) $value;
                }
            }

            foreach ($this->validate($value, $parameterSchema) as $error) {
                $errors[] = sprintf('%s parameter "%s": %s', $location, $name, $error);
            }
        }

        foreach (array_keys($query) as $name) {
            if (! isset($declaredQuery[$name])) {
                $errors[] = sprintf('Query parameter "%s" is not documented by OpenAPI.', $name);
            }
        }

        foreach (array_keys($headerValues) as $name) {
            if ($name !== 'authorization' && ! isset($declaredHeaders[$name])) {
                $errors[] = sprintf('Header "%s" is not documented as an operation parameter.', $name);
            }
        }

        $bodyDefinition = $operation['requestBody'] ?? null;

        if ($request->body() === null) {
            if (is_array($bodyDefinition) && ! empty($bodyDefinition['required'])) {
                $errors[] = 'The OpenAPI request body is required but missing.';
            }
        } elseif (! is_array($bodyDefinition)) {
            $errors[] = 'The request has a body but OpenAPI does not define one.';
        } else {
            $schema = $bodyDefinition['content']['application/json']['schema'] ?? null;

            if (! is_array($schema)) {
                $errors[] = 'OpenAPI does not define an application/json request body schema.';
            } else {
                $body = json_decode($request->body());

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[] = 'The request body is not valid JSON.';
                } else {
                    foreach ($this->validate($body, $schema) as $error) {
                        $errors[] = 'Request body: ' . $error;
                    }
                }
            }
        }

        $acceptErrors = $this->validateAccept($request, $operation);

        return array_merge($errors, $acceptErrors);
    }

    /**
     * Finds an operation by matching its OpenAPI path template and HTTP method.
     *
     * @param  string                    $actualPath Request path to match.
     * @param  string                    $method     Lowercase HTTP method.
     * @return array<string, mixed>|null Matching operation, or null when no route matches.
     */
    private function operation(string $actualPath, string $method): ?array
    {
        $requestPath = parse_url($actualPath, PHP_URL_PATH);

        if (! is_string($requestPath)) {
            return null;
        }

        foreach (($this->document['paths'] ?? []) as $template => $operations) {
            $segments        = explode('/', trim($template, '/'));
            $patternSegments = [];

            foreach ($segments as $segment) {
                // A template placeholder matches one non-empty path segment; literal segments are regex-escaped.
                $patternSegments[] = preg_match('/\A\{[^}]+\}\z/', $segment) === 1
                    ? '[^/]+'
                    : preg_quote($segment, '~');
            }

            $pattern = '~\A/' . implode('/', $patternSegments) . '\z~';

            if (preg_match($pattern, $requestPath) !== 1) {
                continue;
            }

            $operation = $operations[$method] ?? null;

            return is_array($operation) ? $operation : null;
        }

        return null;
    }

    /** @param HttpHeaderLine[] $headerLines @return array<string, string> */
    private function headers(array $headerLines): array
    {
        $headers = [];

        foreach ($headerLines as $headerLine) {
            $headers[$headerLine->name()] = $headerLine->value();
        }

        return $headers;
    }

    /**
     * Checks whether the request Accept header is declared by the operation.
     *
     * @param  HttpExchangeRequest  $request   Request whose Accept header is checked.
     * @param  array<string, mixed> $operation Matched OpenAPI operation.
     * @return string[]             Accept header violations.
     */
    private function validateAccept(HttpExchangeRequest $request, array $operation): array
    {
        $accept            = $request->acceptHeader();
        $hasSuccessContent = false;

        foreach (($operation['responses'] ?? []) as $status => $response) {
            if (strpos((string) $status, '2') !== 0) {
                continue;
            }

            if (! is_array($response)) {
                continue;
            }

            foreach (array_keys($response['content'] ?? []) as $mediaType) {
                $hasSuccessContent = true;

                if ($mediaType === '*/*' || $mediaType === $accept) {
                    return [];
                }
            }
        }

        // A no-content success response has no representation to negotiate.
        if (! $hasSuccessContent) {
            return [];
        }

        return [sprintf('Accept value "%s" is not documented for this operation.', $accept)];
    }

    /**
     * Recursively validates a value against a supported JSON Schema definition.
     *
     * @param  mixed                $value  Value being checked.
     * @param  array<string, mixed> $schema Schema definition.
     * @param  string               $path   JSON-style path used in validation errors.
     * @return string[]             Validation errors.
     */
    private function validateSchema($value, array $schema, string $path): array
    {
        if (isset($schema['$ref'])) {
            $target = $this->resolveReference($schema['$ref']);
            return $target === null
                ? [$path . ' has an unresolved schema reference ' . $schema['$ref'] . '.']
                : $this->validateSchema($value, $target, $path);
        }

        if (! empty($schema['nullable']) && $value === null) {
            return [];
        }

        if (isset($schema['oneOf'])) {
            // OpenAPI oneOf requires exactly one matching branch, unlike allOf, which accumulates constraints below.
            $matchingBranches = 0;

            foreach ($schema['oneOf'] as $branch) {
                if (is_array($branch) && $this->validateSchema($value, $branch, $path) === []) {
                    $matchingBranches++;
                }
            }

            if ($matchingBranches !== 1) {
                return [$path . ' must match exactly one OpenAPI oneOf branch.'];
            }
        }

        $errors = [];

        foreach (($schema['allOf'] ?? []) as $branch) {
            if (is_array($branch)) {
                $errors = array_merge($errors, $this->validateSchema($value, $branch, $path));
            }
        }

        // Apply then-constraints only when the same value satisfies the if-schema.
        if (isset($schema['if']) && is_array($schema['if']) && $this->validateSchema($value, $schema['if'], $path) === []) {
            if (isset($schema['then']) && is_array($schema['then'])) {
                $errors = array_merge($errors, $this->validateSchema($value, $schema['then'], $path));
            }
        }

        if (array_key_exists('const', $schema) && $value !== $schema['const']) {
            $errors[] = $path . ' must equal the documented constant.';
        }

        $expectedType = $schema['type'] ?? null;

        if ($expectedType !== null && ! $this->matchesType($value, $expectedType)) {
            $errors[] = sprintf('%s must have JSON type %s.', $path, is_array($expectedType) ? implode('|', $expectedType) : $expectedType);
            return $errors;
        }

        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $errors[] = $path . ' must be one of the documented enum values.';
        }

        if ((isset($schema['type']) && $schema['type'] === 'object') || isset($schema['properties']) || isset($schema['required'])) {
            $properties = $this->objectProperties($value);
            $declared   = $schema['properties'] ?? [];

            foreach (($schema['required'] ?? []) as $name) {
                if (! array_key_exists($name, $properties)) {
                    $errors[] = $path . '.' . $name . ' is required.';
                }
            }

            // additionalProperties may reject unknown keys or provide a schema for their values.
            foreach ($properties as $name => $propertyValue) {
                if (isset($declared[$name])) {
                    $errors = array_merge(
                        $errors,
                        $this->validateSchema($propertyValue, $declared[$name], $path . '.' . $name)
                    );
                } elseif (($schema['additionalProperties'] ?? true) === false) {
                    $errors[] = $path . '.' . $name . ' is not a documented property.';
                } elseif (is_array($schema['additionalProperties'] ?? null)) {
                    $errors = array_merge(
                        $errors,
                        $this->validateSchema($propertyValue, $schema['additionalProperties'], $path . '.' . $name)
                    );
                }
            }
        }

        if (isset($schema['type']) && $schema['type'] === 'array') {
            $count = count($value);

            if (isset($schema['minItems']) && $count < $schema['minItems']) {
                $errors[] = $path . ' has fewer items than OpenAPI allows.';
            }

            if (isset($schema['maxItems']) && $count > $schema['maxItems']) {
                $errors[] = $path . ' has more items than OpenAPI allows.';
            }

            if (isset($schema['items']) && is_array($schema['items'])) {
                foreach ($value as $index => $item) {
                    $errors = array_merge(
                        $errors,
                        $this->validateSchema($item, $schema['items'], $path . '[' . $index . ']')
                    );
                }
            }
        }

        if (is_int($value) || is_float($value)) {
            if (isset($schema['minimum']) && $value < $schema['minimum']) {
                $errors[] = $path . ' is below the OpenAPI minimum.';
            }

            if (isset($schema['maximum']) && $value > $schema['maximum']) {
                $errors[] = $path . ' is above the OpenAPI maximum.';
            }

            if (isset($schema['exclusiveMinimum']) && is_numeric($schema['exclusiveMinimum']) && $value <= $schema['exclusiveMinimum']) {
                $errors[] = $path . ' is below the exclusive OpenAPI minimum.';
            }

            if (isset($schema['exclusiveMaximum']) && is_numeric($schema['exclusiveMaximum']) && $value >= $schema['exclusiveMaximum']) {
                $errors[] = $path . ' is above the exclusive OpenAPI maximum.';
            }
        }

        if (is_string($value)) {
            $errors = array_merge($errors, $this->validateStringConstraints($value, $schema, $path));
        }

        return $errors;
    }

    /** @param mixed $value @param string|string[] $type */
    private function matchesType($value, $type): bool
    {
        // Decoded associative arrays represent objects; sequential arrays represent JSON arrays.
        foreach ((array) $type as $candidate) {
            if ($candidate === 'null' && $value === null) {
                return true;
            }

            if ($candidate === 'object' && (is_array($value) && ! $this->isList($value) || $value instanceof stdClass)) {
                return true;
            }

            if ($candidate === 'array' && is_array($value) && $this->isList($value)) {
                return true;
            }

            if ($candidate === 'string' && is_string($value)) {
                return true;
            }

            if ($candidate === 'integer' && is_int($value)) {
                return true;
            }

            if ($candidate === 'number' && (is_int($value) || is_float($value))) {
                return true;
            }

            if ($candidate === 'boolean' && is_bool($value)) {
                return true;
            }
        }

        return $type === null;
    }

    /** @param mixed $value @return array<string|int, mixed> */
    private function objectProperties($value): array
    {
        if ($value instanceof stdClass) {
            return get_object_vars($value);
        }

        // Do not reinterpret an empty or sequential array as an object after JSON decoding.
        return is_array($value) && ! $this->isList($value) ? $value : [];
    }

    /**
     * Checks a string against OpenAPI length and pattern constraints.
     *
     * @param  string               $value  String value being checked.
     * @param  array<string, mixed> $schema String schema constraints.
     * @param  string               $path   JSON-style path used in validation errors.
     * @return string[]             Constraint violations.
     */
    private function validateStringConstraints(string $value, array $schema, string $path): array
    {
        $errors = [];

        if (isset($schema['minLength']) && mb_strlen($value, 'UTF-8') < $schema['minLength']) {
            $errors[] = $path . ' is shorter than OpenAPI allows.';
        }

        if (isset($schema['maxLength']) && mb_strlen($value, 'UTF-8') > $schema['maxLength']) {
            $errors[] = $path . ' is longer than OpenAPI allows.';
        }

        if (isset($schema['pattern']) && preg_match('~' . str_replace('~', '\\~', $schema['pattern']) . '~u', $value) !== 1) {
            $errors[] = $path . ' does not match its OpenAPI pattern.';
        }

        if (($schema['format'] ?? null) === 'date-time' && Timestamp::tryParseRfc3339($value) === null) {
            $errors[] = $path . ' is not an RFC 3339 date-time.';
        }

        if (($schema['format'] ?? null) === 'uuid' && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $value) !== 1) {
            $errors[] = $path . ' is not a UUID.';
        }

        if (($schema['format'] ?? null) === 'uri' && filter_var($value, FILTER_VALIDATE_URL) === false) {
            $errors[] = $path . ' is not a URI.';
        }

        return $errors;
    }

    /** @param array<array-key, mixed> $value @return bool */
    private function isList(array $value): bool
    {
        // JSON array shape requires contiguous zero-based keys; associative PHP arrays stand for objects.
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }

    /**
     * Resolves a local OpenAPI component schema reference.
     *
     * @param  string                    $reference Local schema reference.
     * @return array<string, mixed>|null Referenced schema, or null when unresolved.
     */
    private function resolveReference(string $reference): ?array
    {
        if (strpos($reference, '#/components/schemas/') !== 0) {
            return null;
        }

        $name   = substr($reference, strlen('#/components/schemas/'));
        $schema = $this->document['components']['schemas'][$name] ?? null;

        return is_array($schema) ? $schema : null;
    }
}
