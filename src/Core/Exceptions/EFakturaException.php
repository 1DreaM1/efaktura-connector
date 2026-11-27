<?php

namespace DreaM1\EfakturaConnector\Core\Exceptions;

use DreaM1\EfakturaConnector\Support\Exceptions\ConnectorThrowable;
use RuntimeException;
use Throwable;

/**
 * Base of every connector failure.
 *
 * The failure is identified by its class and described by its typed accessors; the message is a technical
 * English text for logs. Wording for users is up to the host application. Families:
 *
 *   ProviderApiException                    SAPI / Peppol Gateway request failed or was locally deferred
 *   RequestEncodingException                a request body could not be encoded; nothing was sent
 *   RequestBodyTooLargeException             a request body exceeded its configured limit; nothing was sent
 *   ConfigurationException                  the connector is not configured; nothing was sent
 *   RequestCoordinationException             local HTTP request coordination could not be established
 *   DocumentPreflightException               a remote document check rejected the document
 *   UblValidationException                  the UBL document itself is invalid
 *   LocalValidationInfrastructureException  local validation could not run (Java, Saxon, …)
 *
 * Not every connector failure extends this class, because some also have to be a standard PHP exception type.
 * ConnectorThrowable is the one interface they all implement, so `catch (ConnectorThrowable)` handles every
 * failure the package raises on purpose:
 *
 *   InvalidInputException         an InvalidArgumentException: a value was rejected at an object boundary
 *   InvariantViolationException   a LogicException: an internal invariant broke (a bug or misuse of the package)
 *   XmlParseException             low-level secure XML parsing failed; converted to InvalidXmlException or
 *                                 InvalidProviderResponseException at the boundary
 *   LockStorageException          low-level lock file storage failed; converted to RequestCoordinationException or
 *                                 LocalValidationInfrastructureException at the boundary
 */
abstract class EFakturaException extends RuntimeException implements ConnectorThrowable
{
    /**
     * @param string         $message  Message or diagnostic text associated with the result.
     * @param Throwable|null $previous Previous exception that caused this failure, when available.
     */
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
