<?php

namespace DreaM1\EfakturaConnector\Core\MessageStatuses;

/** Downloaded XML content for a provider message-level status record. */
final class MessageLevelStatusContent
{
    private string $xml;

    /**
     * @param string $xml XML content returned for the provider message-level status.
     */
    public function __construct(string $xml)
    {
        $this->xml = $xml;
    }

    /**
     * Returns the XML.
     *
     * @return string
     */
    public function xml(): string
    {
        return $this->xml;
    }
}
