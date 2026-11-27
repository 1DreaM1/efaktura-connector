<?php

/**
 * Checks Core and Domain, plus explicitly @api declarations, against the reviewed API baseline.
 *
 * Laravel declarations are included when the Laravel framework is available. Use --update only after
 * reviewing an intentional public API change.
 */

$packageRoot  = dirname(__DIR__);
$autoloadFile = getenv('EFAKTURA_CONNECTOR_AUTOLOAD');

if ($autoloadFile === false || $autoloadFile === '') {
    $autoloadFile = $packageRoot . '/vendor/autoload.php';
}

require $autoloadFile;

$baselinePath = $packageRoot . '/tests/Architecture/public-api.json';
$roots        = [
    'DreaM1\\EfakturaConnector\\Core\\'   => $packageRoot . '/src/Core',
    'DreaM1\\EfakturaConnector\\Domain\\' => $packageRoot . '/src/Domain',
];
$laravelFrameworkAvailable = class_exists('Illuminate\\Console\\Command');

if ($laravelFrameworkAvailable) {
    $roots['DreaM1\\EfakturaConnector\\Laravel\\'] = $packageRoot . '/integrations/laravel/src';
}

/**
 * Finds non-internal class, interface, and trait declarations under a source directory.
 *
 * @param  string   $directory Source directory to scan recursively.
 * @return string[] Fully qualified declaration names.
 */
