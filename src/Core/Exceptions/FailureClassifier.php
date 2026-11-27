<?php

namespace DreaM1\EfakturaConnector\Core\Exceptions;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\UblValidationException;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use Throwable;

/**
 * Maps a failure thrown by the connector to a FailureAssessment, so a host can decide between retrying,
 * checking the outcome, fixing configuration and failing a job without inspecting exception classes.
 *
 * The mapping is a pure function of the exception: it sends nothing and decides nothing for the host.
 * A failure that is not a recognized connector exception is assessed conservatively as an internal error
 * that is not retryable and may have reached the provider.
 *
 * @api
 */
final class FailureClassifier
{
    private const HTTP_REQUEST_TIMEOUT    = 408;
    private const HTTP_UNAUTHORIZED       = 401;
    private const HTTP_FORBIDDEN          = 403;
    private const HTTP_TOO_MANY_REQUESTS  = 429;
    private const HTTP_SERVER_ERROR_START = 500;

    /** Assesses any failure; connector exceptions get a specific reading, anything else a conservative one. */
    public static function classify(Throwable $failure): FailureAssessment
    {
        if ($failure instanceof ProviderApiException) {
            return self::classifyProviderFailure($failure);
        }

        if ($failure instanceof DocumentPreflightException) {
            return new FailureAssessment(FailureAssessment::CATEGORY_REJECTED, false, false, false);
        }

        if (
            $failure instanceof InvalidInputException
            || $failure instanceof UblValidationException
            || $failure instanceof RequestEncodingException
            || $failure instanceof RequestBodyTooLargeException
        ) {
            return new FailureAssessment(FailureAssessment::CATEGORY_INVALID_INPUT, false, false, false);
        }

        if (
            $failure instanceof ConfigurationException
            || $failure instanceof LocalValidationInfrastructureException
        ) {
            return new FailureAssessment(FailureAssessment::CATEGORY_CONFIGURATION, false, false, false);
        }

        if ($failure instanceof RequestCoordinationException) {
            return new FailureAssessment(FailureAssessment::CATEGORY_DEFERRED, true, false, false);
        }

        return new FailureAssessment(FailureAssessment::CATEGORY_INTERNAL, false, true, true);
    }

    private static function classifyProviderFailure(ProviderApiException $failure): FailureAssessment
    {
        if ($failure instanceof RequestDeferredException) {
            return new FailureAssessment(FailureAssessment::CATEGORY_DEFERRED, true, false, false, $failure->retryAt());
        }

        if ($failure instanceof CircuitBreakerOpenException) {
            return new FailureAssessment(FailureAssessment::CATEGORY_DEFERRED, true, false, false);
        }

        if ($failure instanceof TokenRequestException) {
            return self::classifyTokenFailure($failure);
        }

        if ($failure instanceof ProviderConnectionException) {
            $mayHaveReached = $failure->requestMayHaveReachedProvider();

            return new FailureAssessment(FailureAssessment::CATEGORY_TRANSIENT, true, $mayHaveReached, $mayHaveReached);
        }

        if ($failure instanceof ProviderErrorResponseException) {
            return self::classifyErrorResponse($failure);
        }

        if (
            $failure instanceof InvalidProviderResponseException
            || $failure instanceof ProviderResponseTooLargeException
        ) {
            return new FailureAssessment(FailureAssessment::CATEGORY_INVALID_RESPONSE, false, true, true);
        }

        $mayHaveReached = $failure->requestMayHaveReachedProvider();

        return new FailureAssessment(FailureAssessment::CATEGORY_INTERNAL, false, $mayHaveReached, $mayHaveReached);
    }

    /** The request waited for the token and was never sent, so only the cause of the token failure matters. */
    private static function classifyTokenFailure(TokenRequestException $failure): FailureAssessment
    {
        $previous = $failure->getPrevious();

        if (! $previous instanceof ProviderApiException) {
            return new FailureAssessment(FailureAssessment::CATEGORY_AUTHENTICATION, false, false, false);
        }

        $cause = self::classifyProviderFailure($previous);

        if ($cause->isRetryable()) {
            return new FailureAssessment(
                FailureAssessment::CATEGORY_TRANSIENT,
                true,
                false,
                false,
                $cause->retryAt()
            );
        }

        return new FailureAssessment(FailureAssessment::CATEGORY_AUTHENTICATION, false, false, false);
    }

    private static function classifyErrorResponse(ProviderErrorResponseException $failure): FailureAssessment
    {
        $status = $failure->rawStatusCode();

        if ($status === self::HTTP_UNAUTHORIZED || $status === self::HTTP_FORBIDDEN) {
            return new FailureAssessment(FailureAssessment::CATEGORY_AUTHENTICATION, false, true, false);
        }

        if ($status === self::HTTP_TOO_MANY_REQUESTS) {
            return new FailureAssessment(FailureAssessment::CATEGORY_TRANSIENT, true, true, false, $failure->retryAt());
        }

        if ($status === self::HTTP_REQUEST_TIMEOUT || ($status !== null && $status >= self::HTTP_SERVER_ERROR_START)) {
            return new FailureAssessment(FailureAssessment::CATEGORY_TRANSIENT, true, true, true, $failure->retryAt());
        }

        return new FailureAssessment(FailureAssessment::CATEGORY_REJECTED, false, true, false);
    }
}
