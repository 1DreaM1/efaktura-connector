<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication;

use DreaM1\EfakturaConnector\Core\IntegratorApi\PdsSigningSecret;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\HashAlgorithm;

/** Signs PDS API requests with the secret supplied for the operation. */
final class PdsRequestSigner
{
    /**
     * Returns a copy of the request carrying its HMAC-SHA512 PDS signature.
     *
     * The signature is calculated from the already encoded request body so it matches the bytes sent to the provider.
     *
     * @param  ApiRequest            $request Request with an encoded JSON body.
     * @param  PdsSigningSecret      $secret  Secret used to sign the request body.
     * @return ApiRequest
     * @throws InvalidInputException when the request has no body.
     */
    public static function sign(ApiRequest $request, PdsSigningSecret $secret): ApiRequest
    {
        $body = $request->body();

        if ($body === null) {
            throw new InvalidInputException('A request body is required to create a PDS signature.');
        }

        $signature = hash_hmac(
            HashAlgorithm::SHA_512,
            $body,
            $secret->reveal()
        );

        return $request->withHeader(HttpHeaderName::pdsSecret(), $signature);
    }
}
