<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization;

use DreaM1\EfakturaConnector\Core\Documents\OutboundGatewayDocument;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentsRequest;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestBodyTooLargeException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Request body mapper for POST /v1/send-documents. */
final class SendDocumentsRequestMapper
{
    /** Prevents instantiation of this static request-body schema. */
    private function __construct()
    {
    }

    /**
     * Maps the Core batch request to the Gateway JSON shape.
     *
     * @return array<string, mixed>
     */
    public static function fromRequest(SendDocumentsRequest $request): array
    {
        $documents = [];

        foreach ($request->documents() as $document) {
            $documents[] = self::documentItem($document);
        }

        return ['documents' => $documents];
    }

    /**
     * Encodes the batch incrementally and stops before the serialized body exceeds its byte limit.
     *
     * @param  SendDocumentsRequest $request
     * @param  int                  $maxBytes Maximum serialized body size in bytes.
     * @return string
     */
    public static function encode(SendDocumentsRequest $request, int $maxBytes): string
    {
        if ($maxBytes < 1) {
            throw new InvalidInputException('The maximum request body size must be positive.');
        }

        $prefix   = '{"documents":[';
        $suffix   = ']}';
        $body     = $prefix;
        $bodySize = strlen($prefix) + strlen($suffix);

        if ($bodySize > $maxBytes) {
            throw new RequestBodyTooLargeException($maxBytes);
        }

        $encoder = new ApiJsonEncoder();
        $isFirst = true;

        foreach ($request->documents() as $document) {
            $encodedItem = $encoder->encode(self::documentItem($document));
            $separator   = $isFirst ? '' : ',';
            $nextSize    = $bodySize + strlen($separator) + strlen($encodedItem);

            if ($nextSize > $maxBytes) {
                throw new RequestBodyTooLargeException($maxBytes);
            }

            $body .= $separator . $encodedItem;
            $bodySize = $nextSize;
            $isFirst  = false;
        }

        return $body . $suffix;
    }

    /** @return array<string, string> */
    private static function documentItem(OutboundGatewayDocument $document): array
    {
        $item = [
            'type'    => $document->type(),
            'payload' => $document->payload(),
        ];

        $receiver = $document->receiverParticipant();

        if ($receiver !== null) {
            $item['receiverParticipantId'] = $receiver->identifier();
        }

        return $item;
    }
}
