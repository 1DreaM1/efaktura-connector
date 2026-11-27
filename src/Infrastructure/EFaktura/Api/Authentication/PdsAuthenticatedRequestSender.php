<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpTransportInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Forwards PDS-signed requests without unrelated authentication headers.
 *
 * @internal
 */
final class PdsAuthenticatedRequestSender implements HttpTransportInterface
{
    private HttpTransportInterface $transport;

    /**
     * Creates a PDS authentication sender around the shared Integrator transport.
     *
     * @param HttpTransportInterface $transport Transport that binds the provider URL and executes requests.
     */
    public function __construct(HttpTransportInterface $transport)
    {
        $this->transport = $transport;
    }

    /**
     * Validates the PDS signature header and forwards the request without other authentication headers.
     *
     * @param  HttpExchangeRequest   $request Request carrying the PDS signature.
     * @return HttpExchangeResult    Result returned by the configured transport.
     * @throws InvalidInputException when the PDS signature header is missing, empty, or duplicated.
     */
    public function send(HttpExchangeRequest $request): HttpExchangeResult
    {
        $this->assertHasSinglePdsSignature($request);

        $authenticatedRequest = $request
            ->withoutHeader(HttpHeaderName::authorization())
            ->withoutHeader(HttpHeaderName::integratorToken());

        return $this->transport->send($authenticatedRequest);
    }

    /** @throws InvalidInputException when the PDS signature header is missing, empty, or duplicated. */
    private function assertHasSinglePdsSignature(HttpExchangeRequest $request): void
    {
        $signatures = $request->headers()->allNamed(HttpHeaderName::pdsSecret());

        if (count($signatures) !== 1 || trim($signatures[0]->value()) === '') {
            throw new InvalidInputException('Exactly one non-empty X-PDS-Secret header is required.');
        }
    }
}
