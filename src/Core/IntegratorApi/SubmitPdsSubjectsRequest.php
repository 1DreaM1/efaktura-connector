<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

/** PDS participant subjects and signing secret for an Integrator API request. */
final class SubmitPdsSubjectsRequest
{
    private PdsSubjectBatch $subjects;
    private PdsSigningSecret $signingSecret;

    /**
     * Creates a request from typed PDS subjects and their signing secret.
     *
     * @param PdsSubjectBatch  $subjects      PDS subjects to submit.
     * @param PdsSigningSecret $signingSecret Secret used to sign the request body.
     */
    public function __construct(PdsSubjectBatch $subjects, PdsSigningSecret $signingSecret)
    {
        $this->subjects      = $subjects;
        $this->signingSecret = $signingSecret;
    }

    /** Returns the PDS subjects to submit. */
    public function subjects(): PdsSubjectBatch
    {
        return $this->subjects;
    }

    /** Returns the secret used to sign the outgoing request body. */
    public function signingSecret(): PdsSigningSecret
    {
        return $this->signingSecret;
    }
}
