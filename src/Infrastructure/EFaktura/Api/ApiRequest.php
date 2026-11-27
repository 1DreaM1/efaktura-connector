<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\Documents\DocumentRequestContext;
use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;
use DreaM1\EfakturaConnector\Domain\EFaktura\CodeList\ParticipantIdentifierSchemeCodeList;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization\ApiJsonEncoder;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\AbstractHttpRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\HttpRequestContext;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\HttpRequestTarget;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaders;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\MediaType;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Immutable description of one provider API call, built by an endpoint and executed by ApiConnector.
 *
 * Copy-returning with* methods let endpoints assemble requests without mutating shared state.
 * GET is considered retry-safe by default; other methods require an explicit opt-in because a
 * transport failure can occur after the provider has already applied a write operation.
 *
 * Path templates use {name} placeholders; their values are validated and URL-encoded:
 *   ApiRequest::get('/v1/webhooks/{webhookId}', ['webhookId' => $id])
 */
final class ApiRequest extends AbstractHttpRequest
{
    /** HTTP header name used to provide the Peppol participant identity. */
    public const PARTICIPANT_HEADER = HttpHeaderName::PEPPOL_PARTICIPANT_ID;

    private ?Participant $participant = null;

    /** Initializes requests through the method-specific public factories. */
    private function __construct(HttpMethod $method, HttpRequestTarget $target)
    {
        parent::__construct(
            $method,
            $target,
            HttpHeaders::create(),
            null,
            HttpRequestContext::empty(),
            MediaType::APPLICATION_JSON,
            $method->isGet()
        );
    }

    /**
     * Creates a GET request for the supplied path template and parameters.
     *
     * @param  string                    $pathTemplate   Provider API path template containing parameter placeholders.
     * @param  array<string, string|int> $pathParameters Values encoded as individual path segments.
     * @return self
     * @throws InvalidInputException     when path parameters do not match the template or an identifier is invalid.
     */
    public static function get(string $pathTemplate, array $pathParameters = []): self
    {
        return self::create(HttpMethod::get(), $pathTemplate, $pathParameters);
    }

    /**
     * Creates a POST request for the supplied path template and parameters.
     *
     * @param  string                    $pathTemplate   Provider API path template containing parameter placeholders.
     * @param  array<string, string|int> $pathParameters Values encoded as individual path segments.
     * @return self
     * @throws InvalidInputException     when path parameters do not match the template or an identifier is invalid.
     */
    public static function post(string $pathTemplate, array $pathParameters = []): self
    {
        return self::create(HttpMethod::post(), $pathTemplate, $pathParameters);
    }

    /**
     * Creates a PATCH request for the supplied path template and parameters.
     *
     * @param  string                    $pathTemplate   Provider API path template containing parameter placeholders.
     * @param  array<string, string|int> $pathParameters Values encoded as individual path segments.
     * @return self
     * @throws InvalidInputException     when path parameters do not match the template or an identifier is invalid.
     */
    public static function patch(string $pathTemplate, array $pathParameters = []): self
    {
        return self::create(HttpMethod::patch(), $pathTemplate, $pathParameters);
    }

    /**
     * Creates a PUT request for the supplied path template and parameters.
     *
     * @param  string                    $pathTemplate   Provider API path template containing parameter placeholders.
     * @param  array<string, string|int> $pathParameters Values encoded as individual path segments.
     * @return self
     * @throws InvalidInputException     when path parameters do not match the template or an identifier is invalid.
     */
    public static function put(string $pathTemplate, array $pathParameters = []): self
    {
        return self::create(HttpMethod::put(), $pathTemplate, $pathParameters);
    }

    /**
     * Creates a DELETE request for the supplied path template and parameters.
     *
     * @param  string                    $pathTemplate   Provider API path template containing parameter placeholders.
     * @param  array<string, string|int> $pathParameters Values encoded as individual path segments.
     * @return self
     * @throws InvalidInputException     when path parameters do not match the template or an identifier is invalid.
     */
    public static function delete(string $pathTemplate, array $pathParameters = []): self
    {
        return self::create(HttpMethod::delete(), $pathTemplate, $pathParameters);
    }

    /**
     * Returns a copy with non-null query parameters merged into the request.
     * Later non-null values replace earlier values with the same key.
     *
     * @param  array<string, string|int|null> $query null values are omitted
     * @return self
     * @throws InvalidInputException          when a query value is not a string, integer, or null.
     */
    public function withQuery(array $query): self
    {
        $target = $this->target->withQuery($query);

        if ($target === $this->target) {
            return $this;
        }

        $request         = clone $this;
        $request->target = $target;

        return $request;
    }

    /**
     * Encodes the body immediately, so an unencodable payload fails before any token or HTTP call.
     *
     * @param  array<array-key, mixed>  $data JSON request object or list
     * @return self
     * @throws RequestEncodingException
     *                                  when the payload cannot be encoded as JSON.
     */
    public function withJsonBody(array $data): self
    {
        return $this->withEncodedJsonBody((new ApiJsonEncoder())->encode($data));
    }

    /**
     * Returns a copy with a JSON body that has already been encoded by an infrastructure mapper.
     *
     * @internal
     */
    public function withEncodedJsonBody(string $body): self
    {
        $request       = clone $this;
        $request->body = $body;

        return $request;
    }

