<?php

namespace DreaM1\EfakturaConnector\Tests\Support;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpTransportInterface;
use LogicException;

/**
 * In-memory transport for endpoint tests: records every request and answers from a responder callback.
 */
final class RecordingTransport implements HttpTransportInterface
{
    /** @var callable(HttpExchangeRequest): HttpExchangeResult */
    private $responder;
    /** @var HttpExchangeRequest[] */
    private array $requests = [];

    /**
     * Creates a transport that records requests and delegates responses to a callback.
     *
     * @param callable(HttpExchangeRequest): HttpExchangeResult $responder Callback producing an exchange for each request.
     */
    public function __construct(callable $responder)
    {
        $this->responder = $responder;
    }

    /**
     * Creates a transport that returns the same exchange for every request.
     *
     * @param  HttpExchangeResult $exchange Exchange returned by each send call.
     * @return self               Recording transport configured with a fixed response.
     */
    public static function returning(HttpExchangeResult $exchange): self
    {
        return new self(static function () use ($exchange): HttpExchangeResult {
            return $exchange;
        });
    }

    /**
     * Records a request and returns the responder's exchange.
     *
     * @param  HttpExchangeRequest $request Request to send.
     * @return HttpExchangeResult  Exchange produced by the configured responder.
     */
    public function send(HttpExchangeRequest $request): HttpExchangeResult
    {
        $this->requests[] = $request;

        return ($this->responder)($request);
    }

    /**
     * Returns all requests sent through this transport in order.
     *
     * @return HttpExchangeRequest[] Recorded requests.
     */
    public function requests(): array
    {
        return $this->requests;
    }

    /**
     * Returns the most recently sent request.
     *
     * @return HttpExchangeRequest Last recorded request.
     * @throws LogicException      when no request has been sent
     */
    public function lastRequest(): HttpExchangeRequest
    {
        if ($this->requests === []) {
            throw new LogicException('No request has been sent.');
        }

        return $this->requests[count($this->requests) - 1];
    }
}
