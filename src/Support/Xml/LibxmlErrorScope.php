<?php

namespace DreaM1\EfakturaConnector\Support\Xml;

/** @internal Temporarily captures libxml errors and restores the previous global error mode on exit. */
final class LibxmlErrorScope
{
    /**
     * Runs an operation with libxml errors captured and restores the previous mode afterward.
     *
     * @template T
     * @param  callable(): T $operation Zero-argument callback whose result is returned after libxml state is restored.
     * @return T             The operation's return value.
     */
    public static function run(callable $operation)
    {
        // libxml error handling is process-global; remember the caller's mode before changing it.
        $previousMode = libxml_use_internal_errors(true);

        try {
            // Discard stale errors so this operation reports only errors raised within its scope.
            libxml_clear_errors();

            return $operation();
        } finally {
            // Restore global state even when parsing or the callback throws.
            libxml_clear_errors();
            libxml_use_internal_errors($previousMode);
        }
    }
}
