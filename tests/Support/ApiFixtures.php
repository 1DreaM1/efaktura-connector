<?php

namespace DreaM1\EfakturaConnector\Tests\Support;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;

/**
 * Synthetic provider payloads in the exact shape of the OpenAPI schemas.
 * Values are fake; shapes follow docs/eFaktura/api/openapi.json and real test-API responses.
 */
final class ApiFixtures
{
    public const TIMESTAMP        = '2026-11-12T19:29:58.291Z';
    public const DOCUMENT_TYPE_ID = 'busdox-docid-qns::urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice'
        . '##urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0::2.1';
    public const PROCESS_ID = 'cenbii-procid-ubl::urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    /**
     * Creates a JSON HTTP exchange result for a synthetic provider response.
     *
     * @param  array<string, mixed> $data   Decoded provider response body.
     * @param  int                  $status HTTP status code to attach to the response.
     * @return HttpExchangeResult   Synthetic exchange with an application/json content type.
     */
    public static function jsonExchange(array $data, int $status = 200): HttpExchangeResult
    {
        return new HttpExchangeResult(
            $status,
            json_encode($data),
            ['content-type' => 'application/json'],
            microtime(true)
        );
    }

    /**
     * Creates an XML HTTP exchange result for a synthetic provider response.
     *
     * @param  string             $xml    XML response body.
     * @param  int                $status HTTP status code to attach to the response.
     * @return HttpExchangeResult Synthetic exchange with an application/xml content type.
     */
    public static function xmlExchange(string $xml, int $status = 200): HttpExchangeResult
    {
        return new HttpExchangeResult(
            $status,
            $xml,
            ['content-type' => 'application/xml'],
            microtime(true)
        );
    }

    /**
     * Creates a synthetic SAPI access-token response.
     *
     * @param  array<string, mixed> $overrides Values that replace the default response fields.
     * @return array<string, mixed> Provider token response body.
     */
    public static function token(array $overrides = []): array
    {
        return array_merge([
            'access_token' => 'synthetic-access-token',
            'token_type'   => 'Bearer',
            'expires_in'   => 900,
            'scope'        => 'document:send document:receive',
        ], $overrides);
    }

    /**
     * Creates a synthetic SAPI token-status response.
     *
     * @param  bool                 $valid Whether the token status should be reported as valid.
     * @return array<string, mixed> Provider token-status response body.
     */
    public static function tokenStatus(bool $valid = true): array
    {
        return [
            'valid'                  => $valid,
            'token_type'             => 'access',
            'client_id'              => 'synthetic-client',
            'issued_at'              => '2026-11-18T20:45:26Z',
            'expires_at'             => '2026-11-18T21:00:26Z',
            'expires_in_seconds'     => 900,
            'should_refresh'         => false,
            'refresh_recommended_at' => '2026-11-18T20:57:26Z',
        ];
    }

    /**
     * Creates synthetic received-document metadata.
     *
     * @param  string               $documentId            Provider document identifier.
     * @param  string               $senderParticipantId   Sender participant identifier.
     * @param  string               $receiverParticipantId Receiver participant identifier.
     * @return array<string, mixed> Provider document metadata object.
     */
    public static function documentMetadata(
        string $documentId,
        string $senderParticipantId = '0245:2012345699',
        string $receiverParticipantId = '0245:2012345676'
    ): array {
        return [
            'documentId'            => $documentId,
            'documentTypeId'        => self::DOCUMENT_TYPE_ID,
            'processId'             => self::PROCESS_ID,
            'senderParticipantId'   => $senderParticipantId,
            'receiverParticipantId' => $receiverParticipantId,
            'creationDateTime'      => self::TIMESTAMP,
        ];
    }

