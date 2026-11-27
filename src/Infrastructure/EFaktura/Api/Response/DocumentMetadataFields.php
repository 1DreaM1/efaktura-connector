<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response;

/** Shared provider document metadata field names used by API mappings. @internal */
final class DocumentMetadataFields
{
    /** Provider object name used in response-validation diagnostics. */
    public const OBJECT_NAME = 'DocumentMetadata';
    /** Provider field containing the document identifier. */
    public const DOCUMENT_ID = 'documentId';
    /** Provider field containing the Peppol document type identifier. */
    public const DOCUMENT_TYPE_ID = 'documentTypeId';
    /** Provider field containing the Peppol process identifier. */
    public const PROCESS_ID = 'processId';
    /** Provider field containing the sender participant identifier. */
    public const SENDER_PARTICIPANT_ID = 'senderParticipantId';
    /** Provider field containing the receiver participant identifier. */
    public const RECEIVER_PARTICIPANT_ID = 'receiverParticipantId';
    /** Provider field containing the document creation timestamp. */
    public const CREATION_DATE_TIME = 'creationDateTime';

    private function __construct()
    {
    }
}
