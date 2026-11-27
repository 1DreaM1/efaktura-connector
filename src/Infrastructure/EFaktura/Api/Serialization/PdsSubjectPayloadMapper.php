<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization;

use DreaM1\EfakturaConnector\Core\IntegratorApi\PdsSubject;
use DreaM1\EfakturaConnector\Core\IntegratorApi\SubmitPdsSubjectsRequest;

/**
 * Maps typed PDS subject requests to the Integrator API payload shape.
 *
 * @internal
 */
final class PdsSubjectPayloadMapper
{
    /**
     * Maps a typed request to a list of JSON objects using provider field names.
     *
     * Null subject fields are omitted to match the provider's request schema.
     *
     * @param  SubmitPdsSubjectsRequest $request Typed subjects and signing secret.
     * @return array<int, object>       JSON-serializable subject objects.
     */
    public static function toPayload(SubmitPdsSubjectsRequest $request): array
    {
        $subjects = [];

        foreach ($request->subjects()->subjects() as $subject) {
            $subjects[] = (object) self::subject($subject);
        }

        return $subjects;
    }

    /**
     * Maps one subject to provider field names and excludes fields without values.
     *
     * @param  PdsSubject            $subject Typed PDS subject.
     * @return array<string, string> Non-null provider fields.
     */
    private static function subject(PdsSubject $subject): array
    {
        $body = [];

        if ($subject->created() !== null) {
            $body['created'] = $subject->created()->value();
        }

        if ($subject->dic() !== null) {
            $body['dic'] = $subject->dic();
        }

        if ($subject->legalName() !== null) {
            $body['legalName'] = $subject->legalName();
        }

        if ($subject->companyEmail() !== null) {
            $body['company_email'] = $subject->companyEmail();
        }

        if ($subject->companyPhone() !== null) {
            $body['company_phone'] = $subject->companyPhone();
        }

        if ($subject->verificationToken() !== null) {
            $body['verification_token'] = $subject->verificationToken()->reveal();
        }

        return $body;
    }
}
