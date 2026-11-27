<?php

namespace DreaM1\EfakturaConnector\Tests\Support;

use ReflectionMethod;
use ReflectionProperty;

/** Keeps reflection-based tests compatible with PHP 7.4 without deprecated calls on newer PHP versions. */
final class ReflectionAccess
{
    /**
     * Makes a reflected method or property accessible where PHP still requires it.
     *
     * @param ReflectionMethod|ReflectionProperty $reflection Reflected member to make accessible.
     */
    public static function makeAccessible($reflection): void
    {
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }
    }
}
