<?php

/**
 * Compares configured validation-resource release values with their upstream sources and prints changes as JSON.
 *
 * This command makes HTTPS requests to the endpoints listed in the validation manifest.
 */

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationResources;
use DreaM1\EfakturaConnector\Tools\RepositoryToolDefaults;

require_once __DIR__ . '/bootstrap.php';

$manifestPath     = ValidationResources::path(ValidationResources::RULES_MANIFEST_FILENAME);
$manifestContents = @file_get_contents($manifestPath);

if (! is_string($manifestContents)) {
    fwrite(STDERR, "Could not read validation rules manifest.\n");
    exit(1);
}

try {
    $manifest = json_decode($manifestContents, true, RepositoryToolDefaults::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fwrite(STDERR, 'Invalid validation rules manifest: ' . $exception->getMessage() . "\n");
    exit(1);
}

if (! is_array($manifest) || empty($manifest['upstream_watch']) || ! is_array($manifest['upstream_watch'])) {
    fwrite(STDERR, "The manifest has no upstream release sources to check.\n");
    exit(1);
}

/**
 * @var Closure(string): string $fetch Fetches a configured endpoint, with a 16 MiB response limit.
 */
$fetch = static function (string $url): string {
    $handle = curl_init($url);

    if ($handle === false) {
        throw new RuntimeException('Could not initialize the HTTP client.');
    }

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_MAXFILESIZE    => 16777216,
        CURLOPT_USERAGENT      => 'efaktura-connector-validation-updater/1.0',
        CURLOPT_HTTPHEADER     => ['Accept: application/json, text/html;q=0.9, */*;q=0.8'],
    ]);

    $body   = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error  = curl_error($handle);

    // PHP 7.4 uses a resource handle; PHP 8+ releases CurlHandle objects automatically.
    if (is_resource($handle)) {
        curl_close($handle);
    }

    unset($handle);

    if (! is_string($body) || $status < 200 || $status >= 300) {
        throw new RuntimeException("Request failed for {$url}: HTTP {$status}" . ($error !== '' ? " ({$error})" : ''));
    }

    return $body;
};

/**
 * @var Closure(array<string, mixed>, string): string $parseLatest Extracts a release value or content digest
 *                                                    from a configured source's response using its adapter.
 */
$parseLatest = static function (array $source, string $body): string {
    // Each adapter normalizes its source-specific response to a release string or content digest.
    switch ($source['adapter']) {
        case 'github-latest-release':
            try {
                $release = json_decode($body, true, RepositoryToolDefaults::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException("Invalid GitHub release response: {$exception->getMessage()}");
            }

            $version = is_array($release) ? ($release['tag_name'] ?? null) : null;

            if (! is_string($version) || ! preg_match('/^[A-Za-z0-9._+-]{1,100}$/', $version)) {
                throw new RuntimeException('GitHub response did not contain a release tag.');
            }

            return $version;

        case 'peppol-release-notes':
            // Suppress parser diagnostics for upstream HTML and restore the caller's libxml setting afterward.
            $previousState = libxml_use_internal_errors(true);
            $document      = new DOMDocument();
            $loaded        = $document->loadHTML($body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            libxml_use_internal_errors($previousState);

            if (! $loaded) {
                throw new RuntimeException('Could not parse the Peppol release notes page.');
            }

            $xpath    = new DOMXPath($document);
            $headings = $xpath->query('//h1 | //h2 | //h3');

            if ($headings === false) {
                throw new RuntimeException('Could not read headings from the Peppol release notes page.');
            }

            foreach ($headings as $heading) {
                if (preg_match('/\bVersion\s+([0-9]+(?:\.[0-9]+)+)/i', $heading->textContent, $matches)) {
                    return $matches[1];
                }
            }

            throw new RuntimeException('Could not find a version heading in the Peppol release notes page.');

        case 'peppol-edec-codelists':
            if (preg_match('/latest code list release is\s+v?([0-9]+(?:\.[0-9]+)+)\s+from\s+([0-9-]+)/i', $body, $matches)) {
                return $matches[1];
            }

            throw new RuntimeException('Could not find the latest release in the OpenPeppol eDEC code lists page.');

        case 'sha256':
            return hash('sha256', $body);
    }

    throw new RuntimeException("Unsupported upstream monitor adapter: {$source['adapter']}");
};

$changes = [];

foreach ($manifest['upstream_watch'] as $source) {
    if (! is_array($source) || empty($source['id']) || empty($source['adapter']) || empty($source['source']) || empty($source['endpoint']) || ! isset($source['current'])) {
        fwrite(STDERR, "Invalid upstream source entry in the validation rules manifest.\n");
        exit(1);
    }

    try {
        $latest = $parseLatest($source, $fetch($source['endpoint']));
    } catch (RuntimeException $exception) {
        fwrite(STDERR, "Could not check {$source['id']}: {$exception->getMessage()}\n");
        exit(1);
    }

    if ($latest !== $source['current']) {
        $changes[] = [
            'id'      => $source['id'],
            'source'  => $source['source'],
            'current' => $source['current'],
            'latest'  => $latest,
        ];
    }
}

echo json_encode($changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
