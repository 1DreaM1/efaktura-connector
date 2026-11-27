<?php

/**
 * Provides synthetic submission and tracking adapters for the local retry simulation.
 *
 * The returned map contains a document-submission gateway and a document-tracking provider. Both
 * adapters append to the same event log under its adjacent lock file so the simulation can compare calls.
 */

use DreaM1\EfakturaConnector\Core\Contracts\DocumentSubmissionGatewayInterface;
use DreaM1\EfakturaConnector\Core\Contracts\DocumentTrackingProviderInterface;
use DreaM1\EfakturaConnector\Core\Documents\DocumentSubmissionResult;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentRequest;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentIdentity;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentOutcome;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentParties;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentReference;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentStatus;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentTiming;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Core\MessageStatuses\MessageLevelStatusContent;
use DreaM1\EfakturaConnector\Core\MessageStatuses\ProviderMessageLevelStatusResponse;
use DreaM1\EfakturaConnector\Domain\EFaktura\MessageLevelStatusIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Support\Timestamp;
use DreaM1\EfakturaConnector\Tools\RepositoryToolDefaults;

require_once __DIR__ . '/../RepositoryToolDefaults.php';

$simulationLogPath  = getenv('EFAKTURA_SIMULATION_LOG');
$trackingStatusJson = getenv('EFAKTURA_SIMULATION_TRACKING_STATUS_SEQUENCE');

if (! is_string($simulationLogPath) || $simulationLogPath === '' || ! is_string($trackingStatusJson)) {
    throw new RuntimeException('The local simulation adapter configuration is incomplete.');
}

$trackingStatusSequence = json_decode(
    $trackingStatusJson,
    true,
    RepositoryToolDefaults::JSON_MAX_DEPTH,
    JSON_THROW_ON_ERROR
);

if (! is_array($trackingStatusSequence) || $trackingStatusSequence === []) {
    throw new RuntimeException('The local simulation tracking status sequence must not be empty.');
}

$expectedSequenceIndex = 0;

foreach ($trackingStatusSequence as $sequenceIndex => $statusCode) {
    if (
        $sequenceIndex !== $expectedSequenceIndex
        || ! is_int($statusCode)
        || $statusCode < 200
        || $statusCode > 599
    ) {
        throw new RuntimeException('The local simulation tracking status sequence contains an invalid entry.');
    }

    $expectedSequenceIndex++;
}

