<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Core\Xml\XmlPayloadFormat;
use DreaM1\EfakturaConnector\Domain\EFaktura\CodeList\ParticipantIdentifierSchemeCodeList;
use DreaM1\EfakturaConnector\Domain\EFaktura\EFakturaDocumentLimits;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** One XML document item submitted through the Peppol Gateway batch endpoint. */
final class OutboundGatewayDocument
{
    /** @var string[] */
    private const SUPPORTED_TYPES = [XmlPayloadFormat::UBL_XML, XmlPayloadFormat::SBDH_XML];

    private string $type;
    private string $payload;
    private ?Participant $receiverParticipant;

    /**
     * Creates one Gateway document item.
     *
     * @param  string                $type                XML payload shape accepted by the Gateway.
     * @param  string                $payload             UTF-8 XML content.
     * @param  Participant|null      $receiverParticipant Receiver for UBL_XML, or an optional explicit receiver for SBDH_XML.
     * @throws InvalidInputException when the type, size, or required receiver is invalid
     */
    public function __construct(string $type, string $payload, ?Participant $receiverParticipant = null)
    {
        if (! in_array($type, self::SUPPORTED_TYPES, true)) {
            throw new InvalidInputException('Gateway document type must be UBL_XML or SBDH_XML.');
        }

        if (strlen($payload) > EFakturaDocumentLimits::MAX_XML_SIZE_BYTES) {
            throw new InvalidInputException(sprintf(
                'Gateway document payload exceeds the maximum allowed size of %d bytes.',
                EFakturaDocumentLimits::MAX_XML_SIZE_BYTES
            ));
        }

        if ($type === XmlPayloadFormat::UBL_XML && $receiverParticipant === null) {
            throw new InvalidInputException('UBL_XML documents require a receiver participant.');
        }

        if (
            $receiverParticipant !== null
            && ! ParticipantIdentifierSchemeCodeList::isAllowedForNewDocument($receiverParticipant->scheme())
        ) {
            throw new InvalidInputException('Receiver must use an active, registrable Peppol participant identifier scheme.');
        }

        $this->type                = $type;
        $this->payload             = $payload;
        $this->receiverParticipant = $receiverParticipant;
    }

    /** Returns the Gateway payload type. */
    public function type(): string
    {
        return $this->type;
    }

    /** Returns the XML document content. */
    public function payload(): string
    {
        return $this->payload;
    }

    /** Returns the explicit receiver, or null when it is derived from an SBDH payload. */
    public function receiverParticipant(): ?Participant
    {
        return $this->receiverParticipant;
    }
}
