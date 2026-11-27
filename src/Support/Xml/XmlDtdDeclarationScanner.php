<?php

namespace DreaM1\EfakturaConnector\Support\Xml;

/**
 * Detects a document type declaration in an XML prolog before libxml parses the document.
 *
 * @internal
 */
final class XmlDtdDeclarationScanner
{
    /**
     * Whether the XML prolog contains a DOCTYPE declaration.
     *
     * Comments and processing instructions are skipped, so DTD-like text in ordinary markup data
     * does not cause a false positive. UTF-16 and UTF-32 byte-order forms are normalized for scanning;
     * input that cannot be converted cannot be inspected, so it is reported as containing one (fail closed).
     */
    public static function containsDocumentTypeDeclaration(string $xml): bool
    {
        $xml = self::normalizeForScan($xml);

        if ($xml === null) {
            return true;
        }

        if (strncmp($xml, "\xEF\xBB\xBF", 3) === 0) {
            $xml = substr($xml, 3);
        }

        $length = strlen($xml);
        $offset = 0;

        while ($offset < $length) {
            $character = $xml[$offset];

            if ($character === ' ' || $character === "\t" || $character === "\r" || $character === "\n") {
                $offset++;
                continue;
            }

            if (substr($xml, $offset, 4) === '<!--') {
                $end = strpos($xml, '-->', $offset + 4);

                if ($end === false) {
                    return false;
                }

                $offset = $end + 3;
                continue;
            }

            if (substr($xml, $offset, 2) === '<?') {
                $end = strpos($xml, '?>', $offset + 2);

                if ($end === false) {
                    return false;
                }

                $offset = $end + 2;
                continue;
            }

            // A DOCTYPE declaration is only valid before the root element, where scanning stops.
            return substr($xml, $offset, 9) === '<!DOCTYPE';
        }

        return false;
    }

    /**
     * Converts UTF-16/32 input to UTF-8 so the ASCII XML declaration can be inspected.
     *
     * @return string|null The scannable text, or null when the declared encoding could not be converted.
     */
    private static function normalizeForScan(string $xml): ?string
    {
        $prefix = substr($xml, 0, 4);

        if ($prefix === "\x00\x00\xFE\xFF") {
            return self::toUtf8(substr($xml, 4), 'UTF-32BE');
        }

        if ($prefix === "\xFF\xFE\x00\x00") {
            return self::toUtf8(substr($xml, 4), 'UTF-32LE');
        }

        if (strpos($xml, "\xFE\xFF") === 0) {
            return self::toUtf8(substr($xml, 2), 'UTF-16BE');
        }

        if (strpos($xml, "\xFF\xFE") === 0) {
            return self::toUtf8(substr($xml, 2), 'UTF-16LE');
        }

        if ($prefix === "\x00\x00\x00<") {
            return self::toUtf8($xml, 'UTF-32BE');
        }

        if ($prefix === "<\x00\x00\x00") {
            return self::toUtf8($xml, 'UTF-32LE');
        }

        if (strpos($xml, "\x00<") === 0) {
            return self::toUtf8($xml, 'UTF-16BE');
        }

        if (strpos($xml, "<\x00") === 0) {
            return self::toUtf8($xml, 'UTF-16LE');
        }

        return $xml;
    }

    private static function toUtf8(string $xml, string $fromEncoding): ?string
    {
        $converted = mb_convert_encoding($xml, 'UTF-8', $fromEncoding);

        return is_string($converted) ? $converted : null;
    }
}