function publicApiDeclarations(string $directory, bool $onlyApiDeclarations = false): array
{
    $declarations = [];
    $iterator     = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        $directory,
        FilesystemIterator::SKIP_DOTS
    ));

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen(dirname($directory)) + 1));

        if (
            strpos($relativePath, 'Core/Configuration/Internal/') === 0
            || strpos($relativePath, 'src/Configuration/Internal/') === 0
        ) {
            continue;
        }

        $tokens    = token_get_all(file_get_contents($file->getPathname()));
        $namespace = '';
        $count     = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if (! is_array($token) || $token[0] !== T_NAMESPACE) {
                continue;
            }

            $namespace = '';
            for ($namespaceIndex = $index + 1; $namespaceIndex < $count; $namespaceIndex++) {
                $namespaceToken = $tokens[$namespaceIndex];

                if ($namespaceToken === ';' || $namespaceToken === '{') {
                    break;
                }

                if (is_array($namespaceToken)) {
                    $namespace .= $namespaceToken[1];
                }
            }

            $namespace = trim($namespace);
        }

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if (! is_array($token) || ! in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
                continue;
            }

            $previousIndex             = $index - 1;
            $declarationModifierTokens = [T_ABSTRACT, T_FINAL];

            if (defined('T_READONLY')) {
                $declarationModifierTokens[] = constant('T_READONLY');
            }

            $isInternal = false;
            $isApi      = false;
            while (
                $previousIndex >= 0
                && is_array($tokens[$previousIndex])
                && (
                    in_array($tokens[$previousIndex][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
                    || in_array($tokens[$previousIndex][0], $declarationModifierTokens, true)
                )
            ) {
                if (
                    $tokens[$previousIndex][0] === T_DOC_COMMENT
                    && strpos($tokens[$previousIndex][1], '@internal') !== false
                ) {
                    $isInternal = true;
                }

                if (
                    $tokens[$previousIndex][0] === T_DOC_COMMENT
                    && preg_match('/@api\b/', $tokens[$previousIndex][1]) === 1
                ) {
                    $isApi = true;
                }

                $previousIndex--;
            }

            if ($isInternal || ($onlyApiDeclarations && ! $isApi)) {
                continue;
            }

            // Ignore anonymous classes and ::class references.
            $previous = $tokens[$previousIndex] ?? null;

            if (
                $previous === T_NEW
                || (is_array($previous) && in_array($previous[0], [T_NEW, T_DOUBLE_COLON], true))
            ) {
                continue;
            }

            $nameIndex = $index + 1;
            while (
                $nameIndex < $count
                && is_array($tokens[$nameIndex])
                && in_array($tokens[$nameIndex][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ) {
                $nameIndex++;
            }

            if (isset($tokens[$nameIndex]) && is_array($tokens[$nameIndex]) && $tokens[$nameIndex][0] === T_STRING) {
                $declarations[] = $namespace . '\\' . $tokens[$nameIndex][1];
            }
        }
    }

    return $declarations;
}

/**
 * Converts a reflected PHP type to the normalized value stored in the API snapshot.
 *
 * @param  ReflectionType|null                                         $type Reflected parameter, property, or return type.
 * @return array{name: string, builtin?: bool, allowsNull?: bool}|null
 */
function publicApiType(?ReflectionType $type): ?array
{
    if ($type === null) {
        return null;
    }

    if (! $type instanceof ReflectionNamedType) {
        return ['name' => (string) $type];
    }

    return [
        'name'       => $type->getName(),
        'builtin'    => $type->isBuiltin(),
        'allowsNull' => $type->allowsNull(),
    ];
}

/**
 * Returns interfaces declared directly by a class or interface, excluding inherited interfaces.
 *
 * @param  ReflectionClass $reflection Declaration to inspect.
 * @return string[]        Sorted fully qualified interface names.
 */
function publicApiDirectInterfaces(ReflectionClass $reflection): array
{
    $interfaces = $reflection->getInterfaceNames();
    $inherited  = [];
    $parent     = $reflection->getParentClass();

    if ($parent) {
        $inherited = $parent->getInterfaceNames();
    } elseif ($reflection->isInterface()) {
        foreach ($interfaces as $interface) {
            $inherited = array_merge($inherited, (new ReflectionClass($interface))->getInterfaceNames());
        }
    }

    $interfaces = array_values(array_diff($interfaces, $inherited));
    sort($interfaces, SORT_STRING);

    return $interfaces;
}

/**
 * Builds the normalized public signature snapshot for the configured namespace roots.
 *
 * @param  array<string, string>               $roots Namespace prefixes mapped to source directories.
 * @return array<string, array<string, mixed>> Snapshots keyed by fully qualified declaration name.
 */
function publicApiSignature(array $roots, ?string $annotatedDirectory = null): array
{
    $declarations = [];

    foreach ($roots as $directory) {
        $declarations = array_merge($declarations, publicApiDeclarations($directory));
    }

    if ($annotatedDirectory !== null) {
        $declarations = array_merge($declarations, publicApiDeclarations($annotatedDirectory, true));
    }

    $declarations = array_values(array_unique($declarations));
    sort($declarations, SORT_STRING);

    $snapshot = [];

    foreach ($declarations as $name) {
        if (! class_exists($name) && ! interface_exists($name) && ! trait_exists($name)) {
            throw new RuntimeException('Unable to load public API declaration ' . $name . '.');
        }

        $reflection = new ReflectionClass($name);
        $constants  = [];

        foreach ($reflection->getReflectionConstants() as $constant) {
            if (! $constant->isPublic() || $constant->getDeclaringClass()->getName() !== $name) {
                continue;
            }

            $constants[$constant->getName()] = $constant->getValue();
        }

        ksort($constants, SORT_STRING);

        $properties        = [];
        $defaultProperties = $reflection->getDefaultProperties();

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== $name) {
                continue;
            }

            $properties[$property->getName()] = [
                'static'     => $property->isStatic(),
                'type'       => publicApiType($property->hasType() ? $property->getType() : null),
                'hasDefault' => array_key_exists($property->getName(), $defaultProperties),
                'default'    => $defaultProperties[$property->getName()] ?? null,
            ];
        }

        ksort($properties, SORT_STRING);

        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $name) {
                continue;
            }

            $parameters = [];

            foreach ($method->getParameters() as $parameter) {
                $parameters[] = [
                    'name'        => $parameter->getName(),
                    'type'        => publicApiType($parameter->hasType() ? $parameter->getType() : null),
                    'byReference' => $parameter->isPassedByReference(),
                    'variadic'    => $parameter->isVariadic(),
                    'optional'    => $parameter->isOptional(),
                    'default'     => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
                    'defaultName' => $parameter->isDefaultValueAvailable()
                        ? $parameter->getDefaultValueConstantName()
                        : null,
                ];
            }

            $methods[$method->getName()] = [
                'static'           => $method->isStatic(),
                'final'            => $method->isFinal(),
                'abstract'         => $method->isAbstract(),
                'returnsReference' => $method->returnsReference(),
                'returnType'       => publicApiType($method->hasReturnType() ? $method->getReturnType() : null),
                'parameters'       => $parameters,
            ];
        }

        ksort($methods, SORT_STRING);

        $interfaces = publicApiDirectInterfaces($reflection);
        $traits     = $reflection->getTraitNames();
        sort($traits, SORT_STRING);

        $snapshot[$name] = [
            'kind'       => $reflection->isInterface() ? 'interface' : ($reflection->isTrait() ? 'trait' : 'class'),
            'abstract'   => $reflection->isAbstract(),
            'final'      => $reflection->isFinal(),
            'parent'     => $reflection->getParentClass() ? $reflection->getParentClass()->getName() : null,
            'interfaces' => $interfaces,
            'traits'     => $traits,
            'constants'  => $constants,
            'properties' => $properties,
            'methods'    => $methods,
        ];
    }

    return $snapshot;
}

