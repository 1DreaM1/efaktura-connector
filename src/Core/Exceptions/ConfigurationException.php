<?php

namespace DreaM1\EfakturaConnector\Core\Exceptions;

/**
 * The connector is not configured well enough to talk to the provider. Nothing was sent.
 *
 * Unlike ProviderApiException this is never caused by the provider; the administrator has to fix the setup.
 * The host application may extend it for failures of its own settings.
 * This exception is intentionally extensible.
 */
class ConfigurationException extends EFakturaException
{
}