    /**
     * Creates a synthetic received-document page.
     *
     * @param  string[]             $documentIds   Provider document identifiers to include.
     * @param  string|null          $nextPageToken Optional token for the following page.
     * @return array<string, mixed> Provider document-list response body.
     */
    public static function receivedDocumentList(array $documentIds, ?string $nextPageToken = null): array
    {
        $data = ['documents' => array_map([self::class, 'documentMetadata'], $documentIds)];

        if ($nextPageToken !== null) {
            $data['nextPageToken'] = $nextPageToken;
        }

        return $data;
    }

    /**
     * Creates a synthetic received-document detail response.
     *
     * @param  string               $xml           Document payload content.
     * @param  string               $documentId    Provider document identifier.
     * @param  string               $payloadFormat Format label reported by the provider.
     * @return array<string, mixed> Provider document-detail response body.
     */
    public static function receivedDocumentDetail(
        string $xml,
        string $documentId = 'provider-doc-1',
        string $payloadFormat = 'XML'
    ): array {
        return [
            'metadata'      => self::documentMetadata($documentId),
            'payload'       => $xml,
            'payloadFormat' => $payloadFormat,
        ];
    }

    /**
     * Creates a synthetic document acknowledgement response.
     *
     * @param  string               $documentId Provider document identifier.
     * @return array<string, mixed> Provider acknowledgement response body.
     */
    public static function acknowledge(string $documentId = 'provider-doc-1'): array
    {
        return [
            'documentId'           => $documentId,
            'status'               => 'ACKNOWLEDGED',
            'acknowledgedDateTime' => self::TIMESTAMP,
        ];
    }

    /**
     * Creates a synthetic document submission response.
     *
     * @param  string               $providerDocumentId Provider-assigned document identifier.
     * @param  string               $status             Provider submission status.
     * @return array<string, mixed> Provider document-submission response body.
     */
    public static function sendDocument(string $providerDocumentId, string $status = 'ACCEPTED'): array
    {
        $data = [
            'providerDocumentId' => $providerDocumentId,
            'status'             => $status,
            'timestamp'          => self::TIMESTAMP,
        ];

        if ($status === 'ACCEPTED') {
            $data['receivedAt'] = self::TIMESTAMP;
        }

        return $data;
    }

    /**
     * Creates a synthetic webhook registry entry.
     *
     * @param  string               $id        Webhook identifier.
     * @param  array<string, mixed> $overrides Values that replace the default webhook fields.
     * @return array<string, mixed> Provider webhook object.
     */
    public static function webhook(string $id = 'wh-1', array $overrides = []): array
    {
        return array_merge([
            'id'          => $id,
            'url'         => 'https://erp.example.test/peppol/webhooks',
            'events'      => ['sent_document.status_changed', 'inbox_document.received'],
            'status'      => 'ACTIVE',
            'description' => 'Synthetic endpoint',
            'createdAt'   => self::TIMESTAMP,
            'updatedAt'   => self::TIMESTAMP,
        ], $overrides);
    }

    /**
     * Creates a synthetic webhook registration response.
     *
     * @param  string               $id            Webhook identifier.
     * @param  string               $signingSecret Signing secret returned at registration.
     * @return array<string, mixed> Provider webhook registration response body.
     */
    public static function webhookCreate(string $id = 'wh-1', string $signingSecret = 'synthetic-signing-secret'): array
    {
        return self::webhook($id) + ['signingSecret' => $signingSecret];
    }

    /**
     * Creates a synthetic webhook registry page.
     *
     * @param  array<int, array<string, mixed>> $webhooks      Webhook entries on the page.
     * @param  string|null                      $nextPageToken Optional token for the following page.
     * @return array<string, mixed>             Provider webhook-list response body.
     */
    public static function webhookList(array $webhooks, ?string $nextPageToken = null): array
    {
        return ['webhooks' => $webhooks, 'nextPageToken' => $nextPageToken];
    }

