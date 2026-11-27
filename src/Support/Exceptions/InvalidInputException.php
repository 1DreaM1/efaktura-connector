<?php

namespace DreaM1\EfakturaConnector\Support\Exceptions;

use InvalidArgumentException;

/**
 * A value supplied to the connector is malformed, out of range, or otherwise unacceptable.
 *
 * @api
 */
class InvalidInputException extends InvalidArgumentException implements ConnectorThrowable
{
}