$gateway = new class ($simulationLogPath) implements DocumentSubmissionGatewayInterface {
    private string $logPath;

    /** @param string $logPath Shared JSON event log; its .lock sidecar serializes access. */
    public function __construct(string $logPath)
    {
        $this->logPath = $logPath;
    }

    /**
     * {@inheritdoc}
     *
     * Records synthetic provider acceptance before simulating configured response-loss timeouts.
     */
    public function send(SendDocumentRequest $request): DocumentSubmissionResult
    {
        $payload            = $request->payload();
        $sendTimeoutCount   = (int) getenv('EFAKTURA_SIMULATION_SEND_TIMEOUT_COUNT');
        $providerDocumentId = getenv('EFAKTURA_SIMULATION_SEND_PROVIDER_ID');
        $responseStatus     = getenv('EFAKTURA_SIMULATION_SEND_RESPONSE_STATUS');

        if (! is_string($providerDocumentId) || $providerDocumentId === '') {
            $providerDocumentId = 'SIMULATED-PROVIDER-ID';
        }

        if (! is_string($responseStatus) || $responseStatus === '') {
            $responseStatus = 'ACCEPTED';
        }

        $idempotencyKeyValue = $request->idempotencyKey()->value();

        // Record the provider's receipt before throwing below to model a request accepted with its response lost.
        $event = $this->appendEvent('send', function (int $sendNumber) use (
            $idempotencyKeyValue,
            $payload,
            $providerDocumentId,
            $sendTimeoutCount,
            $responseStatus
        ): array {
            return [
                'idempotency_key'               => $idempotencyKeyValue,
                'document_id'                   => $payload->documentId(),
                'document_type_id'              => $payload->documentTypeId()->value(),
                'process_id'                    => $payload->processId()->value(),
                'sender'                        => $payload->sender()->identifier(),
                'receiver'                      => $payload->receiver()->identifier(),
                'creation_datetime'             => $payload->creationDateTime()->value(),
                'xml'                           => $payload->xml(),
                'checksum'                      => $payload->checksum(),
                'response_provider_document_id' => $sendNumber <= $sendTimeoutCount ? null : $providerDocumentId,
                'response_status'               => $sendNumber <= $sendTimeoutCount ? null : $responseStatus,
            ];
        });
        $sendNumber = $event['number'];

        // The application sees a timeout even though the simulated provider event already contains its document ID.
        if ($sendNumber <= $sendTimeoutCount) {
            throw new RuntimeException('Simulated timeout after submission request.');
        }

        return new DocumentSubmissionResult(
            ProviderDocumentId::fromString(
                $event['response_provider_document_id']
            ),
            ProviderDocumentStatus::fromString(
                $event['response_status']
            ),
            Timestamp::fromIso8601('2026-11-23T12:00:00Z')
        );
    }

    /**
     * Appends one numbered event while holding the shared log lock.
     *
     * @param  string                              $type         Event category, such as send or tracking.
     * @param  callable(int): array<string, mixed> $eventFactory Builds the event fields for its sequence number.
     * @return array<string, mixed>                Event after adding its type and sequence number.
     */
    private function appendEvent(string $type, callable $eventFactory): array
    {
        // Serialize the read-modify-write so concurrent child processes get unique event numbers and intact logs.
        $lock = fopen($this->logPath . '.lock', 'c+');

        if (! is_resource($lock) || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Could not lock the simulation event log.');
        }

        try {
            $events = is_file($this->logPath)
                ? json_decode(
                    (string) file_get_contents($this->logPath),
                    true,
                    RepositoryToolDefaults::JSON_MAX_DEPTH,
                    JSON_THROW_ON_ERROR
                )
                : [];
            $number = count(array_filter($events, static function (array $event) use ($type): bool {
                return $event['type'] === $type;
            })) + 1;
            $event           = $eventFactory($number);
            $event['type']   = $type;
            $event['number'] = $number;
            $events[]        = $event;

            if (file_put_contents($this->logPath, json_encode($events, JSON_PRETTY_PRINT)) === false) {
                throw new RuntimeException('Could not write the simulation event log.');
            }

            return $event;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
};

$trackingProvider = new class ($simulationLogPath, $trackingStatusSequence) implements DocumentTrackingProviderInterface {
    private string $logPath;
    /** @var int[] HTTP status codes returned by successive tracking calls. */
    private array $trackingStatusSequence;

    /**
     * @param string $logPath                Shared JSON event log; its .lock sidecar serializes access.
     * @param int[]  $trackingStatusSequence HTTP statuses returned in order; the last repeats after the sequence ends.
     */
    public function __construct(string $logPath, array $trackingStatusSequence)
    {
        $this->logPath                = $logPath;
        $this->trackingStatusSequence = $trackingStatusSequence;
    }

    /**
     * {@inheritdoc}
     *
     * Records the call and returns a result for a 2xx status; non-2xx statuses raise a provider error.
     */
    public function getSentDocumentStatus(
        SentDocumentReference $document
    ): SentDocumentStatus {
        $event = $this->appendEvent('tracking', function (int $trackingNumber) use ($document): array {
            // Once the configured sequence ends, repeat its last status for any additional tracking call.
            $sequenceIndex = min($trackingNumber - 1, count($this->trackingStatusSequence) - 1);
            $statusCode    = $this->trackingStatusSequence[$sequenceIndex];

            return [
                'provider_document_id' => $document->identifier()->value(),
                'status_code'          => $statusCode,
            ];
        });
        $statusCode = $event['status_code'];

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ProviderErrorResponseException(
                $statusCode
            );
        }

        return SentDocumentStatus::create(
            ProviderDocumentStatus::fromString('QUEUED'),
            new SentDocumentParties($document->participant()),
            new SentDocumentTiming(null, Timestamp::fromIso8601('2026-11-23T12:01:00Z')),
            new SentDocumentIdentity($document->identifier()),
            new SentDocumentOutcome()
        );
    }

    /**
     * {@inheritdoc}
     *
     * Message-level status retrieval is unsupported by this simulation and raises a logic exception.
     */
    public function getMessageLevelStatuses(
        SentDocumentReference $document
    ): ProviderMessageLevelStatusResponse {
        throw new LogicException('Not used by this simulation.');
    }

    /**
     * {@inheritdoc}
     *
     * Message-level status downloads are unsupported by this simulation and raise a logic exception.
     */
    public function downloadMessageLevelStatus(
        SentDocumentReference $document,
        MessageLevelStatusIdentifier $statusIdentifier
    ): MessageLevelStatusContent {
        throw new LogicException('Not used by this simulation.');
    }

    /**
     * Appends one numbered event while holding the shared log lock.
     *
     * @param  string                              $type         Event category, such as send or tracking.
     * @param  callable(int): array<string, mixed> $eventFactory Builds the event fields for its sequence number.
     * @return array<string, mixed>                Event after adding its type and sequence number.
     */
    private function appendEvent(string $type, callable $eventFactory): array
    {
        // Use the same lock as the gateway adapter so both event types remain ordered in the shared log.
        $lock = fopen($this->logPath . '.lock', 'c+');

        if (! is_resource($lock) || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Could not lock the simulation event log.');
        }

        try {
            $events = is_file($this->logPath)
                ? json_decode(
                    (string) file_get_contents($this->logPath),
                    true,
                    RepositoryToolDefaults::JSON_MAX_DEPTH,
                    JSON_THROW_ON_ERROR
                )
                : [];
            $number = count(array_filter($events, static function (array $event) use ($type): bool {
                return $event['type'] === $type;
            })) + 1;
            $event           = $eventFactory($number);
            $event['type']   = $type;
            $event['number'] = $number;
            $events[]        = $event;

            if (file_put_contents($this->logPath, json_encode($events, JSON_PRETTY_PRINT)) === false) {
                throw new RuntimeException('Could not write the simulation event log.');
            }

            return $event;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
};

return [
    'gateway'           => $gateway,
    'tracking_provider' => $trackingProvider,
];