    /**
     * Creates a synthetic sent-document tracking response.
     *
     * @param  string               $status    Provider delivery status.
     * @param  array<string, mixed> $overrides Values that replace the default response fields.
     * @return array<string, mixed> Provider sent-document response body.
     */
    public static function sentDocument(string $status = 'SENT_TO_ACCESS_POINT', array $overrides = []): array
    {
        return array_merge([
            'identifier'            => 'provider-doc-1',
            'status'                => $status,
            'senderParticipantId'   => '0245:2012345676',
            'receiverParticipantId' => '0245:2012345699',
            'documentId'            => 'INV-SYNTHETIC-1',
            'documentTypeId'        => self::DOCUMENT_TYPE_ID,
            'processId'             => self::PROCESS_ID,
            'payloadFormat'         => 'UBL_XML',
            'createdAt'             => self::TIMESTAMP,
            'updatedAt'             => self::TIMESTAMP,
        ], $overrides);
    }

    /**
     * Creates a synthetic message-level status response.
     *
     * @param  string               $identifier   Provider status-record identifier.
     * @param  string               $responseCode Provider status code.
     * @param  array<string, mixed> $overrides    Values that replace the default response fields.
     * @return array<string, mixed> Provider message-level status body.
     */
    public static function messageLevelStatus(
        string $identifier = 'mls-1',
        string $responseCode = 'AP',
        array $overrides = []
    ): array {
        return array_merge([
            'identifier'            => $identifier,
            'messageId'             => 'msg-' . $identifier,
            'responseCode'          => $responseCode,
            'responseDescription'   => 'Synthetic description',
            'reasons'               => [],
            'senderParticipantId'   => '0245:2012345699',
            'receiverParticipantId' => '0245:2012345676',
            'documentTypeId'        => self::DOCUMENT_TYPE_ID,
            'receivedAt'            => self::TIMESTAMP,
        ], $overrides);
    }

    /**
     * Creates a synthetic Peppol participant capability.
     *
     * @return array<string, string> Document type, process and transport profile values.
     */
    public static function capability(): array
    {
        return [
            'documentTypeId'   => self::DOCUMENT_TYPE_ID,
            'processId'        => self::PROCESS_ID,
            'transportProfile' => 'peppol-transport-as4-v2_0',
        ];
    }

    /**
     * Creates a synthetic participant profile response.
     *
     * @param  string                            $participantId Participant identifier.
     * @param  bool                              $reachable     Whether the participant is reported as reachable.
     * @param  array<int, array<string, string>> $capabilities  Advertised document and process capabilities.
     * @return array<string, mixed>              Provider participant response body.
     */
    public static function participant(string $participantId, bool $reachable = true, array $capabilities = []): array
    {
        return [
            'participantId' => $participantId,
            'reachable'     => $reachable,
            'capabilities'  => $capabilities,
        ];
    }

    /**
     * Creates a synthetic receiving-discovery response.
     *
     * @param  string               $participantId Participant identifier queried for receiving support.
     * @param  bool                 $receives      Whether the provider reports receiving support.
     * @return array<string, mixed> Provider discovery response body.
     */
    public static function discovery(string $participantId, bool $receives = true): array
    {
        return [
            'participantId' => $participantId,
            'receives'      => $receives,
            'endpoint'      => $receives ? 'https://ap.example.test/as4' : null,
            'capability'    => $receives ? self::capability() : null,
        ];
    }

    /**
     * Creates a synthetic provider error response body.
     *
     * @param  string                           $message  Provider diagnostic message.
     * @param  array<int, array<string, mixed>> $details  Structured provider error details.
     * @param  string                           $category Provider error category.
     * @return array<string, mixed>             Provider error response body.
     */
    public static function error(string $message, array $details = [], string $category = 'VALIDATION'): array
    {
        return [
            'error' => [
                'category'       => $category,
                'code'           => 'SAPI-VAL-001',
                'message'        => $message,
                'details'        => $details,
                'retryable'      => false,
                'correlation_id' => '00000000-0000-4000-8000-000000000000',
            ],
        ];
    }
}
