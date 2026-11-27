<?php

/**
 * Runs the maintained invoice example against synthetic SAPI adapters across retry scenarios.
 *
 * Requests, provider responses, and persistence are local to the temporary simulation environment.
 */

use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Tools\RepositoryToolDefaults;

require_once __DIR__ . '/bootstrap.php';

$root                = dirname(__DIR__);
$phpExamplePath      = ResourcePath::join($root, 'docs/wiki/UBL-Documents.md');
$adapterFixturePath  = ResourcePath::join($root, 'tools/fixtures/sapi-timeout-adapters.php');
$temporaryRoot       = realpath(sys_get_temp_dir());
$simulationDirectory = ResourcePath::join(
    sys_get_temp_dir(),
    'efaktura-sapi-timeout-' . bin2hex(random_bytes(8))
);
$environmentNames = [
    'EFAKTURA_SEND_SANDBOX',
    'EFAKTURA_BASE_URL',
    'EFAKTURA_CLIENT_ID',
    'EFAKTURA_CLIENT_SECRET',
    'EFAKTURA_CLIENT_SCOPE',
    'EFAKTURA_SUBMISSION_DB',
    'EFAKTURA_SIMULATION_LOG',
    'EFAKTURA_SIMULATION_TRACKING_STATUS_SEQUENCE',
    'EFAKTURA_SIMULATION_SEND_TIMEOUT_COUNT',
    'EFAKTURA_SIMULATION_SEND_PROVIDER_ID',
    'EFAKTURA_SIMULATION_SEND_RESPONSE_STATUS',
    'EFAKTURA_SIMULATION_BARRIER_DIRECTORY',
    'EFAKTURA_SUBMISSION_REVISION',
];
$originalEnvironment = [];

foreach ($environmentNames as $name) {
    $originalEnvironment[$name] = getenv($name);
}

// Run the published example in child PHP processes so retries and cross-process locks use their real runtime behavior.
/**
 * @var Closure(string, string): array{process: resource, stdout: resource, stderr: resource} $startPhp
 *                                                                                            Starts a PHP script as a child process in the supplied working directory.
 */
$startPhp = static function (string $scriptPath, string $workingDirectory): array {
    $process = proc_open(
        [PHP_BINARY, $scriptPath],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $workingDirectory
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Could not start a PHP simulation process.');
    }

    fclose($pipes[0]);

    return ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
};

/**
 * @var Closure(array{process: resource, stdout: resource, stderr: resource}): array{exit_code: int, output: string} $finishPhp
 *                                                                                                                   Closes a child process's pipes and returns its exit code and combined output.
 */
$finishPhp = static function (array $runningProcess): array {
    $stdout = stream_get_contents($runningProcess['stdout']);
    fclose($runningProcess['stdout']);
    $stderr = stream_get_contents($runningProcess['stderr']);
    fclose($runningProcess['stderr']);

    return [
        'exit_code' => proc_close($runningProcess['process']),
        'output'    => $stdout . $stderr,
    ];
};

/**
 * @var Closure(string, string): array{exit_code: int, output: string} $runPhp
 *                                                                     Starts and finishes one PHP child process, returning its exit code and combined output.
 */
$runPhp = static function (string $scriptPath, string $workingDirectory) use ($startPhp, $finishPhp): array {
    return $finishPhp($startPhp($scriptPath, $workingDirectory));
};

