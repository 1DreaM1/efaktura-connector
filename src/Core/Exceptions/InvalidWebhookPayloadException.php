<?php

namespace DreaM1\EfakturaConnector\Core\Exceptions;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Raised when an externally supplied webhook payload cannot be parsed or validated. */
final class InvalidWebhookPayloadException extends InvalidInputException
{
}