/**
 * Encodes one declaration per line so reviewed API changes produce focused diffs.
 *
 * @param  array<string, array<string, mixed>> $snapshot Signatures keyed by declaration name.
 * @return string                              JSON snapshot with a trailing newline.
 */
function encodePublicApiSnapshot(array $snapshot): string
{
    $lines   = ['{'];
    $names   = array_keys($snapshot);
    $lastKey = count($names) - 1;

    foreach ($names as $index => $name) {
        $encodedName = json_encode($name, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $encodedApi  = json_encode($snapshot[$name], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encodedName === false || $encodedApi === false) {
            throw new RuntimeException('Could not encode public API declaration ' . $name . '.');
        }

        $lines[] = '    ' . $encodedName . ': ' . $encodedApi . ($index === $lastKey ? '' : ',');
    }

    $lines[] = '}';

    return implode("\n", $lines) . "\n";
}

$snapshot = publicApiSignature($roots, $packageRoot . '/src');
$actual   = encodePublicApiSnapshot($snapshot);

if (in_array('--update', $argv, true)) {
    if (! $laravelFrameworkAvailable) {
        $existingBaseline = is_file($baselinePath) ? file_get_contents($baselinePath) : false;
        $existingSnapshot = $existingBaseline === false ? false : json_decode($existingBaseline, true);

        if (! is_array($existingSnapshot)) {
            fwrite(STDERR, "Cannot preserve the Laravel API baseline while updating Core and Domain without Laravel dependencies.\n");
            exit(1);
        }

        $laravelSnapshot = array_filter(
            $existingSnapshot,
            static function ($declaration): bool {
                return strpos($declaration, 'DreaM1\\EfakturaConnector\\Laravel\\') === 0;
            },
            ARRAY_FILTER_USE_KEY
        );
        $snapshot = array_merge($laravelSnapshot, $snapshot);
        ksort($snapshot, SORT_STRING);
        $actual = encodePublicApiSnapshot($snapshot);
    }

    if (file_put_contents($baselinePath, $actual) === false) {
        fwrite(STDERR, 'Could not write ' . $baselinePath . ".\n");
        exit(1);
    }

    fwrite(STDOUT, 'Updated ' . $baselinePath . "\n");
    exit(0);
}

$expected         = is_file($baselinePath) ? file_get_contents($baselinePath) : false;
$expectedSnapshot = $expected === false ? [] : json_decode($expected, true);

if (! is_array($expectedSnapshot)) {
    $expectedSnapshot = [];
}

if (! $laravelFrameworkAvailable) {
    $expectedSnapshot = array_filter(
        $expectedSnapshot,
        static function ($declaration): bool {
            return strpos($declaration, 'DreaM1\\EfakturaConnector\\Laravel\\') !== 0;
        },
        ARRAY_FILTER_USE_KEY
    );
    ksort($expectedSnapshot, SORT_STRING);
    $expected = encodePublicApiSnapshot($expectedSnapshot);
}

if ($expected === false || $expected !== $actual) {
    fwrite(STDERR, "The supported public API differs from its reviewed baseline.\n");

    if ($expectedSnapshot !== []) {
        $added   = array_diff(array_keys($snapshot), array_keys($expectedSnapshot));
        $removed = array_diff(array_keys($expectedSnapshot), array_keys($snapshot));
        $changed = [];

        foreach (array_intersect(array_keys($snapshot), array_keys($expectedSnapshot)) as $name) {
            if (
                json_encode($snapshot[$name], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                !== json_encode($expectedSnapshot[$name], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ) {
                $changed[] = $name;
            }
        }

        foreach (['Added' => $added, 'Removed' => $removed, 'Changed' => $changed] as $label => $declarations) {
            if ($declarations === []) {
                continue;
            }

            fwrite(STDERR, $label . " declarations:\n");

            foreach ($declarations as $declaration) {
                fwrite(STDERR, '  - ' . $declaration . "\n");
            }
        }
    }

    fwrite(STDERR, "Review the compatibility impact; use php tools/check-public-api.php --update only for an intentional change.\n");
    exit(1);
}

fwrite(STDOUT, 'Public API matches ' . count($snapshot) . " reviewed declarations.\n");

if (! $laravelFrameworkAvailable) {
    fwrite(STDOUT, "Laravel framework unavailable; Laravel declarations are checked in the isolated bridge CI job.\n");
}
