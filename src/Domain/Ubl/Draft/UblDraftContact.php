<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

/** cac:Contact details for a seller or buyer. */
final class UblDraftContact
{
    /** Contact person's name, written to cac:Contact/cbc:Name. */
    private ?string $name;
    /** Contact telephone, written to cac:Contact/cbc:Telephone. */
    private ?string $telephone;
    /** Contact email address, written to cac:Contact/cbc:ElectronicMail. */
    private ?EmailAddress $email;

    /**
     * Creates optional contact details for a seller or buyer.
     *
     * @param string|null       $name      Contact person's name.
     * @param string|null       $telephone Contact telephone number.
     * @param EmailAddress|null $email     Contact email address.
     */
    public function __construct(?string $name = null, ?string $telephone = null, ?EmailAddress $email = null)
    {
        $this->name      = UblDraftText::emptyToNull($name);
        $this->telephone = UblDraftText::emptyToNull($telephone);
        $this->email     = $email;
    }

    /** Returns the contact name, or null when it is omitted. */
    public function name(): ?string
    {
        return $this->name;
    }

    /** Returns the contact telephone number, or null when it is omitted. */
    public function telephone(): ?string
    {
        return $this->telephone;
    }

    /** Returns the contact email address, or null when it is omitted. */
    public function email(): ?EmailAddress
    {
        return $this->email;
    }
}
