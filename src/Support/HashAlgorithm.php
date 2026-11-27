<?php

namespace DreaM1\EfakturaConnector\Support;

/** Hash algorithm identifiers used throughout the connector. */
final class HashAlgorithm
{
    /** Hash algorithm identifier used by the connector. */
    public const SHA_256 = 'sha256';
    /** Hash algorithm identifier used to sign Integrator PDS requests. */
    public const SHA_512 = 'sha512';
}
