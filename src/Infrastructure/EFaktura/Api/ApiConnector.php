<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderErrorEnvelopeParser;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpTransportInterface;
use DreaM1\EfakturaConnector\Support\JsonArrayDecoder;
use DreaM1\EfakturaConnector\Support\Xml\SecureXmlDocumentParser;
use DreaM1\EfakturaConnector\Support\Xml\XmlParseException;

/**
 * Executes ApiRequests built by endpoints: sends the request over the
 * (usually authenticated) transport, rejects unsuccessful HTTP statuses and decodes successful responses.
 * An unsuccessful response ends with ProviderErrorResponseException carrying the returned status, headers and body.
 *
 * Endpoints map the decoded body to their own typed response, so callers never see raw exchanges.
 */
class ApiConnector
{
    private HttpTransportInterface $transport;

    /**
     * @param HttpTransportInterface $transport HTTP transport used to perform the request.
     */
    public function __construct(HttpTransportInterface $transport)
    {
        $this->transport = $transport;
    }

    /**
     * Decodes and maps a successful JSON response while keeping its HTTP context on schema errors.
     *
     * @template T
     * @param  ApiRequest                           $request Provider API request describing the route, parameters and payload to send.
     * @param  callable(array<array-key, mixed>): T $mapper  Mapper used to convert the provider response.
     * @return T
     * @throws ProviderApiException                 when the call fails or is deferred, or the provider returns an
     *                                              unsuccessful or invalid response.
     * @throws RequestCoordinationException         when local HTTP request coordination cannot be established.
     */
    public function json(ApiRequest $request, callable $mapper)
    {
        $exchange = $this->exchange($request);

        if ($exchange->isWithoutContent()) {
            throw InvalidProviderResponseException::missingJson($exchange->rawStatusCode(), $exchange->headers());
        }

        $data = JsonArrayDecoder::decodeObject($exchange->body());

        // Malformed JSON and valid JSON with a non-object root both fail with the HTTP context attached.
        if ($data === null) {
            throw InvalidProviderResponseException::malformedJson($exchange->rawStatusCode(), $exchange->headers());
        }

        try {
            return $mapper($data);
        } catch (InvalidProviderResponseException $exception) {
            // Keep schema/field diagnostics from the mapper and add the status and selected response headers.
            throw $exception->withResponseContext($exchange->rawStatusCode(), $exchange->headers());
        }
    }

    /**
     * Sends the request and returns the body after secure parsing confirms it is well-formed XML.
     *
     * @param  ApiRequest                   $request Provider API request describing the route, parameters and payload to send.
     * @return string
     * @throws ProviderApiException         when the call fails or is deferred, or the provider returns an
     *                                      unsuccessful or invalid response.
     * @throws RequestCoordinationException when local HTTP request coordination cannot be established.
     */
    public function xml(ApiRequest $request): string
    {
        $exchange = $this->exchange($request);

        try {
            // Parse securely to validate the provider payload, but return its original bytes unchanged.
            $document = SecureXmlDocumentParser::parse($exchange->body());
        } catch (XmlParseException $exception) {
            // Map unsafe or malformed XML to the API exception while preserving response context.
            throw InvalidProviderResponseException::malformedXml($exception, $exchange->rawStatusCode(), $exchange->headers());
        }

        if ($document === null) {
            throw InvalidProviderResponseException::malformedXml(null, $exchange->rawStatusCode(), $exchange->headers());
        }

        return $exchange->body();
    }

    /**
     * Sends a request whose successful response carries no data the caller needs.
     *
     * @param  ApiRequest                   $request Provider API request describing the route, parameters and payload to send.
     * @return void
     * @throws ProviderApiException         when the call fails or is deferred, or the provider returns an
     *                                      unsuccessful or invalid response.
     * @throws RequestCoordinationException when local HTTP request coordination cannot be established.
     */
    public function send(ApiRequest $request): void
    {
        $this->exchange($request);
    }

    /** Sends the request and converts non-success responses to provider exceptions. */
    private function exchange(ApiRequest $request): HttpExchangeResult
    {
        $exchange = $this->transport->send(
            $this->toExchangeRequest($request)
        );

        if ($exchange->isSuccessful()) {
            return $exchange;
        }

        $envelope = ProviderErrorEnvelopeParser::parse($exchange->body());

        // Preserve raw bytes and attach a typed, tolerant parse when this body is a recognized error envelope.
        throw new ProviderErrorResponseException(
            $exchange->rawStatusCode(),
            $exchange->body(),
            $exchange->headers(),
            null,
            $envelope
        );
    }

    /** Maps one provider API request to the transport request executed by the configured transport. */
    private function toExchangeRequest(ApiRequest $request): HttpExchangeRequest
    {
        // Context stays available to logging decorators; only HTTP request fields are sent to the provider.
        return new HttpExchangeRequest(
            $request->httpMethod(),
            $request->target(),
            $request->headers(),
            $request->body(),
            $request->requestContext(),
            $request->acceptHeader(),
            $request->isRetrySafe()
        );
    }
}