    /**
     * Returns a copy with this header set, replacing any existing value of the same name.
     *
     * The participant header is parsed and replaces the current participant scope.
     *
     * @param  HttpHeaderName        $name  Validated HTTP header field name.
     * @param  string                $value Value to parse, normalize, or validate.
     * @return self
     * @throws InvalidInputException when the header value is invalid or the participant identifier is
     *                               malformed or unsupported for this operation.
     */
    public function withHeader(HttpHeaderName $name, string $value): self
    {
        if ($name->isNamed(self::PARTICIPANT_HEADER)) {
            HttpHeaderLine::assertValid($name->value(), $value);

            return $this->forParticipant(Participant::parse($value));
        }

        $request          = clone $this;
        $request->headers = $this->headers->withHeader($name, $value);

        return $request;
    }

    /**
     * Returns a copy with an additional value for this header.
     *
     * The participant header cannot be added; use withHeader() or forParticipant() to set its scope.
     * Idempotency-Key is limited to one value.
     *
     * @param  HttpHeaderName        $name  Validated HTTP header field name.
     * @param  string                $value Value to parse, normalize, or validate.
     * @return self
     * @throws InvalidInputException when the value is invalid, or a participant or duplicate idempotency
     *                               header is added.
     */
    public function withAddedHeader(HttpHeaderName $name, string $value): self
    {
        if ($name->isNamed(self::PARTICIPANT_HEADER)) {
            throw new InvalidInputException('The participant header cannot be added.');
        }

        if ($name->isNamed(HttpHeaderName::IDEMPOTENCY_KEY) && $this->headers->hasHeader($name)) {
            throw new InvalidInputException('The Idempotency-Key header may only occur once.');
        }

        $request          = clone $this;
        $request->headers = $this->headers->withAddedHeader($name, $value);

        return $request;
    }

    /**
     * Returns a copy with the stable key for an idempotent provider operation.
     *
     * This does not change retry safety; callers must opt into retries separately when supported.
     */
    public function withIdempotencyKey(IdempotencyKey $key): self
    {
        $request          = clone $this;
        $request->headers = $this->headers->withHeader(HttpHeaderName::idempotencyKey(), $key->value());

        return $request;
    }

    /** Returns a copy that asks the provider for an XML response. */
    public function acceptXml(): self
    {
        $request               = clone $this;
        $request->acceptHeader = MediaType::APPLICATION_XML;

        return $request;
    }

    /**
     * Acts on behalf of the participant (X-Peppol-Participant-Id header), replacing any earlier value.
     *
     * @param  Participant           $participant Participant whose identity scopes the provider operation.
     * @throws InvalidInputException when the scheme is deprecated, unknown, removed, or not registrable.
     */
    public function forParticipant(Participant $participant): self
    {
        ParticipantIdentifierSchemeCodeList::assertAllowedForExistingOperation($participant->scheme());

        $request          = clone $this;
        $request->headers = $this->headers->withHeader(
            HttpHeaderName::peppolParticipantId(),
            $participant->identifier()
        );
        $request->participant = $participant;

        return $request;
    }

    /**
     * Returns a copy whose call may be replayed after an ambiguous result.
     *
     * Enable this only when the provider operation is safe to repeat, such as an operation protected
     * by a stable idempotency key. The default is true for GET and false for every other method.
     *
     * @param bool $retrySafe Whether the operation may be retried safely.
     */
    public function retrySafe(bool $retrySafe = true): self
    {
        $request            = clone $this;
        $request->retrySafe = $retrySafe;

        return $request;
    }

    /**
     * Returns a copy with host document metadata for logs; the context is not sent to the provider.
     *
     * @param DocumentRequestContext|null $context Request or document context associated with the operation.
     */
    public function withContext(?DocumentRequestContext $context): self
    {
        $request          = clone $this;
        $request->context = $context === null
            ? HttpRequestContext::empty()
            : HttpRequestContext::fromArray($context->toArray());

        return $request;
    }

    /**
     * Returns the resolved route path without its query string; template placeholders were substituted
     * and URL-encoded when built.
     *
     * @return string
     */
    public function path(): string
    {
        return $this->target->path();
    }

    /** @internal Returns the resolved path and encoded query string for HTTP request mapping. */
    public function requestTarget(): string
    {
        return $this->target->toString();
    }

    /**
     * Returns the media type requested through the Accept header.
     *
     * @return string
     */
    public function accept(): string
    {
        return $this->acceptHeader;
    }

    /**
     * Returns the participant on whose behalf this request is sent, or null for an unscoped request.
     *
     * @return Participant|null
     */
    public function participant(): ?Participant
    {
        return $this->participant;
    }

    /**
     * Creates a request after resolving and validating its path template.
     *
     * @param  HttpMethod                $method         HTTP method to execute.
     * @param  string                    $pathTemplate   Provider API path template.
     * @param  array<string, string|int> $pathParameters Values encoded as individual path segments.
     * @return self
     */
    private static function create(HttpMethod $method, string $pathTemplate, array $pathParameters): self
    {
        return new self($method, ApiRequestTargetResolver::fromTemplate($pathTemplate, $pathParameters));
    }
}
