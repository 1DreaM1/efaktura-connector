<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

/** Provider acknowledgement for a submitted batch of PDS participant subjects. */
final class PdsSubjectResponse
{
    private ?int $code;
    private ?string $description;

    /**
     * Creates the provider response for a PDS subject submission.
     *
     * @param int|null    $code        Optional provider response code.
     * @param string|null $description Optional provider response description.
     */
    public function __construct(?int $code, ?string $description)
    {
        $this->code        = $code;
        $this->description = $description;
    }

    /** Returns the provider response code, when supplied. */
    public function code(): ?int
    {
        return $this->code;
    }

    /** Returns the provider response description, when supplied. */
    public function description(): ?string
    {
        return $this->description;
    }
}
