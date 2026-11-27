<?php

namespace DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks;

/** Size limits for incoming provider webhook payloads. */
final class WebhookEventLimits
{
    /** Maximum accepted provider webhook payload size in bytes. */
    public const MAX_PAYLOAD_SIZE_BYTES = 1024 * 1024;
}
