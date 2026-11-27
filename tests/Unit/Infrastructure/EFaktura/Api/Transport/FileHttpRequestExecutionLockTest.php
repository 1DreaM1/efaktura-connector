<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Transport;

use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\FileHttpRequestExecutionLock;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Coordination\FileLock;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Tests\Support\ReflectionAccess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/** Tests file-backed HTTP request execution coordination. */
class FileHttpRequestExecutionLockTest extends TestCase
{
    private string $temporaryDirectory;
    private string $firstApplicationDirectory;
    private string $secondApplicationDirectory;
    /** @var array<int, array{process: resource, pipes: array<int, resource>, label: string, releasePath: string}> */
    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = ResourcePath::join(
            sys_get_temp_dir(),
            'efaktura-lock-test-' . bin2hex(random_bytes(8))
        );
        mkdir($this->temporaryDirectory, 0700);

        $this->firstApplicationDirectory  = ResourcePath::join($this->temporaryDirectory, 'application-one');
        $this->secondApplicationDirectory = ResourcePath::join($this->temporaryDirectory, 'application-two');
        mkdir($this->firstApplicationDirectory, 0700);
        mkdir($this->secondApplicationDirectory, 0700);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            file_put_contents($worker['releasePath'], 'release');
        }

        foreach (array_keys($this->workers) as $workerId) {
            $this->finishWorker($workerId, false);
        }

        foreach (glob(ResourcePath::join($this->temporaryDirectory, 'efaktura-connector-http-*')) ?: [] as $path) {
            if (is_link($path)) {
                // Windows removes a directory symbolic link with rmdir(), not unlink().
                if (DIRECTORY_SEPARATOR === '\\' && is_dir($path)) {
                    rmdir($path);
                } else {
                    unlink($path);
                }

                continue;
            }

            foreach (glob(ResourcePath::join($path, '*.lock')) ?: [] as $lockFile) {
                @unlink($lockFile);
            }

            @rmdir($path);
        }

        @unlink(ResourcePath::join($this->secondApplicationDirectory, 'outside.lock'));

        foreach (['*.php', '*.events', '*.release', '*.ready'] as $pattern) {
            foreach (glob(ResourcePath::join($this->temporaryDirectory, $pattern)) ?: [] as $path) {
                @unlink($path);
            }
        }

        @rmdir($this->firstApplicationDirectory);
        @rmdir($this->secondApplicationDirectory);
        @rmdir($this->temporaryDirectory);

        parent::tearDown();
    }

    public function testLockScopeIsStableForAnApplicationAndIsolatedBetweenApplications(): void
    {
        $first           = new FileHttpRequestExecutionLock($this->firstApplicationDirectory, $this->temporaryDirectory);
        $sameApplication = new FileHttpRequestExecutionLock(
            ResourcePath::join($this->firstApplicationDirectory, '.'),
            $this->temporaryDirectory
        );
        $second   = new FileHttpRequestExecutionLock($this->secondApplicationDirectory, $this->temporaryDirectory);
        $scopeKey = 'provider-client-one';

        $this->assertSame($this->lockFilePath($first, $scopeKey), $this->lockFilePath($sameApplication, $scopeKey));
        $this->assertNotSame($this->lockFilePath($first, $scopeKey), $this->lockFilePath($second, $scopeKey));
        $this->assertNotSame($this->lockFilePath($first, $scopeKey), $this->lockFilePath($first, 'provider-client-two'));
    }

    public function testLockInitializationFailureIsTypedAndPreservesItsCause(): void
    {
        try {
            new FileHttpRequestExecutionLock(
                ResourcePath::join($this->temporaryDirectory, 'missing-application'),
                $this->temporaryDirectory
            );
            $this->fail('An unavailable lock directory should fail initialization.');
        } catch (RequestCoordinationException $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception->getPrevious());
        }
    }

    public function testLockIsReleasedWhenCallbackThrows(): void
    {
        $lock = new FileHttpRequestExecutionLock($this->firstApplicationDirectory, $this->temporaryDirectory);

        try {
            $lock->synchronizedFor('provider-client-one', function () {
                throw new RuntimeException('synthetic callback failure');
            });
            $this->fail('The callback exception should be propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('synthetic callback failure', $exception->getMessage());
        }

        $result = new HttpExchangeResult(200, '{}', [], microtime(true));
        $this->assertSame($result, $lock->synchronizedFor('provider-client-one', function () use ($result) {
            return $result;
        }));
    }

    public function testLockDirectoryAndFileArePrivateOnPosix(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('POSIX permission bits are not available on Windows.');
        }

        $lock     = new FileHttpRequestExecutionLock($this->firstApplicationDirectory, $this->temporaryDirectory);
        $scopeKey = 'provider-client-one';
        $lock->synchronizedFor($scopeKey, function () {
            return new HttpExchangeResult(200, '{}', [], microtime(true));
        });

        $directoryPermissions = fileperms(dirname($this->lockFilePath($lock, $scopeKey))) & 0777;
        $filePermissions      = fileperms($this->lockFilePath($lock, $scopeKey)) & 0777;
        $this->assertSame(0700, $directoryPermissions);
        $this->assertSame(0600, $filePermissions);
    }

    public function testRepairsPermissionsOnExistingLockDirectoryAndFile(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('POSIX permission bits are not available on Windows.');
        }

        $lock          = new FileHttpRequestExecutionLock($this->firstApplicationDirectory, $this->temporaryDirectory);
        $scopeKey      = 'provider-client-one';
        $lockFilePath  = $this->lockFilePath($lock, $scopeKey);
        $lockDirectory = dirname($lockFilePath);
        mkdir($lockDirectory, 0755);
        file_put_contents($lockFilePath, '');
        chmod($lockDirectory, 0755);
        chmod($lockFilePath, 0644);

        $lock->synchronizedFor($scopeKey, function () {
            return new HttpExchangeResult(200, '{}', [], microtime(true));
        });

        $this->assertSame(0700, fileperms($lockDirectory) & 0777);
        $this->assertSame(0600, fileperms($lockFilePath) & 0777);
    }

    public function testLockRejectsASymbolicLinkAsItsScopedDirectory(): void
    {
        if (! function_exists('symlink')) {
            $this->markTestSkipped('Symbolic links are not available.');
        }

        $lock          = new FileHttpRequestExecutionLock($this->firstApplicationDirectory, $this->temporaryDirectory);
        $lockDirectory = dirname($this->lockFilePath($lock, 'provider-client-one'));

        if (! @symlink($this->secondApplicationDirectory, $lockDirectory)) {
            $this->markTestSkipped('The current environment does not permit creating symbolic links.');
        }

        try {
            $lock->synchronizedFor('provider-client-one', function () {
                return new HttpExchangeResult(200, '{}', [], microtime(true));
            });
            $this->fail('A symbolic lock directory should fail coordination.');
        } catch (RequestCoordinationException $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception->getPrevious());
        }
    }

    public function testLockRejectsASymbolicLinkAsItsLockFile(): void
    {
        if (! function_exists('symlink')) {
            $this->markTestSkipped('Symbolic links are not available.');
        }

        $lock          = new FileHttpRequestExecutionLock($this->firstApplicationDirectory, $this->temporaryDirectory);
        $scopeKey      = 'provider-client-one';
        $lockFilePath  = $this->lockFilePath($lock, $scopeKey);
        $lockDirectory = dirname($lockFilePath);
        mkdir($lockDirectory, 0700);
        $outsideFile = ResourcePath::join($this->secondApplicationDirectory, 'outside.lock');
        file_put_contents($outsideFile, 'synthetic');

        if (! @symlink($outsideFile, $lockFilePath)) {
            $this->markTestSkipped('The current environment does not permit creating symbolic links.');
        }

        $this->expectException(RequestCoordinationException::class);
        $lock->synchronizedFor($scopeKey, function () {
            return new HttpExchangeResult(200, '{}', [], microtime(true));
        });
    }

    public function testSameScopeSerializesCallbacksAcrossProcesses(): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('PHP proc_open is unavailable.');
        }

        $eventsPath   = ResourcePath::join($this->temporaryDirectory, 'same-scope.events');
        $releasePath  = ResourcePath::join($this->temporaryDirectory, 'same-scope.release');
        $firstWorker  = $this->startWorker('same-quota', 'same-first', true, $eventsPath, $releasePath);
        $secondWorker = null;

        try {
            $this->assertTrue($this->waitForFile($this->workerReadyPath('same-first'), 10.0));
            $this->assertTrue($this->waitForEvent($eventsPath, 'same-first-start', 10.0));

            $secondWorker = $this->startWorker('same-quota', 'same-second', false, $eventsPath, $releasePath);
            $this->assertTrue($this->waitForFile($this->workerReadyPath('same-second'), 10.0));
            // The child is running but must not enter the critical section until the first worker releases the lock.
            $this->assertFalse($this->waitForEvent($eventsPath, 'same-second-start', 0.15));

            file_put_contents($releasePath, 'release');
            $this->assertTrue($this->waitForEvent($eventsPath, 'same-second-end', 10.0));
            $this->finishWorker($firstWorker);
            $this->finishWorker($secondWorker);
            $this->assertSame([
                'same-first-start',
                'same-first-end',
                'same-second-start',
                'same-second-end',
            ], $this->readEvents($eventsPath));
        } finally {
            file_put_contents($releasePath, 'release');
            $this->finishWorker($firstWorker, false);

            if ($secondWorker !== null) {
                $this->finishWorker($secondWorker, false);
            }
        }
    }

    public function testDifferentScopesCanRunAtTheSameTimeAcrossProcesses(): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('PHP proc_open is unavailable.');
        }

        $eventsPath   = ResourcePath::join($this->temporaryDirectory, 'different-scopes.events');
        $releasePath  = ResourcePath::join($this->temporaryDirectory, 'different-scopes.release');
        $firstWorker  = $this->startWorker('provider-client-one', 'scope-first', true, $eventsPath, $releasePath);
        $secondWorker = null;

        try {
            $this->assertTrue($this->waitForFile($this->workerReadyPath('scope-first'), 10.0));
            $this->assertTrue($this->waitForEvent($eventsPath, 'scope-first-start', 10.0));

            $secondWorker = $this->startWorker('provider-client-two', 'scope-second', false, $eventsPath, $releasePath);
            $this->assertTrue($this->waitForFile($this->workerReadyPath('scope-second'), 10.0));
            $this->assertTrue(
                $this->waitForEvent($eventsPath, 'scope-second-end', 10.0),
                'An unrelated provider quota should not wait for the first scope lock.'
            );
            $this->assertFileDoesNotExist($releasePath);

            file_put_contents($releasePath, 'release');
            $this->assertTrue($this->waitForEvent($eventsPath, 'scope-first-end', 10.0));
            $this->finishWorker($firstWorker);
            $this->finishWorker($secondWorker);
            $this->assertSame([
                'scope-first-start',
                'scope-second-start',
                'scope-second-end',
                'scope-first-end',
            ], $this->readEvents($eventsPath));
        } finally {
            file_put_contents($releasePath, 'release');
            $this->finishWorker($firstWorker, false);

            if ($secondWorker !== null) {
                $this->finishWorker($secondWorker, false);
            }
        }
    }

    /** Returns the hashed lock path used by filesystem safety assertions. */
    private function lockFilePath(FileHttpRequestExecutionLock $lock, string $scopeKey): string
    {
        // Tests need the exact private path to place a symlink there or inspect the created file's permissions.
        $lockProperty = (new ReflectionClass($lock))->getProperty('fileLock');
        ReflectionAccess::makeAccessible($lockProperty);
        $fileLock          = $lockProperty->getValue($lock);
        $directoryProperty = (new ReflectionClass(FileLock::class))->getProperty('lockDirectory');
        ReflectionAccess::makeAccessible($directoryProperty);

        return ResourcePath::join($directoryProperty->getValue($fileLock), hash('sha256', $scopeKey) . '.lock');
    }

    /**
     * Starts and records a child process that competes for the scoped execution lock.
     *
     * @param  bool $waitForRelease Whether the worker should hold the lock until its release file appears.
     * @return int  Index used to finish and remove this worker later.
     */
    private function startWorker(
        string $scopeKey,
        string $label,
        bool $waitForRelease,
        string $eventsPath,
        string $releasePath
    ): int {
        $readyPath = $this->workerReadyPath($label);
        $command   = [
            PHP_BINARY,
            $this->workerScriptPath(),
            ResourcePath::join(dirname(__DIR__, 6), 'vendor/autoload.php'),
            $this->firstApplicationDirectory,
            $this->temporaryDirectory,
            $scopeKey,
            $label,
            $eventsPath,
            $releasePath,
            $readyPath,
            $waitForRelease ? '1' : '0',
        ];
        $pipes   = [];
        $process = @proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $this->firstApplicationDirectory);

        $this->assertIsResource($process, 'Could not start a lock test worker.');
        fclose($pipes[0]);

        $this->workers[] = [
            'process'     => $process,
            'pipes'       => $pipes,
            'label'       => $label,
            'releasePath' => $releasePath,
        ];

        return array_key_last($this->workers);
    }

    /** Waits for a tracked worker to exit, closes its pipes, and optionally checks its exit status. */
    private function finishWorker(int $workerId, bool $assertSuccess = true): void
    {
        if (! isset($this->workers[$workerId])) {
            return;
        }

        $worker   = $this->workers[$workerId];
        $process  = $worker['process'];
        $deadline = microtime(true) + 5.0;
        $status   = proc_get_status($process);
        while ($status['running'] && microtime(true) < $deadline) {
            usleep(10000);
            $status = proc_get_status($process);
        }

        if ($status['running']) {
            @proc_terminate($process);
            $status = proc_get_status($process);
        }

        $stdout = stream_get_contents($worker['pipes'][1]);
        $stderr = stream_get_contents($worker['pipes'][2]);
        fclose($worker['pipes'][1]);
        fclose($worker['pipes'][2]);
        $closeExitCode = proc_close($process);
        unset($this->workers[$workerId]);

        if ($assertSuccess) {
            // proc_close can return -1 after proc_get_status observed the exit, so prefer that recorded status.
            $exitCode = $status['exitcode'] >= 0 ? $status['exitcode'] : $closeExitCode;
            $this->assertSame(0, $exitCode, $worker['label'] . ' worker failed: ' . $stdout . $stderr);
        }
    }

    /** Polls for a file until the timeout expires. */
    private function waitForFile(string $path, float $timeoutSeconds): bool
    {
        $deadline = microtime(true) + $timeoutSeconds;
        do {
            if (is_file($path)) {
                return true;
            }

            usleep(10000);
        } while (microtime(true) < $deadline);

        return is_file($path);
    }

    /** Polls the worker event log until the requested event appears or the timeout expires. */
    private function waitForEvent(string $eventsPath, string $event, float $timeoutSeconds): bool
    {
        $deadline = microtime(true) + $timeoutSeconds;
        do {
            if (in_array($event, $this->readEvents($eventsPath), true)) {
                return true;
            }

            usleep(10000);
        } while (microtime(true) < $deadline);

        return in_array($event, $this->readEvents($eventsPath), true);
    }

    /** @return string[] */
    private function readEvents(string $eventsPath): array
    {
        $events = @file($eventsPath, FILE_IGNORE_NEW_LINES);

        return is_array($events) ? $events : [];
    }

    private function workerReadyPath(string $label): string
    {
        return ResourcePath::join($this->temporaryDirectory, $label . '.ready');
    }

    /** Returns or creates the child-process script used to exercise cross-process file locks. */
    private function workerScriptPath(): string
    {
        $path = ResourcePath::join($this->temporaryDirectory, 'lock-worker.php');

        if (is_file($path)) {
            return $path;
        }

        // A separate process is required to verify that flock excludes competing workers, not just re-entrant calls.
        $script = <<<'PHP'
<?php

require $argv[1];

$lock = new \DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\FileHttpRequestExecutionLock(
    $argv[2],
    $argv[3]
);
$scopeKey = $argv[4];
$label = $argv[5];
$eventsPath = $argv[6];
$releasePath = $argv[7];
$readyPath = $argv[8];
$waitForRelease = $argv[9] === '1';

file_put_contents($readyPath, 'ready');
// The parent observes readiness separately from entry into the locked callback.
$lock->synchronizedFor($scopeKey, function () use ($label, $eventsPath, $releasePath, $waitForRelease) {
    file_put_contents($eventsPath, $label . '-start' . PHP_EOL, FILE_APPEND | LOCK_EX);

    if ($waitForRelease) {
        $deadline = microtime(true) + 20.0;
        while (! is_file($releasePath) && microtime(true) < $deadline) {
            usleep(10000);
        }
        if (! is_file($releasePath)) {
            throw new \RuntimeException('The test parent did not release the worker.');
        }
    }

    file_put_contents($eventsPath, $label . '-end' . PHP_EOL, FILE_APPEND | LOCK_EX);

    return new \DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult(
        200,
        '',
        [],
        microtime(true)
    );
});
PHP;

        file_put_contents($path, $script);

        return $path;
    }
}
