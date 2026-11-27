<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationProvenanceProviderInterface;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Support\HashAlgorithm;

/** Collects provenance from validation components and artifact files. */
final class UblValidationProvenance
{
    /**
     * Returns provenance supplied by the source, or an empty array when it is unsupported.
     *
     * @param  object                $source   Validation component that may provide provenance.
     * @param  UblXmlDocument        $document Document associated with the validation artifacts.
     * @return array<string, string>
     */
    public static function from(object $source, UblXmlDocument $document): array
    {
        if (! $source instanceof UblValidationProvenanceProviderInterface) {
            return [];
        }

        return $source->provenance($document);
    }

    /**
     * Adds SHA-256 fingerprints for readable artifacts.
     *
     * @param  array<string, string> $provenance Existing provenance values.
     * @param  array<string, string> $artifacts  Provenance keys mapped to artifact paths.
     * @return array<string, string>
     */
    public static function withFileHashes(array $provenance, array $artifacts): array
    {
        foreach ($artifacts as $key => $path) {
            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }

            $hash = hash_file(HashAlgorithm::SHA_256, $path);

            if (is_string($hash)) {
                $provenance[$key] = $hash;
            }
        }

        return $provenance;
    }
}
