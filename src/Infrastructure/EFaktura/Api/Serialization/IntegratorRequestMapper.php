<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization;

use DreaM1\EfakturaConnector\Core\IntegratorApi\ActivateParticipantRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\CreateClientRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\GetDocumentSummaryRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorParticipantAccess;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ListClientsRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ListParticipantsRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ReplaceClientParticipantAccessRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PageQuery;

/** Maps typed Integrator API requests to provider body and query fields. */
final class IntegratorRequestMapper
{
    /**
     * Maps a client creation request to provider field names, omitting absent optional fields.
     *
     * @return array<string, mixed>
     */
    public static function createClientBody(CreateClientRequest $request): array
    {
        $body = [];

        if ($request->participants() !== null) {
            $body['participants'] = self::participantAccesses($request->participants());
        }

        if ($request->name() !== null) {
            $body['name'] = $request->name();
        }

        if ($request->contactEmail() !== null) {
            $body['contact_email'] = $request->contactEmail();
        }

        return $body;
    }

    /**
     * Maps a complete participant access replacement to provider field names.
     *
     * @return array{participants: array<int, array{participant_id: string, can_send: bool, can_receive: bool}>}
     */
    public static function replaceClientParticipantAccessBody(ReplaceClientParticipantAccessRequest $request): array
    {
        return ['participants' => self::participantAccesses($request->participants())];
    }

    /**
     * Maps a participant activation request to provider field names, omitting an absent user identifier.
     *
     * @return array<string, string>
     */
    public static function activateParticipantBody(ActivateParticipantRequest $request): array
    {
        $body = ['mode' => $request->mode()->value()];

        if ($request->requestedByUserId() !== null) {
            $body['requested_by_user_id'] = $request->requestedByUserId();
        }

        return $body;
    }

    /**
     * Maps client pagination and status filters to Integrator API query names.
     *
     * @return array<string, string|int>
     */
    public static function listClientsQuery(ListClientsRequest $request): array
    {
        $query = PageQuery::of($request->pageToken(), $request->limit());

        if ($request->status() !== null) {
            $query['status'] = $request->status()->value();
        }

        return $query;
    }

    /**
     * Maps participant pagination filters to Integrator API query names.
     *
     * @return array<string, string|int>
     */
    public static function listParticipantsQuery(ListParticipantsRequest $request): array
    {
        return PageQuery::of($request->pageToken(), $request->limit());
    }

    /**
     * Maps the billing month to the Integrator API query field.
     *
     * @return array{month: string}
     */
    public static function documentSummaryQuery(GetDocumentSummaryRequest $request): array
    {
        return ['month' => $request->month()];
    }

    /**
     * Maps typed participant permissions to provider field names.
     *
     * @param  IntegratorParticipantAccess[]                                                $participants
     * @return array<int, array{participant_id: string, can_send: bool, can_receive: bool}>
     */
    private static function participantAccesses(array $participants): array
    {
        return array_map(
            static function (IntegratorParticipantAccess $access): array {
                return [
                    'participant_id' => $access->participantId()->identifier(),
                    'can_send'       => $access->canSend(),
                    'can_receive'    => $access->canReceive(),
                ];
            },
            $participants
        );
    }
}
