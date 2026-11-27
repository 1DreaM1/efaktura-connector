<?php

namespace DreaM1\EfakturaConnector\Laravel\Logging;

use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Logging\ApiCommunicationLogSanitizer;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderErrorEnvelopeParser;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutionLoggerInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use Illuminate\Support\Facades\Log;

/**
 * Writes safe HTTP execution summaries to Laravel's default log channel.
 *
 * Each entry carries the method, the request path without its query string, the status and the duration. The path
 * contains provider identifiers (for example a document ID), which is what makes a failed call traceable. A
 * correlation ID is added for error responses when the provider supplies one in the expected safe format.
 * Headers, bodies, query strings and exception messages are never logged.
 */
final class LaravelHttpRequestExecutionLogger implements HttpRequestExecutionLoggerInterface
{
    /**
     * Records a response without including provider payloads, headers or the query string.
     *
     * @param HttpExchangeRequest $request  HTTP request associated with the response.
     * @param HttpExchangeResult  $exchange HTTP response returned by the transport.
     */
    public function recordResponse(HttpExchangeRequest $request, HttpExchangeResult $exchange): void
    {
        $context = [
            'method'      => $request->httpMethod()->value(),
            'path'        => $request->target()->path(),
            'status'      => $exchange->rawStatusCode(),
            'duration_ms' => self::durationMilliseconds($exchange->startedAt()),
        ];

        if ($exchange->isSuccessful()) {
            Log::info('eFaktura HTTP request completed', $context);

            return;
        }

        $envelope      = ProviderErrorEnvelopeParser::parse($exchange->body());
        $correlationId = (new ApiCommunicationLogSanitizer())->sanitizeCorrelationId(
            $envelope === null ? null : $envelope->correlationId()
        );

        if ($correlationId !== null) {
            $context['correlation_id'] = $correlationId;
        }

        if ($exchange->isServerError()) {
            Log::error('eFaktura HTTP request returned a server error', $context);

            return;
        }

        Log::warning('eFaktura HTTP request returned a non-success status', $context);
    }

    /**
     * Records a transport failure without including its message or response body.
     *
     * @param HttpExchangeRequest  $request   HTTP request associated with the failure.
     * @param ProviderApiException $exception Failure raised by the executor.
     * @param float                $startedAt Unix timestamp when the operation started.
     */
    public function recordFailure(HttpExchangeRequest $request, ProviderApiException $exception, float $startedAt): void
    {
        $context = [
            'method'      => $request->httpMethod()->value(),
            'path'        => $request->target()->path(),
            'status'      => $exception->rawStatusCode(),
            'duration_ms' => self::durationMilliseconds($startedAt),
            'exception'   => get_class($exception),
        ];

        $envelope = $exception instanceof ProviderErrorResponseException ? $exception->errorEnvelope() : null;

        if ($envelope !== null) {
            $correlationId = (new ApiCommunicationLogSanitizer())->sanitizeCorrelationId($envelope->correlationId());

            if ($correlationId !== null) {
                $context['correlation_id'] = $correlationId;
            }
        }

        Log::warning('eFaktura HTTP request execution failed', $context);
    }

    /** Returns elapsed request time rounded to milliseconds. */
    private static function durationMilliseconds(float $startedAt): float
    {
        return round(max(0.0, microtime(true) - $startedAt) * 1000, 3);
    }
}
