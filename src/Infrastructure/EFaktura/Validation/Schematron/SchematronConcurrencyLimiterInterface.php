<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Supplies a concurrency permit for a validation running under the given application directory.
 *
 * @api
 */
interface SchematronConcurrencyLimiterInterface
{
    /**
     * Waits for a validation slot and throws when none becomes available before the timeout.
     *
     * @param  string                                 $workingDirectory Directory used for local validation or process execution.
     * @param  int                                    $timeoutSeconds   Maximum time to wait for the operation, in seconds.
     * @return SchematronConcurrencyPermitInterface
     * @throws InvalidInputException                  when the timeout is negative
     * @throws LocalValidationInfrastructureException when file-lock support or a safe validation slot is unavailable
     */
    public function acquire(string $workingDirectory, int $timeoutSeconds): SchematronConcurrencyPermitInterface;
}
