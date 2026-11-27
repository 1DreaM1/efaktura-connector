<?php

namespace DreaM1\EfakturaConnector\Tests\Architecture;

use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Support\Exceptions\ConnectorThrowable;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/** Enforces dependency boundaries between Core, Domain, Infrastructure and optional framework integrations. */
class EFakturaPackageBoundaryTest extends TestCase
{
    public function testApiLayerDoesNotDependOnThirdPartyHttpClients(): void
    {
        $apiDirectory        = ResourcePath::join(dirname(__DIR__, 2), 'src/Infrastructure/EFaktura/Api');
        $forbiddenNamespaces = [
            'GuzzleHttp\\',
            'Http\\Client\\',
            'Psr\\Http\\',
            'Symfony\\Component\\HttpClient\\',
        ];
        $violations = [];
        $files      = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $apiDirectory,
            FilesystemIterator::SKIP_DOTS
        ));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = $this->sourceWithoutCommentsAndStrings(file_get_contents($file->getPathname()));

            foreach ($forbiddenNamespaces as $namespace) {
                if (strpos($source, $namespace) !== false) {
                    $violations[] = $file->getPathname() . ' references ' . $namespace;
                }
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function testCoreAndDomainDoNotDependOnInfrastructureOrHttpClients(): void
    {
        $packageDirectory  = dirname(__DIR__, 2);
        $sourceDirectories = [
            'Core'   => ResourcePath::join($packageDirectory, 'src/Core'),
            'Domain' => ResourcePath::join($packageDirectory, 'src/Domain'),
        ];
        $forbiddenNamespaces = [
            'DreaM1\\EfakturaConnector\\Infrastructure\\',
            'GuzzleHttp\\',
            'Http\\Client\\',
            'Illuminate\\',
            'Laravel\\',
            'Psr\\Http\\',
            'Symfony\\Component\\HttpClient\\',
        ];
        $violations = [];

        foreach ($sourceDirectories as $layer => $sourceDirectory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                $sourceDirectory,
                FilesystemIterator::SKIP_DOTS
            ));

            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $source = $this->sourceWithoutCommentsAndStrings(file_get_contents($file->getPathname()));

                foreach ($forbiddenNamespaces as $namespace) {
                    if (strpos($source, $namespace) !== false) {
                        $violations[] = $layer . ' references ' . $namespace . ' in ' . $file->getPathname();
                    }
                }

                if (preg_match('/\\bcurl_[a-z0-9_]+\\s*\\(/i', $source)) {
                    $violations[] = $layer . ' calls a cURL function in ' . $file->getPathname();
                }
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function testDomainDoesNotDependOnCore(): void
    {
        $domainDirectory = ResourcePath::join(dirname(__DIR__, 2), 'src/Domain');
        $violations      = [];
        $files           = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $domainDirectory,
            FilesystemIterator::SKIP_DOTS
        ));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = $this->sourceWithoutCommentsAndStrings(file_get_contents($file->getPathname()));

            if (strpos($source, 'DreaM1\\EfakturaConnector\\Core\\') !== false) {
                $violations[] = $file->getPathname() . ' depends on the Core layer';
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function testCoreXmlAndCoreUblDoNotDependOnEachOther(): void
    {
        $boundaries = [
            [
                'Core XML',
                'src/Core/Xml',
                'DreaM1\\EfakturaConnector\\Core\\Ubl\\',
                'Core UBL',
            ],
            [
                'Core UBL',
                'src/Core/Ubl',
                'DreaM1\\EfakturaConnector\\Core\\Xml\\',
                'Core XML',
            ],
        ];
        $violations = [];

        foreach ($boundaries as [$sourceLayer, $relativeDirectory, $forbiddenNamespace, $targetLayer]) {
            $directory = ResourcePath::join(dirname(__DIR__, 2), $relativeDirectory);
            $files     = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS
            ));

            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $source = $this->sourceWithoutCommentsAndStrings(file_get_contents($file->getPathname()));

                if (strpos($source, $forbiddenNamespace) !== false) {
                    $violations[] = $sourceLayer . ' depends on ' . $targetLayer . ' in ' . $file->getPathname();
                }
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function testSharedXmlSupportDoesNotDependOnApplicationLayers(): void
    {
        $supportDirectory    = ResourcePath::join(dirname(__DIR__, 2), 'src/Support/Xml');
        $forbiddenNamespaces = [
            'DreaM1\\EfakturaConnector\\Core\\',
            'DreaM1\\EfakturaConnector\\Domain\\',
            'DreaM1\\EfakturaConnector\\Infrastructure\\',
            'DreaM1\\EfakturaConnector\\Laravel\\',
            'Illuminate\\',
        ];
        $violations = [];
        $files      = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $supportDirectory,
            FilesystemIterator::SKIP_DOTS
        ));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = $this->sourceWithoutCommentsAndStrings(file_get_contents($file->getPathname()));

            foreach ($forbiddenNamespaces as $namespace) {
                if (strpos($source, $namespace) !== false) {
                    $violations[] = $file->getPathname() . ' references ' . $namespace;
                }
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function testReceivedDocumentPayloadDoesNotDependOnUblReading(): void
    {
        $payloadFile = ResourcePath::join(
            dirname(__DIR__, 2),
            'src/Core/Documents/ReceivedDocumentPayload.php'
        );
        $source   = $this->sourceWithoutCommentsAndStrings(file_get_contents($payloadFile));
        $ublTypes = [
            'DreaM1\\EfakturaConnector\\Core\\Ubl\\Contracts\\UblDocumentReaderInterface',
            'DreaM1\\EfakturaConnector\\Domain\\Ubl\\UblDocument',
        ];
        $violations = [];

        foreach ($ublTypes as $type) {
            if (strpos($source, $type) !== false) {
                $violations[] = $payloadFile . ' references ' . $type;
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function testConnectorPackageSourceDoesNotDependOnProtapOrLaravel(): void
    {
        $sourceDirectory     = ResourcePath::join(dirname(__DIR__, 2), 'src');
        $forbiddenNamespaces = [
            'DreaM1\\Protap\\',
            'Illuminate\\',
            'Laravel\\',
        ];
        $forbiddenLaravelHelpers = [
            'app',
            'auth',
            'base_path',
            'cache',
            'config',
            'env',
            'logger',
            'public_path',
            'resource_path',
            'storage_path',
        ];
        $violations = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $sourceDirectory,
            FilesystemIterator::SKIP_DOTS
        ));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = $this->sourceWithoutCommentsAndStrings(file_get_contents($file->getPathname()));

            foreach ($forbiddenNamespaces as $namespace) {
                if (strpos($source, $namespace) !== false) {
                    $violations[] = $file->getPathname() . ' references ' . $namespace;
                }
            }

            foreach ($forbiddenLaravelHelpers as $helper) {
                if ($this->callsFunction($source, $helper)) {
                    $violations[] = $file->getPathname() . ' calls Laravel helper ' . $helper . '()';
                }
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function testConnectorPackageDoesNotOwnApplicationLogging(): void
    {
        $sourceDirectory = ResourcePath::join(dirname(__DIR__, 2), 'src');
        $files           = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $sourceDirectory,
            FilesystemIterator::SKIP_DOTS
        ));
        // Protocol-level redaction belongs to the connector; application persistence loggers do not.
        $applicationLoggingTypes = [
            'TransportLoggerInterface',
            'TransportLogEntry',
            'EFakturaLoggerInterface',
            'LaravelApiCommunicationLogger',
        ];
        $violations = [];

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = $this->sourceWithoutCommentsAndStrings(file_get_contents($file->getPathname()));

            foreach ($applicationLoggingTypes as $contract) {
                if (strpos($source, $contract) !== false) {
                    $violations[] = $file->getPathname() . ' references ' . $contract;
                }
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function testEveryExceptionClassImplementsConnectorThrowable(): void
    {
        $packageDirectory = dirname(__DIR__, 2);
        $violations       = [];
        $checked          = 0;

        foreach ($this->phpFiles(ResourcePath::join($packageDirectory, 'src')) as $file) {
            if (substr($file->getFilename(), -13) !== 'Exception.php') {
                continue;
            }

            $relative = str_replace('/', '\\', substr($file->getPathname(), strlen(ResourcePath::join($packageDirectory, 'src')) + 1));
            $class    = 'DreaM1\\EfakturaConnector\\' . substr($relative, 0, -4);

            $checked++;

            if (! (new ReflectionClass($class))->implementsInterface(ConnectorThrowable::class)) {
                $violations[] = $class . ' must implement ConnectorThrowable';
            }
        }

        $this->assertGreaterThan(20, $checked);
        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function testPackageCodeThrowsOnlyConnectorExceptions(): void
    {
        $packageDirectory = dirname(__DIR__, 2);
        $violations       = [];

        foreach (['src', 'integrations/laravel/src'] as $directory) {
            foreach ($this->phpFiles(ResourcePath::join($packageDirectory, $directory)) as $file) {
                $source = $this->sourceWithoutCommentsAndStrings(file_get_contents($file->getPathname()));

                // Bare SPL exceptions escape a host's `catch (ConnectorThrowable)`; use the connector family instead.
                $pattern = '/\bnew\s+\\\\?(Exception|LogicException|RuntimeException|InvalidArgumentException|'
                    . 'DomainException|UnexpectedValueException|OutOfRangeException|OutOfBoundsException|'
                    . 'LengthException|RangeException|OverflowException|UnderflowException|BadMethodCallException|'
                    . 'BadFunctionCallException|ErrorException|Error|TypeError|ValueError)\s*\(/';

                if (preg_match($pattern, $source, $match) === 1) {
                    $violations[] = $file->getPathname() . ' throws a bare ' . $match[1];
                }
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function testProviderEndpointsFollowOneConvention(): void
    {
        $root       = ResourcePath::join(dirname(__DIR__, 2), 'src/Infrastructure/EFaktura/Api/Endpoints');
        $namespace  = 'DreaM1\\EfakturaConnector\\Infrastructure\\EFaktura\\Api\\Endpoints\\';
        $violations = [];
        $checked    = 0;

        foreach (['Sapi', 'PeppolGateway', 'Integrator'] as $family) {
            foreach (glob(ResourcePath::join($root, $family) . DIRECTORY_SEPARATOR . '*.php') ?: [] as $path) {
                $name  = basename($path, '.php');
                $class = $namespace . $family . '\\' . $name;

                if (substr($name, -5) === 'Paths') {
                    if (! (new ReflectionClass($class))->isFinal()) {
                        $violations[] = $class . ' must be final';
                    }

                    continue;
                }

                $checked++;
                $reflection = new ReflectionClass($class);

                if (substr($name, -8) !== 'Endpoint') {
                    $violations[] = $class . ' must be named <Verb><Resource>Endpoint';
                }

                if (! $reflection->isSubclassOf($namespace . 'AbstractEndpoint')) {
                    $violations[] = $class . ' must extend AbstractEndpoint';
                }

                // Endpoints stay non-final on purpose: adapter tests mock them.
                if ($reflection->isFinal() || $reflection->isAbstract()) {
                    $violations[] = $class . ' must be a concrete, non-final class';
                }

                if (! $reflection->hasMethod('send') || ! $reflection->getMethod('send')->isPublic()) {
                    $violations[] = $class . ' must expose a public send() method';
                }
            }
        }

        $this->assertGreaterThan(20, $checked);
        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function testProviderEndpointAndClientFactoriesKeepAuthenticationFamiliesSeparate(): void
    {
        $packageDirectory   = dirname(__DIR__, 2);
        $endpointBoundaries = [
            [
                'SAPI endpoints',
                'src/Infrastructure/EFaktura/Api/Endpoints/Sapi',
                [
                    'DreaM1\\EfakturaConnector\\Infrastructure\\EFaktura\\Api\\Endpoints\\PeppolGateway\\',
                    'DreaM1\\EfakturaConnector\\Infrastructure\\EFaktura\\Api\\Endpoints\\Integrator\\',
                    'IntegratorTokenRequestSender',
                    'PdsAuthenticatedRequestSender',
                ],
            ],
            [
                'Peppol Gateway endpoints',
                'src/Infrastructure/EFaktura/Api/Endpoints/PeppolGateway',
                [
                    'DreaM1\\EfakturaConnector\\Infrastructure\\EFaktura\\Api\\Endpoints\\Sapi\\',
                    'DreaM1\\EfakturaConnector\\Infrastructure\\EFaktura\\Api\\Endpoints\\Integrator\\',
                    'IntegratorTokenRequestSender',
                    'PdsAuthenticatedRequestSender',
                ],
            ],
            [
                'Integrator endpoints',
                'src/Infrastructure/EFaktura/Api/Endpoints/Integrator',
                [
                    'DreaM1\\EfakturaConnector\\Infrastructure\\EFaktura\\Api\\Endpoints\\Sapi\\',
                    'DreaM1\\EfakturaConnector\\Infrastructure\\EFaktura\\Api\\Endpoints\\PeppolGateway\\',
                    'DreaM1\\EfakturaConnector\\Infrastructure\\EFaktura\\Api\\Authentication\\AuthenticatedRequestSender',
                    'SapiTokenProvider',
                ],
            ],
        ];
        $violations = [];

        foreach ($endpointBoundaries as [$layer, $relativeDirectory, $forbiddenReferences]) {
            $directory = ResourcePath::join($packageDirectory, $relativeDirectory);
            $files     = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS
            ));

            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $source = $this->sourceWithoutCommentsAndStrings(file_get_contents($file->getPathname()));

                foreach ($forbiddenReferences as $reference) {
                    if ($this->sourceReferences($source, $reference)) {
                        $violations[] = $layer . ' references ' . $reference . ' in ' . $file->getPathname();
                    }
                }
            }
        }

        $clientFactoryBoundaries = [
            [
                'SAPI client factory',
                'src/Infrastructure/EFaktura/Connection/SapiClientFactory.php',
                ['IntegratorTokenRequestSender', 'PdsAuthenticatedRequestSender'],
            ],
            [
                'Integrator client factory',
                'src/Infrastructure/EFaktura/Connection/IntegratorAccountClientFactory.php',
                [
                    'DreaM1\\EfakturaConnector\\Infrastructure\\EFaktura\\Api\\Authentication\\AuthenticatedRequestSender',
                    'SapiTokenProvider',
                    'SapiTokenCacheInterface',
                ],
            ],
        ];

        foreach ($clientFactoryBoundaries as [$layer, $relativeFile, $forbiddenReferences]) {
            $path   = ResourcePath::join($packageDirectory, $relativeFile);
            $source = $this->sourceWithoutCommentsAndStrings(file_get_contents($path));

            foreach ($forbiddenReferences as $reference) {
                if ($this->sourceReferences($source, $reference)) {
                    $violations[] = $layer . ' references ' . $reference . ' in ' . $path;
                }
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function testLaravelHelperDetectionIgnoresMethodsAndDeclarations(): void
    {
        $this->assertTrue($this->callsFunction('<?php logger();', 'logger'));
        $this->assertTrue($this->callsFunction('<?php \\logger();', 'logger'));
        $this->assertFalse($this->callsFunction('<?php \\Application\\logger();', 'logger'));
        $this->assertFalse($this->callsFunction('<?php $options->logger();', 'logger'));
        $this->assertFalse($this->callsFunction('<?php function logger() {}', 'logger'));
    }

    public function testLockFileCodeSuppressesWarningsOnlyInLockFilesystem(): void
    {
        $packageDirectory = dirname(__DIR__, 2);
        $efaktura         = ResourcePath::join($packageDirectory, 'src/Infrastructure/EFaktura');
        $files            = $this->phpFiles(ResourcePath::join($efaktura, 'Coordination'));
        $files[]          = new SplFileInfo(ResourcePath::join($efaktura, 'Validation/Schematron/SchematronLockFileStore.php'));
        $files[]          = new SplFileInfo(ResourcePath::join($efaktura, 'Validation/Schematron/FileSchematronConcurrencyLimiter.php'));
        $violations       = [];

        foreach ($files as $file) {
            if ($file->getFilename() === 'LockFilesystem.php') {
                continue;
            }

            $source = $this->sourceWithoutCommentsAndStrings(file_get_contents($file->getPathname()));

            if (preg_match('/(?<![\w$])@\s*[a-z_]+\s*\(/', $source) === 1) {
                $violations[] = $file->getPathname() . ' suppresses warnings with @; use LockFilesystem';
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    /** @return SplFileInfo[] */
    private function phpFiles(string $directory): array
    {
        $files    = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        return $files;
    }

    /** Matches a namespace prefix or a complete symbol name without matching identifier suffixes. */
    private function sourceReferences(string $source, string $reference): bool
    {
        if (substr($reference, -1) === '\\') {
            return strpos($source, $reference) !== false;
        }

        return preg_match('/(?<![A-Za-z0-9_])' . preg_quote($reference, '/') . '(?![A-Za-z0-9_])/', $source) === 1;
    }

    /** Removes comments and literals so architecture checks inspect executable source only. */
    private function sourceWithoutCommentsAndStrings(string $source): string
    {
        $code          = '';
        $ignoredTokens = [
            T_COMMENT,
            T_DOC_COMMENT,
            T_CONSTANT_ENCAPSED_STRING,
            T_ENCAPSED_AND_WHITESPACE,
            T_START_HEREDOC,
            T_END_HEREDOC,
            T_INLINE_HTML,
        ];

        // Replace comments and literals with spaces so dependency names inside examples do not cause false positives.
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                $code .= in_array($token[0], $ignoredTokens, true) ? ' ' : $token[1];
                continue;
            }

            $code .= $token;
        }

        return $code;
    }

    /** Checks for a bare helper-function call while ignoring declarations and member calls. */
    private function callsFunction(string $source, string $function): bool
    {
        $tokens                  = token_get_all($source);
        $fullyQualifiedNameToken = defined('T_NAME_FULLY_QUALIFIED')
            ? constant('T_NAME_FULLY_QUALIFIED')
            : null;

        foreach ($tokens as $index => $token) {
            if (! is_array($token)) {
                continue;
            }

            $isFunctionName = $token[0] === T_STRING && strtolower($token[1]) === $function;

            if ($fullyQualifiedNameToken !== null && $token[0] === $fullyQualifiedNameToken) {
                $isFunctionName = strtolower(ltrim($token[1], '\\')) === $function;
            }

            if (! $isFunctionName) {
                continue;
            }

            $nextIndex = $index + 1;
            while (isset($tokens[$nextIndex]) && is_array($tokens[$nextIndex]) && $tokens[$nextIndex][0] === T_WHITESPACE) {
                $nextIndex++;
            }

            if (! isset($tokens[$nextIndex]) || $tokens[$nextIndex] !== '(') {
                continue;
            }

            // Exclude member, static and declaration syntax; only a bare function invocation is a helper call.
            $previousIndex = $index - 1;
            while ($previousIndex >= 0 && is_array($tokens[$previousIndex]) && $tokens[$previousIndex][0] === T_WHITESPACE) {
                $previousIndex--;
            }

            $previous = $tokens[$previousIndex] ?? null;

            // PHP 7.4 tokenizes qualified names as separate namespace separators and identifiers.
            $isNamespaceSeparator = $previous === '\\'
                || (is_array($previous) && $previous[0] === T_NS_SEPARATOR);

            if ($isNamespaceSeparator) {
                $qualifierIndex = $previousIndex - 1;
                while ($qualifierIndex >= 0 && is_array($tokens[$qualifierIndex]) && $tokens[$qualifierIndex][0] === T_WHITESPACE) {
                    $qualifierIndex--;
                }

                $qualifier = $tokens[$qualifierIndex] ?? null;

                if (is_array($qualifier) && in_array($qualifier[0], [T_STRING, T_NAMESPACE], true)) {
                    continue;
                }
            }

            if (
                $previous === '->'
                || (is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true))
            ) {
                continue;
            }

            return true;
        }

        return false;
    }
}
