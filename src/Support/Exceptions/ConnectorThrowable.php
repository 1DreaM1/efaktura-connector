<?php

namespace DreaM1\EfakturaConnector\Support\Exceptions;

use Throwable;

/**
 * Marker implemented by every exception the connector raises on purpose.
 *
 * Catch this interface to handle any connector failure, including invalid input, without also catching unrelated
 * PHP errors or host-application exceptions.
 *
 * @api
 */
interface ConnectorThrowable extends Throwable
{
}
