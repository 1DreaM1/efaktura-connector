<?php

namespace DreaM1\EfakturaConnector\Infrastructure;

/** Resolves package and configurable resource paths with platform-specific separators. */
final class ResourcePath
{
    /**
     * Resolves a relative path from the package root.
     *
     * @param  string $relativePath Relative resource path.
     * @return string Absolute package path using platform-specific separators.
     */
    public static function packageDirectory(string $relativePath): string
    {
        return self::join(dirname(__DIR__, 2), $relativePath);
    }

    /**
     * Resolves a relative path under a resource directory.
     *
     * @param  string $directory    Base directory.
     * @param  string $relativePath Relative path to append.
     * @return string Joined path using platform-specific separators.
     */
    public static function join(string $directory, string $relativePath): string
    {
        return rtrim($directory, '/\\') . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }
}
