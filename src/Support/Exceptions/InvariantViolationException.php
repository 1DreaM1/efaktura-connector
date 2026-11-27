<?php

namespace DreaM1\EfakturaConnector\Support\Exceptions;

use LogicException;

/**
 * An internal invariant of the connector was broken, which indicates a bug in the package or its misuse.
 *
 * Unlike InvalidInputException it is never caused by a value the host supplied, and unlike ConfigurationException
 * the administrator cannot fix it by changing settings.
 *
 * @api
 */
final class InvariantViolationException extends LogicException implements ConnectorThrowable
{
}