try {
    if (! extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('The local simulation requires the pdo_sqlite extension.');
    }

    if (! function_exists('proc_open')) {
        throw new RuntimeException('The local simulation requires proc_open.');
    }

    if ($temporaryRoot === false || ! mkdir($simulationDirectory, 0700)) {
        throw new RuntimeException('Could not create an isolated temporary directory.');
    }

    // Extract the marked example from the guide so the simulation exercises the maintained instructions verbatim.
    $documentation      = file_get_contents($phpExamplePath);
    $exampleStartMarker = '<!-- sapi-timeout-simulation-example:start -->';
    $exampleEndMarker   = '<!-- sapi-timeout-simulation-example:end -->';
    $exampleStart       = is_string($documentation) ? strpos($documentation, $exampleStartMarker) : false;
    $exampleEnd         = $exampleStart === false
        ? false
        : strpos($documentation, $exampleEndMarker, $exampleStart + strlen($exampleStartMarker));

    if (
        $documentation === false
        || $exampleStart === false
        || $exampleEnd === false
        || strpos($documentation, $exampleStartMarker, $exampleStart + strlen($exampleStartMarker)) !== false
        || strpos($documentation, $exampleEndMarker, $exampleEnd + strlen($exampleEndMarker)) !== false
    ) {
        throw new RuntimeException('Could not find unique simulation example markers in UBL-Documents.md.');
    }

    $exampleSection = substr(
        $documentation,
        $exampleStart + strlen($exampleStartMarker),
        $exampleEnd - ($exampleStart + strlen($exampleStartMarker))
    );

    if (preg_match('~```php\r?\n(.*?)\r?\n```~s', $exampleSection, $matches) !== 1) {
        throw new RuntimeException('Could not extract a PHP example from the marked section in UBL-Documents.md.');
    }

    $autoloadPath      = var_export(ResourcePath::join($root, 'vendor/autoload.php'), true);
    $autoloadStatement = "require __DIR__ . '/vendor/autoload.php';";

    if (substr_count($matches[1], $autoloadStatement) !== 1) {
        throw new RuntimeException('The marked invoice example must contain one Composer autoload statement.');
    }

    $example = str_replace(
        $autoloadStatement,
        'require ' . $autoloadPath . ';',
        $matches[1]
    );
    $adapterStartMarker = '/* SAPI_TIMEOUT_SIMULATION_ADAPTERS_START */';
    $adapterEndMarker   = '/* SAPI_TIMEOUT_SIMULATION_ADAPTERS_END */';
    $adapterStart       = strpos($example, $adapterStartMarker);
    $adapterEnd         = $adapterStart === false
        ? false
        : strpos($example, $adapterEndMarker, $adapterStart + strlen($adapterStartMarker));

    if (
        $adapterStart === false
        || $adapterEnd === false
        || strpos($example, $adapterStartMarker, $adapterStart + strlen($adapterStartMarker)) !== false
        || strpos($example, $adapterEndMarker, $adapterEnd + strlen($adapterEndMarker)) !== false
    ) {
        throw new RuntimeException('Could not find unique adapter markers in the invoice example.');
    }

    // Replace only the marked provider adapters; the rest of the documentation example remains unchanged.
    $adapterFixturePathLiteral = var_export($adapterFixturePath, true);
    $adapterReplacement        = '$simulationAdapters = require ' . $adapterFixturePathLiteral . ';' . PHP_EOL
        . '$gateway = $simulationAdapters[\'gateway\'];' . PHP_EOL
        . '$trackingProvider = $simulationAdapters[\'tracking_provider\'];';
    $example = substr($example, 0, $adapterStart)
        . $adapterReplacement
        . substr($example, $adapterEnd + strlen($adapterEndMarker));

    // The barrier pauses each concurrent retry after its database commit until both workers reach the same point.
    $barrierInjection = <<<'PHP'
$simulationBarrierDirectory = getenv('EFAKTURA_SIMULATION_BARRIER_DIRECTORY');
if ($simulationBarrierDirectory !== false && $simulationBarrierDirectory !== '') {
    file_put_contents(
        \DreaM1\EfakturaConnector\Infrastructure\ResourcePath::join(
            $simulationBarrierDirectory,
            'ready-' . getmypid()
        ),
        'ready'
    );
    $barrierDeadline = microtime(true) + 45;
    while (count(glob(\DreaM1\EfakturaConnector\Infrastructure\ResourcePath::join(
        $simulationBarrierDirectory,
        'ready-*'
    ))) < 2) {
        if (microtime(true) > $barrierDeadline) {
            throw new \RuntimeException('Timed out waiting for the concurrent simulation barrier.');
        }
        usleep(10000);
    }
}
PHP;
    $example = str_replace(
        '$submissionDb->commit();',
        '$submissionDb->commit();' . PHP_EOL . $barrierInjection,
        $example,
        $commitReplacementCount
    );

    if ($commitReplacementCount !== 1) {
        throw new RuntimeException('Could not add the concurrent simulation barrier to the invoice example.');
    }

    putenv('EFAKTURA_SEND_SANDBOX=1');
    putenv('EFAKTURA_BASE_URL=https://test-sapisk.datahosting.sk');
    putenv('EFAKTURA_CLIENT_ID=local-simulation-client');
    putenv('EFAKTURA_CLIENT_SECRET=local-simulation-secret');
    putenv('EFAKTURA_CLIENT_SCOPE=local-simulation-scope');
    // Each scenario starts from an ambiguous timed-out send, then varies tracking, persistence or retry conditions.
    $scenarios = [
        'tracking-404-retry' => [
            'seed_provider_id'         => true,
            'tracking_status_sequence' => [404, 200],
            'expected_order'           => ['send', 'tracking 404', 'send', 'tracking 200'],
            'expected_send_count'      => 2,
            'expected_tracking_ids'    => ['SIMULATED-STORED-ID', 'SIMULATED-STORED-ID'],
            'send_provider_id'         => 'SIMULATED-STORED-ID',
            'compare_sends'            => true,
            'expect_retry_success'     => true,
        ],
        'tracking-found-no-resend' => [
            'seed_provider_id'         => true,
            'tracking_status_sequence' => [200],
            'expected_order'           => ['send', 'tracking 200'],
            'expected_send_count'      => 1,
            'expected_tracking_ids'    => ['SIMULATED-STORED-ID'],
            'compare_sends'            => false,
            'expect_retry_success'     => true,
        ],
        'missing-provider-id-retry' => [
            'seed_provider_id'         => false,
            'tracking_status_sequence' => [200],
            'expected_order'           => ['send', 'send', 'tracking 200'],
            'expected_send_count'      => 2,
            'expected_tracking_ids'    => ['SIMULATED-PROVIDER-ID'],
            'compare_sends'            => true,
            'expect_retry_success'     => true,
        ],
        'tracking-error-no-resend' => [
            'seed_provider_id'         => true,
            'tracking_status_sequence' => [500],
            'expected_order'           => ['send', 'tracking 500'],
            'expected_send_count'      => 1,
            'expected_tracking_ids'    => ['SIMULATED-STORED-ID'],
            'compare_sends'            => false,
            'expect_retry_success'     => false,
            'expected_retry_error'     => 'Provider answered with HTTP 500.',
        ],
        'tracking-success-204-no-resend' => [
            'seed_provider_id'         => true,
            'tracking_status_sequence' => [204],
            'expected_order'           => ['send', 'tracking 204'],
            'expected_send_count'      => 1,
            'expected_tracking_ids'    => ['SIMULATED-STORED-ID'],
            'compare_sends'            => false,
            'expect_retry_success'     => true,
        ],
        'tracking-success-299-no-resend' => [
            'seed_provider_id'         => true,
            'tracking_status_sequence' => [299],
            'expected_order'           => ['send', 'tracking 299'],
            'expected_send_count'      => 1,
            'expected_tracking_ids'    => ['SIMULATED-STORED-ID'],
            'compare_sends'            => false,
            'expect_retry_success'     => true,
        ],
        'tracking-redirect-300-no-resend' => [
            'seed_provider_id'         => true,
            'tracking_status_sequence' => [300],
            'expected_order'           => ['send', 'tracking 300'],
            'expected_send_count'      => 1,
            'expected_tracking_ids'    => ['SIMULATED-STORED-ID'],
            'compare_sends'            => false,
            'expect_retry_success'     => false,
            'expected_retry_error'     => 'Provider answered with HTTP 300.',
        ],
        'tracking-client-error-400-no-resend' => [
            'seed_provider_id'         => true,
            'tracking_status_sequence' => [400],
            'expected_order'           => ['send', 'tracking 400'],
            'expected_send_count'      => 1,
            'expected_tracking_ids'    => ['SIMULATED-STORED-ID'],
            'compare_sends'            => false,
            'expect_retry_success'     => false,
            'expected_retry_error'     => 'Provider answered with HTTP 400.',
        ],
        'tracking-404-then-server-error-no-extra-send' => [
            'seed_provider_id'         => true,
            'tracking_status_sequence' => [404, 500],
            'expected_order'           => ['send', 'tracking 404', 'send', 'tracking 500'],
            'expected_send_count'      => 2,
            'expected_tracking_ids'    => ['SIMULATED-STORED-ID', 'SIMULATED-STORED-ID'],
            'send_provider_id'         => 'SIMULATED-STORED-ID',
            'compare_sends'            => true,
            'expect_retry_success'     => false,
            'expected_retry_error'     => 'Provider answered with HTTP 500.',
        ],
        'payload-change-stop' => [
            'seed_provider_id'           => false,
            'tracking_status_sequence'   => [200],
            'expected_order'             => ['send'],
            'expected_send_count'        => 1,
            'expected_tracking_ids'      => [],
            'compare_sends'              => false,
            'expect_retry_success'       => false,
            'expected_retry_error'       => 'its payload changed; do not reuse its idempotency key.',
            'change_payload'             => true,
            'assert_original_key_stored' => true,
        ],
        'payload-change-new-revision' => [
            'seed_provider_id'         => false,
            'tracking_status_sequence' => [200],
            'expected_order'           => ['send', 'send', 'tracking 200'],
            'expected_send_count'      => 2,
            'expected_tracking_ids'    => ['SIMULATED-PROVIDER-ID'],
            'compare_sends'            => false,
            'expect_retry_success'     => true,
            'change_payload'           => true,
            'retry_revision'           => '2',
            'assert_new_attempt'       => true,
        ],
        'accepted-status-tracking-404-no-resend' => [
            'seed_provider_id'            => true,
            'seed_status'                 => 'ACCEPTED',
            'assert_seed_status_retained' => true,
            'tracking_status_sequence'    => [404],
            'expected_order'              => ['send', 'tracking 404'],
            'expected_send_count'         => 1,
            'expected_tracking_ids'       => ['SIMULATED-STORED-ID'],
            'compare_sends'               => false,
            'expect_retry_success'        => true,
        ],
        'rejected-status-tracking-404-no-resend' => [
            'seed_provider_id'            => true,
            'seed_status'                 => 'REJECTED',
            'assert_seed_status_retained' => true,
            'tracking_status_sequence'    => [404],
            'expected_order'              => ['send', 'tracking 404'],
            'expected_send_count'         => 1,
            'expected_tracking_ids'       => ['SIMULATED-STORED-ID'],
            'compare_sends'               => false,
            'expect_retry_success'        => true,
        ],
        'repeated-timeout-same-key' => [
            'seed_provider_id'         => true,
            'tracking_status_sequence' => [404, 404, 200],
            'send_timeout_count'       => 2,
            'send_provider_id'         => 'SIMULATED-STORED-ID',
            'expected_order'           => [
                'send', 'tracking 404', 'send', 'tracking 404', 'send', 'tracking 200',
            ],
            'expected_send_count'   => 3,
            'expected_tracking_ids' => [
                'SIMULATED-STORED-ID',
                'SIMULATED-STORED-ID',
                'SIMULATED-STORED-ID',
            ],
            'compare_sends'         => true,
            'expect_retry_success'  => true,
            'retry_run_count'       => 2,
            'timeout_on_retry_runs' => [1],
        ],
        'accepted-response-persistence-fails' => [
            'seed_provider_id'                  => false,
            'tracking_status_sequence'          => [200],
            'send_provider_id'                  => 'SIMULATED-PROVIDER-ID',
            'expected_order'                    => ['send', 'send', 'send', 'tracking 200'],
            'expected_send_count'               => 3,
            'expected_tracking_ids'             => ['SIMULATED-PROVIDER-ID'],
            'compare_sends'                     => true,
            'expect_retry_success'              => true,
            'retry_run_count'                   => 2,
            'persistence_failure_on_retry_runs' => [1],
            'assert_final_replay_attempt'       => true,
        ],
        'concurrent-retries-same-key' => [
            'seed_provider_id'         => false,
            'tracking_status_sequence' => [200],
            'expected_send_count'      => 3,
            'expected_tracking_ids'    => [
                'SIMULATED-PROVIDER-ID',
                'SIMULATED-PROVIDER-ID',
            ],
            'compare_sends'             => true,
            'expect_retry_success'      => true,
            'concurrent_retry_count'    => 2,
            'assert_concurrent_attempt' => true,
        ],
        'tracking-unauthorized-no-resend' => [
            'seed_provider_id'         => true,
            'tracking_status_sequence' => [401],
            'expected_order'           => ['send', 'tracking 401'],
            'expected_send_count'      => 1,
            'expected_tracking_ids'    => ['SIMULATED-STORED-ID'],
            'compare_sends'            => false,
            'expect_retry_success'     => false,
            'expected_retry_error'     => 'Provider answered with HTTP 401.',
        ],
        'tracking-rate-limit-no-resend' => [
            'seed_provider_id'         => true,
            'tracking_status_sequence' => [429],
            'expected_order'           => ['send', 'tracking 429'],
            'expected_send_count'      => 1,
            'expected_tracking_ids'    => ['SIMULATED-STORED-ID'],
            'compare_sends'            => false,
            'expect_retry_success'     => false,
            'expected_retry_error'     => 'Provider answered with HTTP 429.',
        ],
        'tracking-forbidden-no-resend' => [
            'seed_provider_id'         => true,
            'tracking_status_sequence' => [403],
            'expected_order'           => ['send', 'tracking 403'],
            'expected_send_count'      => 1,
            'expected_tracking_ids'    => ['SIMULATED-STORED-ID'],
            'compare_sends'            => false,
            'expect_retry_success'     => false,
            'expected_retry_error'     => 'Provider answered with HTTP 403.',
        ],
        'tracking-rate-limit-then-recovers' => [
            'seed_provider_id'         => true,
            'tracking_status_sequence' => [429, 200],
            'expected_order'           => ['send', 'tracking 429', 'tracking 200'],
            'expected_send_count'      => 1,
            'expected_tracking_ids'    => ['SIMULATED-STORED-ID', 'SIMULATED-STORED-ID'],
            'compare_sends'            => false,
            'expect_retry_success'     => true,
            'retry_run_count'          => 2,
            'retry_failure_runs'       => [1 => 'Provider answered with HTTP 429.'],
        ],
        'missing-provider-id-repeated-timeout' => [
            'seed_provider_id'         => false,
            'tracking_status_sequence' => [200],
            'send_timeout_count'       => 2,
            'expected_order'           => ['send', 'send', 'send', 'tracking 200'],
            'expected_send_count'      => 3,
            'expected_tracking_ids'    => ['SIMULATED-PROVIDER-ID'],
            'compare_sends'            => true,
            'expect_retry_success'     => true,
            'retry_run_count'          => 2,
            'timeout_on_retry_runs'    => [1],
        ],
        'tracking-service-unavailable-no-resend' => [
            'seed_provider_id'         => true,
            'tracking_status_sequence' => [503],
            'expected_order'           => ['send', 'tracking 503'],
            'expected_send_count'      => 1,
            'expected_tracking_ids'    => ['SIMULATED-STORED-ID'],
            'compare_sends'            => false,
            'expect_retry_success'     => false,
            'expected_retry_error'     => 'Provider answered with HTTP 503.',
        ],
        'tracking-request-timeout-408-no-resend' => [
            'seed_provider_id'         => true,
            'tracking_status_sequence' => [408],
            'expected_order'           => ['send', 'tracking 408'],
            'expected_send_count'      => 1,
            'expected_tracking_ids'    => ['SIMULATED-STORED-ID'],
            'compare_sends'            => false,
            'expect_retry_success'     => false,
            'expected_retry_error'     => 'Provider answered with HTTP 408.',
        ],
        'tracking-server-error-then-recovers' => [
            'seed_provider_id'         => true,
            'tracking_status_sequence' => [503, 200],
            'expected_order'           => ['send', 'tracking 503', 'tracking 200'],
            'expected_send_count'      => 1,
            'expected_tracking_ids'    => ['SIMULATED-STORED-ID', 'SIMULATED-STORED-ID'],
            'compare_sends'            => false,
            'expect_retry_success'     => true,
            'retry_run_count'          => 2,
            'retry_failure_runs'       => [1 => 'Provider answered with HTTP 503.'],
        ],
        'tracking-404-then-rate-limit-no-extra-send' => [
            'seed_provider_id'         => true,
            'tracking_status_sequence' => [404, 429],
            'expected_order'           => ['send', 'tracking 404', 'send', 'tracking 429'],
            'expected_send_count'      => 2,
            'expected_tracking_ids'    => ['SIMULATED-STORED-ID', 'SIMULATED-STORED-ID'],
            'send_provider_id'         => 'SIMULATED-STORED-ID',
            'compare_sends'            => true,
            'expect_retry_success'     => false,
            'expected_retry_error'     => 'Provider answered with HTTP 429.',
        ],
        'send-replay-rejected' => [
            'seed_provider_id'          => false,
            'tracking_status_sequence'  => [200],
            'send_response_status'      => 'REJECTED',
            'expected_persisted_status' => 'REJECTED',
            'expected_order'            => ['send', 'send', 'tracking 200'],
            'expected_send_count'       => 2,
            'expected_tracking_ids'     => ['SIMULATED-PROVIDER-ID'],
            'compare_sends'             => true,
            'expect_retry_success'      => true,
        ],
    ];
    $results = [];

    foreach ($scenarios as $scenarioName => $scenario) {
        $scenarioDirectory = ResourcePath::join($simulationDirectory, $scenarioName);

        if (! mkdir($scenarioDirectory, 0700)) {
            throw new RuntimeException('Could not create the temporary directory for ' . $scenarioName . '.');
        }

        $examplePath = ResourcePath::join($scenarioDirectory, 'invoice-example.php');

        if (file_put_contents($examplePath, $example) === false) {
            throw new RuntimeException('Could not write the temporary invoice example for ' . $scenarioName . '.');
        }

        putenv('EFAKTURA_SUBMISSION_DB=' . ResourcePath::join($scenarioDirectory, 'attempts.sqlite'));
        putenv('EFAKTURA_SIMULATION_LOG=' . ResourcePath::join($scenarioDirectory, 'events.json'));
        putenv('EFAKTURA_SIMULATION_TRACKING_STATUS_SEQUENCE=' . json_encode(
            $scenario['tracking_status_sequence'],
            JSON_THROW_ON_ERROR
        ));
        putenv('EFAKTURA_SIMULATION_SEND_TIMEOUT_COUNT=' . ($scenario['send_timeout_count'] ?? '1'));
        putenv('EFAKTURA_SIMULATION_SEND_PROVIDER_ID'
            . '=' . ($scenario['send_provider_id'] ?? 'SIMULATED-PROVIDER-ID'));
        putenv('EFAKTURA_SIMULATION_SEND_RESPONSE_STATUS=' . ($scenario['send_response_status'] ?? 'ACCEPTED'));
        putenv('EFAKTURA_SIMULATION_BARRIER_DIRECTORY=');
        putenv('EFAKTURA_SUBMISSION_REVISION=1');

        // The initial send must pass local validation and then fail only after the adapter records the timeout.
        $firstRun = $runPhp($examplePath, $root);

        if (
            $firstRun['exit_code'] === 0
            || strpos($firstRun['output'], 'Faktúra prešla lokálnou validáciou.') === false
            || strpos($firstRun['output'], 'Simulated timeout after submission request.') === false
        ) {
            throw new RuntimeException('Initial timeout run failed for ' . $scenarioName . ': ' . $firstRun['output']);
        }

        // Some cases model a pending attempt whose provider ID was already known from an earlier response.
        if ($scenario['seed_provider_id']) {
            $database = new PDO('sqlite:' . getenv('EFAKTURA_SUBMISSION_DB'));
            $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $seedProviderId = $database->prepare(
                "UPDATE sapi_submission_attempts
                 SET provider_document_id = :provider_document_id, status = :status
                 WHERE document_id = :document_id AND status = 'PENDING'"
            );
            $seedProviderId->execute([
                'provider_document_id' => 'SIMULATED-STORED-ID',
                'status'               => $scenario['seed_status'] ?? 'PENDING',
                'document_id'          => 'DEMO-INV-001',
            ]);

            if ($seedProviderId->rowCount() !== 1) {
                throw new RuntimeException('Could not seed a pending attempt for ' . $scenarioName . '.');
            }

            $seedProviderId = null;
            $database       = null;
        }

        if (! empty($scenario['change_payload'])) {
            // Change only the temporary example copy so payload-revision cases never edit the maintained documentation.
            $changedExample = str_replace("'Ukážková služba'", "'Changed sample service'", $example, $changeCount);

            if ($changeCount !== 1 || file_put_contents($examplePath, $changedExample) === false) {
                throw new RuntimeException('Could not change the payload for the payload-change simulation.');
            }
        }

        if (isset($scenario['retry_revision'])) {
            putenv('EFAKTURA_SUBMISSION_REVISION=' . $scenario['retry_revision']);
        }

        $retryRuns = [];

        // Start all retries before releasing the injected barrier so they contend on the same persisted attempt.
        if (! empty($scenario['concurrent_retry_count'])) {
            putenv('EFAKTURA_SIMULATION_BARRIER_DIRECTORY=' . $scenarioDirectory);
            $runningProcesses = [];
            for ($retryIndex = 0; $retryIndex < $scenario['concurrent_retry_count']; $retryIndex++) {
                $runningProcesses[] = $startPhp($examplePath, $root);
            }

            foreach ($runningProcesses as $runningProcess) {
                $retryRuns[] = $finishPhp($runningProcess);
            }

            foreach ($retryRuns as $retryRun) {
                if (
                    $retryRun['exit_code'] !== 0
                    || strpos($retryRun['output'], 'Faktúra prešla lokálnou validáciou.') === false
                ) {
                    throw new RuntimeException(
                        'Concurrent retry failed for ' . $scenarioName . ': ' . $retryRun['output']
                    );
                }
            }

            putenv('EFAKTURA_SIMULATION_BARRIER_DIRECTORY=');
        } else {
            $retryRunCount = $scenario['retry_run_count'] ?? 1;
            for ($retryIndex = 1; $retryIndex <= $retryRunCount; $retryIndex++) {
                $failResponsePersistence = in_array(
                    $retryIndex,
                    $scenario['persistence_failure_on_retry_runs'] ?? [],
                    true
                );

                if ($failResponsePersistence) {
                    // Fail only the accepted-status update, then verify the transaction left the attempt pending.
                    $database = new PDO('sqlite:' . getenv('EFAKTURA_SUBMISSION_DB'));
                    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $database->exec(
                        "CREATE TRIGGER fail_response_persistence BEFORE UPDATE OF status
                         ON sapi_submission_attempts
                         WHEN OLD.status = 'PENDING' AND NEW.status = 'ACCEPTED'
                         BEGIN SELECT RAISE(ABORT, 'Simulated response persistence failure'); END"
                    );
                    $database = null;
                }

                $retryRun    = $runPhp($examplePath, $root);
                $retryRuns[] = $retryRun;

                if ($failResponsePersistence) {
                    $database = new PDO('sqlite:' . getenv('EFAKTURA_SUBMISSION_DB'));
                    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $database->exec('DROP TRIGGER fail_response_persistence');
                    $database = null;

                    if (
                        $retryRun['exit_code'] === 0
                        || strpos($retryRun['output'], 'Faktúra prešla lokálnou validáciou.') === false
                        || strpos($retryRun['output'], 'Simulated response persistence failure') === false
                    ) {
                        throw new RuntimeException(
                            'Expected the accepted SAPI response persistence failure in '
                            . $scenarioName . ' (exit=' . $retryRun['exit_code']
                            . ', validation=' . (strpos(
                                $retryRun['output'],
                                'Faktúra prešla lokálnou validáciou.'
                            ) !== false ? 'yes' : 'no')
                            . ', persistence_error=' . (strpos(
                                $retryRun['output'],
                                'Simulated response persistence failure'
                            ) !== false ? 'yes' : 'no')
                            . '): ' . $retryRun['output']
                        );
                    }

                    $database           = new PDO('sqlite:' . getenv('EFAKTURA_SUBMISSION_DB'));
                    $findPendingAttempt = $database->prepare(
                        'SELECT status, provider_document_id FROM sapi_submission_attempts
                         WHERE document_id = :document_id'
                    );
                    $findPendingAttempt->execute(['document_id' => 'DEMO-INV-001']);
                    $pendingAttempt = $findPendingAttempt->fetch(PDO::FETCH_ASSOC);

                    if (
                        $pendingAttempt === false
                        || $pendingAttempt['status'] !== 'PENDING'
                        || $pendingAttempt['provider_document_id'] !== null
                    ) {
                        throw new RuntimeException(
                            'The failed response update partially changed the pending attempt in ' . $scenarioName . '.'
                        );
                    }

                    $findPendingAttempt = null;
                    $database           = null;
                    continue;
                }

                $shouldTimeout = in_array(
                    $retryIndex,
                    $scenario['timeout_on_retry_runs'] ?? [],
                    true
                );

                if ($shouldTimeout) {
                    if (
                        $retryRun['exit_code'] === 0
                        || strpos($retryRun['output'], 'Faktúra prešla lokálnou validáciou.') === false
                        || strpos($retryRun['output'], 'Simulated timeout after submission request.') === false
                    ) {
                        throw new RuntimeException(
                            'Expected another timeout for ' . $scenarioName . ': ' . $retryRun['output']
                        );
                    }
                } elseif (isset($scenario['retry_failure_runs'][$retryIndex])) {
                    $expectedError = $scenario['retry_failure_runs'][$retryIndex];

                    if (
                        $retryRun['exit_code'] === 0
                        || strpos($retryRun['output'], 'Faktúra prešla lokálnou validáciou.') === false
                        || strpos($retryRun['output'], $expectedError) === false
                    ) {
                        throw new RuntimeException(
                            'Expected retry failure did not occur for ' . $scenarioName . ': ' . $retryRun['output']
                        );
                    }
                } elseif ($scenario['expect_retry_success']) {
                    if (
                        $retryRun['exit_code'] !== 0
                        || strpos($retryRun['output'], 'Faktúra prešla lokálnou validáciou.') === false
                    ) {
                        throw new RuntimeException(
                            'Retry run failed for ' . $scenarioName . ': ' . $retryRun['output']
                        );
                    }
                } elseif (
                    $retryRun['exit_code'] === 0
                    || strpos($retryRun['output'], 'Faktúra prešla lokálnou validáciou.') === false
                    || strpos($retryRun['output'], $scenario['expected_retry_error']) === false
                ) {
                    throw new RuntimeException('Expected retry failure did not occur for ' . $scenarioName . '.');
                }
            }
        }

        // Validate observed adapter calls and persisted attempts against the scenario's expected replay behavior.
        $eventLog = file_get_contents(getenv('EFAKTURA_SIMULATION_LOG'));
        $events   = json_decode(
            (string) $eventLog,
            true,
            RepositoryToolDefaults::JSON_MAX_DEPTH,
            JSON_THROW_ON_ERROR
        );
        $sendEvents = array_values(array_filter($events, static function (array $event): bool {
            return $event['type'] === 'send';
        }));
        $trackingEvents = array_values(array_filter($events, static function (array $event): bool {
            return $event['type'] === 'tracking';
        }));
        $eventOrder = array_map(static function (array $event): string {
            return $event['type'] === 'tracking' ? 'tracking ' . $event['status_code'] : 'send';
        }, $events);

        if (! empty($scenario['concurrent_retry_count'])) {
            if (
                $eventOrder === []
                || $eventOrder[0] !== 'send'
                || count($events) !== 1 + $scenario['concurrent_retry_count'] * 2
                || count(array_filter($events, static function (array $event): bool {
                    return $event['type'] === 'tracking' && $event['status_code'] === 200;
                })) !== $scenario['concurrent_retry_count']
            ) {
                throw new RuntimeException('Unexpected concurrent call order in ' . $scenarioName . ': ' . json_encode($eventOrder));
            }
        } elseif ($eventOrder !== $scenario['expected_order']) {
            throw new RuntimeException('Unexpected call order in ' . $scenarioName . ': ' . json_encode($eventOrder));
        }

        if (count($sendEvents) !== $scenario['expected_send_count']) {
            throw new RuntimeException('Unexpected send count in ' . $scenarioName . '.');
        }

        $trackingIds = array_map(static function (array $event): string {
            return $event['provider_document_id'];
        }, $trackingEvents);

        if ($trackingIds !== $scenario['expected_tracking_ids']) {
            throw new RuntimeException('Tracking used unexpected provider IDs in ' . $scenarioName . '.');
        }

        $sendProviderId   = $scenario['send_provider_id'] ?? 'SIMULATED-PROVIDER-ID';
        $sendTimeoutCount = $scenario['send_timeout_count'] ?? 1;

        foreach ($sendEvents as $sendEvent) {
            $expectedProviderId = $sendEvent['number'] <= $sendTimeoutCount ? null : $sendProviderId;

            if ($sendEvent['response_provider_document_id'] !== $expectedProviderId) {
                throw new RuntimeException('Unexpected provider ID response in ' . $scenarioName . '.');
            }

            $expectedResponseStatus = $sendEvent['number'] <= $sendTimeoutCount
                ? null
                : ($scenario['send_response_status'] ?? 'ACCEPTED');

            if (($sendEvent['response_status'] ?? null) !== $expectedResponseStatus) {
                throw new RuntimeException('Unexpected SAPI response status in ' . $scenarioName . '.');
            }
        }

        if ($scenario['compare_sends']) {
            // A replay of the same attempt must retain its key and all persisted payload identity fields.
            foreach (array_slice($sendEvents, 1) as $sendEvent) {
                foreach (
                    [
                        'idempotency_key',
                        'document_id',
                        'document_type_id',
                        'process_id',
                        'sender',
                        'receiver',
                        'creation_datetime',
                        'xml',
                        'checksum',
                    ] as $field
                ) {
                    if ($sendEvents[0][$field] !== $sendEvent[$field]) {
                        throw new RuntimeException($scenarioName . ' changed the saved ' . $field . '.');
                    }
                }
            }
        }

        if (! empty($scenario['assert_original_key_stored'])) {
            $database          = new PDO('sqlite:' . getenv('EFAKTURA_SUBMISSION_DB'));
            $findStoredAttempt = $database->prepare(
                'SELECT idempotency_key, status FROM sapi_submission_attempts WHERE document_id = :document_id'
            );
            $findStoredAttempt->execute(['document_id' => 'DEMO-INV-001']);
            $storedAttempt = $findStoredAttempt->fetch(PDO::FETCH_ASSOC);

            if (
                $storedAttempt === false
                || $storedAttempt['idempotency_key'] !== $sendEvents[0]['idempotency_key']
                || $storedAttempt['status'] !== 'PENDING'
            ) {
                throw new RuntimeException('The original idempotency key was not retained after payload change.');
            }

            $findStoredAttempt = null;
            $database          = null;
        }

        if (! empty($scenario['assert_new_attempt'])) {
            if (
                $sendEvents[0]['idempotency_key'] === $sendEvents[1]['idempotency_key']
                || $sendEvents[0]['xml'] === $sendEvents[1]['xml']
            ) {
                throw new RuntimeException('A changed payload under a new revision must have a new XML and idempotency key.');
            }

            $database     = new PDO('sqlite:' . getenv('EFAKTURA_SUBMISSION_DB'));
            $attemptCount = (int) $database->query(
                "SELECT COUNT(*) FROM sapi_submission_attempts WHERE document_id = 'DEMO-INV-001'"
            )->fetchColumn();
            $findAttemptByKey = $database->prepare(
                'SELECT status, payload_xml FROM sapi_submission_attempts WHERE idempotency_key = :idempotency_key'
            );
            $findAttemptByKey->execute(['idempotency_key' => $sendEvents[0]['idempotency_key']]);
            $originalAttempt = $findAttemptByKey->fetch(PDO::FETCH_ASSOC);
            $findAttemptByKey->execute(['idempotency_key' => $sendEvents[1]['idempotency_key']]);
            $newAttempt = $findAttemptByKey->fetch(PDO::FETCH_ASSOC);

            if (
                $attemptCount !== 2
                || $originalAttempt === false
                || $originalAttempt['status'] !== 'PENDING'
                || $originalAttempt['payload_xml'] !== $sendEvents[0]['xml']
                || $newAttempt === false
                || $newAttempt['status'] !== 'ACCEPTED'
                || $newAttempt['payload_xml'] !== $sendEvents[1]['xml']
            ) {
                throw new RuntimeException('The new revision did not create a separate persisted submission attempt.');
            }

            $findAttemptByKey = null;
            $database         = null;
        }

        if (! empty($scenario['assert_seed_status_retained'])) {
            $database          = new PDO('sqlite:' . getenv('EFAKTURA_SUBMISSION_DB'));
            $findSeededAttempt = $database->prepare(
                'SELECT status, provider_document_id FROM sapi_submission_attempts WHERE document_id = :document_id'
            );
            $findSeededAttempt->execute(['document_id' => 'DEMO-INV-001']);
            $seededAttempt = $findSeededAttempt->fetch(PDO::FETCH_ASSOC);

            if (
                $seededAttempt === false
                || $seededAttempt['status'] !== $scenario['seed_status']
                || $seededAttempt['provider_document_id'] !== 'SIMULATED-STORED-ID'
            ) {
                throw new RuntimeException('Tracking 404 changed the saved non-pending status in ' . $scenarioName . '.');
            }

            $findSeededAttempt = null;
            $database          = null;
        }

        if (! empty($scenario['assert_concurrent_attempt'])) {
            $database              = new PDO('sqlite:' . getenv('EFAKTURA_SUBMISSION_DB'));
            $findConcurrentAttempt = $database->prepare(
                'SELECT idempotency_key, status, provider_document_id, payload_xml
                 FROM sapi_submission_attempts WHERE document_id = :document_id'
            );
            $findConcurrentAttempt->execute(['document_id' => 'DEMO-INV-001']);
            $concurrentAttempt = $findConcurrentAttempt->fetch(PDO::FETCH_ASSOC);
            $attemptCount      = (int) $database->query(
                "SELECT COUNT(*) FROM sapi_submission_attempts WHERE document_id = 'DEMO-INV-001'"
            )->fetchColumn();

            if (
                $attemptCount !== 1
                || $concurrentAttempt === false
                || $concurrentAttempt['status'] !== 'ACCEPTED'
                || $concurrentAttempt['provider_document_id'] !== $sendProviderId
                || $concurrentAttempt['idempotency_key'] !== $sendEvents[0]['idempotency_key']
                || $concurrentAttempt['payload_xml'] !== $sendEvents[0]['xml']
            ) {
                throw new RuntimeException('Concurrent retries did not retain one accepted attempt with the original key and payload.');
            }

            $findConcurrentAttempt = null;
            $database              = null;
        }

        if (! empty($scenario['assert_final_replay_attempt'])) {
            $database         = new PDO('sqlite:' . getenv('EFAKTURA_SUBMISSION_DB'));
            $findFinalAttempt = $database->prepare(
                'SELECT idempotency_key, status, provider_document_id, payload_xml
                 FROM sapi_submission_attempts WHERE document_id = :document_id'
            );
            $findFinalAttempt->execute(['document_id' => 'DEMO-INV-001']);
            $finalAttempt = $findFinalAttempt->fetch(PDO::FETCH_ASSOC);

            if (
                $finalAttempt === false
                || $finalAttempt['status'] !== 'ACCEPTED'
                || $finalAttempt['provider_document_id'] !== $sendProviderId
                || $finalAttempt['idempotency_key'] !== $sendEvents[0]['idempotency_key']
                || $finalAttempt['payload_xml'] !== $sendEvents[0]['xml']
            ) {
                throw new RuntimeException(
                    'The replay after a response persistence failure did not retain the original attempt.'
                );
            }

            $findFinalAttempt = null;
            $database         = null;
        }

        if (isset($scenario['expected_persisted_status'])) {
            $database    = new PDO('sqlite:' . getenv('EFAKTURA_SUBMISSION_DB'));
            $findAttempt = $database->prepare(
                'SELECT status FROM sapi_submission_attempts WHERE document_id = :document_id'
            );
            $findAttempt->execute(['document_id' => 'DEMO-INV-001']);
            $persistedAttempt = $findAttempt->fetch(PDO::FETCH_ASSOC);

            if (
                $persistedAttempt === false
                || $persistedAttempt['status'] !== $scenario['expected_persisted_status']
            ) {
                throw new RuntimeException('Unexpected persisted SAPI status in ' . $scenarioName . '.');
            }

            $findAttempt = null;
            $database    = null;
        }

        $results[] = [
            'scenario'                                     => $scenarioName,
            'call_order'                                   => $eventOrder,
            'send_count'                                   => count($sendEvents),
            'same_payload_and_idempotency_key'             => $scenario['compare_sends'],
            'retry_blocked'                                => ! $scenario['expect_retry_success'],
            'original_key_not_used_for_a_retry'            => ! empty($scenario['assert_original_key_stored']),
            'new_revision_created_new_key'                 => ! empty($scenario['assert_new_attempt']),
            'concurrent_attempt_persisted_once'            => ! empty($scenario['assert_concurrent_attempt']),
            'response_persistence_failure_replayed_safely' => ! empty($scenario['assert_final_replay_attempt']),
            'persisted_status'                             => $scenario['expected_persisted_status'] ?? null,
        ];
    }

    echo 'All local simulations passed: ' . json_encode([
        'scenarios'          => $results,
        'real_network_calls' => 0,
    ]) . PHP_EOL;
} finally {
    // Restore caller environment and remove only the temporary tree created beneath the system temp directory.
    if (isset($findAttemptByKey)) {
        $findAttemptByKey = null;
    }

    if (isset($findAttempt)) {
        $findAttempt = null;
    }

    if (isset($seedProviderId)) {
        $seedProviderId = null;
    }

    if (isset($database)) {
        $database = null;
    }

    foreach ($environmentNames as $name) {
        $value = $originalEnvironment[$name];
        putenv($name . ($value === false ? '' : '=' . $value));
    }

    $temporaryPath   = realpath($simulationDirectory);
    $temporaryPrefix = $temporaryRoot === false ? '' : $temporaryRoot . DIRECTORY_SEPARATOR;

    if (
        $temporaryPath !== false
        && $temporaryPrefix !== ''
        && strpos($temporaryPath, $temporaryPrefix) === 0
    ) {
        /**
         * @var Closure(string): void $removeTree Recursively removes the validated temporary simulation
         *                            directory and its contents.
         */
        $removeTree = null;
        $removeTree = static function (string $directory) use (&$removeTree): void {
            foreach (scandir($directory) as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $path = ResourcePath::join($directory, $entry);

                if (is_dir($path) && ! is_link($path)) {
                    $removeTree($path);
                    continue;
                }

                unlink($path);
            }

            rmdir($directory);
        };
        $removeTree($temporaryPath);
    }
}
