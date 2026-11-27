<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi;

/** Route templates declared by the SAPI contract. */
final class SapiApiPaths
{
    /** Path for requesting an OAuth access token. */
    public const TOKEN = '/sapi/auth/token';
    /** Path for retrieving the current OAuth token status. */
    public const TOKEN_STATUS = '/sapi/auth/token/status';
    /** Path for submitting one outbound document. */
    public const SEND_DOCUMENT = '/sapi/document/send';
    /** Collection path for listing received documents. */
    public const RECEIVED_DOCUMENTS = '/sapi/document/receive';
    /** Path for retrieving one received document. */
    public const RECEIVED_DOCUMENT = self::RECEIVED_DOCUMENTS . '/{documentId}';
    /** Path for acknowledging receipt of one document. */
    public const ACKNOWLEDGE_RECEIVED_DOCUMENT = self::RECEIVED_DOCUMENT . '/acknowledge';
}
