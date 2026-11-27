<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron;

/** Test switches for failures that are otherwise dependent on the host operating system. */
final class SaxonProcessExecutorTestControl
{
    public static $failProcessStart       = false;
    public static $disableProcessFunction = false;
    public static $hideProcessStatus      = false;
    public static $failTemporaryFile      = false;
    public static $shortInputWrite        = false;
    public static $hideReport             = false;
    public static $hideProcessError       = false;

    /** Clears all process and temporary-file fault switches. */
    public static function reset(): void
    {
        self::$failProcessStart       = false;
        self::$disableProcessFunction = false;
        self::$hideProcessStatus      = false;
        self::$failTemporaryFile      = false;
        self::$shortInputWrite        = false;
        self::$hideReport             = false;
        self::$hideProcessError       = false;
    }
}

/**
 * Applies the configured process-function visibility override in this namespace.
 *
 * @param  string $function Function name queried by the code under test.
 * @return bool   Whether the function is available.
 */
function function_exists($function)
{
    if (SaxonProcessExecutorTestControl::$disableProcessFunction && $function === 'proc_open') {
        return false;
    }

    return \function_exists($function);
}

/**
 * Applies the process-start fault switch before delegating to PHP's proc_open.
 *
 * @param  string|array<int, string>  $command        Command and optional arguments.
 * @param  array<int, mixed>          $descriptorSpec Process descriptor specification.
 * @param  array<int, resource>|null  $pipes          Receives process pipe handles.
 * @param  string|null                $cwd            Optional working directory.
 * @param  array<string, string>|null $env            Optional process environment.
 * @param  array<string, mixed>|null  $options        Optional process creation options.
 * @return resource|false             Process resource or false when the fault switch is enabled.
 */
function proc_open($command, $descriptorSpec, &$pipes, $cwd = null, $env = null, $options = null)
{
    if (SaxonProcessExecutorTestControl::$failProcessStart) {
        $pipes = [];

        return false;
    }

    return \proc_open($command, $descriptorSpec, $pipes, $cwd, $env, $options);
}

/**
 * Applies the process-status fault switch before delegating to PHP.
 *
 * @param  resource                   $process Process resource to inspect.
 * @return array<string, mixed>|false Process status or false when hidden by the fault switch.
 */
function proc_get_status($process)
{
    if (SaxonProcessExecutorTestControl::$hideProcessStatus) {
        return false;
    }

    return \proc_get_status($process);
}

/**
 * Applies the temporary-file fault switch before delegating to PHP.
 *
 * @param  string       $directory Directory for the temporary file.
 * @param  string       $prefix    Temporary file name prefix.
 * @return string|false Temporary file path or false when creation failure is enabled.
 */
function tempnam($directory, $prefix)
{
    if (SaxonProcessExecutorTestControl::$failTemporaryFile) {
        return false;
    }

    return \tempnam($directory, $prefix);
}

/**
 * Applies the simulated short-write fault before delegating to PHP.
 *
 * @param  string        $filename Destination path.
 * @param  string        $data     Content to write.
 * @param  int           $flags    File write flags.
 * @param  resource|null $context  Optional stream context.
 * @return int|false     Bytes written, zero for the simulated short write, or false on failure.
 */
function file_put_contents($filename, $data, $flags = 0, $context = null)
{
    if (SaxonProcessExecutorTestControl::$shortInputWrite) {
        return 0;
    }

    return \file_put_contents(...func_get_args());
}

/**
 * Applies report-read and process-error fault switches before delegating to PHP.
 *
 * @param  string        $filename       File path to read.
 * @param  bool          $useIncludePath Whether to search the include path.
 * @param  resource|null $context        Optional stream context.
 * @param  int           $offset         Read offset in bytes.
 * @param  int|null      $length         Maximum number of bytes to read.
 * @return string|false  File content or false when the fault switch hides it.
 */
function file_get_contents($filename, $useIncludePath = false, $context = null, $offset = 0, $length = null)
{
    if (SaxonProcessExecutorTestControl::$hideReport && func_num_args() === 1) {
        return false;
    }

    if (SaxonProcessExecutorTestControl::$hideProcessError && func_num_args() === 5) {
        return false;
    }

    return \file_get_contents(...func_get_args());
}
