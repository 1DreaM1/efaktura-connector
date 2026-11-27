<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Support\TypedArray;

/** Typed collection of PDS subjects submitted in one Integrator API request. */
final class PdsSubjectBatch
{
    /** @var PdsSubject[] */
    private array $subjects;

    /** @param PdsSubject[] $subjects Subjects to submit. */
    public function __construct(array $subjects)
    {
        $this->subjects = TypedArray::valuesOfType(
            $subjects,
            PdsSubject::class,
            'PDS subject batch items must be PdsSubject values.'
        );
    }

    /** @return PdsSubject[] */
    public function subjects(): array
    {
        return $this->subjects;
    }
}
