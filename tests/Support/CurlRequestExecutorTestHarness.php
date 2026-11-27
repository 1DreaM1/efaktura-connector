<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution {
    /** Provides shared setup for cURL request executor tests. */
    final class CurlRequestExecutorTestHarness
    {
        public static $initFails = false;


        public static $configurationSucceeds = true;
        public static $execResult            = 'success';
        public static $error                 = '';
        public static $status                = 200;
        public static $responseBody          = '';
        public static $responseHeaders       = [];
        public static $url;
        public static $options       = [];
        public static $closed        = false;
        public static $initCount     = 0;
        public static $resetCount    = 0;
        public static $closeCount    = 0;
        public static $optionHistory = [];
        public static $handleIds     = [];

        /** Resets all cURL shim settings and call records to their defaults. */
        public static function reset(): void
        {
            self::$initFails             = false;
            self::$configurationSucceeds = true;
            self::$execResult            = 'success';
            self::$error                 = '';
            self::$status                = 200;
            self::$responseBody          = '';
            self::$responseHeaders       = [];
            self::$url                   = null;
            self::$options               = [];
            self::$closed                = false;
            self::$initCount             = 0;
            self::$resetCount            = 0;
            self::$closeCount            = 0;
            self::$optionHistory         = [];
            self::$handleIds             = [];
        }
    }

    /**
     * Initializes the fake cURL handle and records its requested URL.
     *
     * @param  string|null    $url URL passed to the executor.
     * @return resource|false Fake handle, or false when initialization failure is enabled.
     */
    function curl_init($url = null)
    {
        CurlRequestExecutorTestHarness::$initCount++;
        CurlRequestExecutorTestHarness::$url = $url;

        return CurlRequestExecutorTestHarness::$initFails ? false : fopen('php://memory', 'r+');
    }

    /**
     * Stores options for the fake handle and records option history.
     *
     * @param  resource                 $handle  Fake handle to configure.
     * @param  array<int|string, mixed> $options cURL option values supplied by the executor.
     * @return bool                     Whether the fake reports successful configuration.
     */
    function curl_setopt_array($handle, array $options): bool
    {
        CurlRequestExecutorTestHarness::$options         = $options;
        CurlRequestExecutorTestHarness::$optionHistory[] = $options;
        CurlRequestExecutorTestHarness::$handleIds[]     = (int) $handle;

        return CurlRequestExecutorTestHarness::$configurationSucceeds;
    }

    /**
     * Resets the fake handle's request options and URL.
     *
     * @param resource $handle Fake handle to reset.
     */
    function curl_reset($handle): void
    {
        CurlRequestExecutorTestHarness::$resetCount++;
        CurlRequestExecutorTestHarness::$options = [];
        CurlRequestExecutorTestHarness::$url     = null;
    }

    /**
     * Runs the configured fake response callbacks and returns the response body or failure.
     *
     * @param  resource     $handle Fake handle being executed.
     * @return string|false Configured response body or a simulated execution failure.
     */
    function curl_exec($handle)
    {
        $options = CurlRequestExecutorTestHarness::$options;

        foreach (CurlRequestExecutorTestHarness::$responseHeaders as $header) {
            ($options[CURLOPT_HEADERFUNCTION])($handle, $header . "\r\n");
        }

        if (CurlRequestExecutorTestHarness::$responseBody !== '') {
            $written = ($options[CURLOPT_WRITEFUNCTION])($handle, CurlRequestExecutorTestHarness::$responseBody);

            if ($written !== strlen(CurlRequestExecutorTestHarness::$responseBody)) {
                CurlRequestExecutorTestHarness::$error = 'synthetic response write failure';

                return false;
            }
        }

        return CurlRequestExecutorTestHarness::$execResult;
    }

    /**
     * Returns the error string configured by the fake cURL execution.
     *
     * @param  resource $handle Fake handle queried for an error.
     * @return string   Configured cURL error message.
     */
    function curl_error($handle): string
    {
        return CurlRequestExecutorTestHarness::$error;
    }

    /**
     * Returns the configured fake HTTP status for cURL info requests.
     *
     * @param  resource $handle Fake handle queried for information.
     * @param  int      $option cURL information option.
     * @return int      Configured HTTP status code.
     */
    function curl_getinfo($handle, $option)
    {
        return CurlRequestExecutorTestHarness::$status;
    }

    /**
     * Records that the fake cURL handle was closed.
     *
     * @param resource $handle Fake handle being closed.
     */
    function curl_close($handle): void
    {
        CurlRequestExecutorTestHarness::$closeCount++;
        CurlRequestExecutorTestHarness::$closed = true;

        if (is_resource($handle)) {
            fclose($handle);
        }
    }
}
