<?php

/**
 * Checks validation manifest metadata, bundled artifact hashes, resource inventories, and version assertions.
 *
 * Exits with a non-zero status and lists every detected inconsistency.
 */

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationResources;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Support\HashAlgorithm;
use DreaM1\EfakturaConnector\Tools\RepositoryToolDefaults;

require_once __DIR__ . '/bootstrap.php';

$repositoryRoot = dirname(__DIR__);
$manifestPath   = ValidationResources::path(ValidationResources::RULES_MANIFEST_FILENAME);
$errors         = [];
$trackedFiles   = [];
$trackedHashes  = [];

$manifestContents = @file_get_contents($manifestPath);

if (! is_string($manifestContents)) {
    fwrite(STDERR, "Could not read validation rules manifest: {$manifestPath}\n");
    exit(1);
}

try {
    $manifest = json_decode($manifestContents, true, RepositoryToolDefaults::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fwrite(STDERR, 'Invalid validation rules manifest: ' . $exception->getMessage() . "\n");
    exit(1);
}

if (! is_array($manifest) || ($manifest['manifest_version'] ?? null) !== 1) {
    fwrite(STDERR, "Unsupported validation rules manifest version.\n");
    exit(1);
}

/**
 * @var Closure(string): ?string $resolveRepositoryPath Resolves a safe relative path inside the repository,
 *                               recording a diagnostic and returning null when the path is invalid or outside the repository.
 */
$resolveRepositoryPath = static function (string $relativePath) use ($repositoryRoot, &$errors): ?string {
    if ($relativePath === '' || strpos($relativePath, '\\') !== false) {
        $errors[] = "Invalid repository-relative path: {$relativePath}";

        return null;
    }

    // Reject traversal components here; the realpath boundary check below also catches symlinks escaping the repository.
    foreach (explode('/', $relativePath) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            $errors[] = "Unsafe repository-relative path: {$relativePath}";

            return null;
        }
    }

    $absolutePath = realpath(ResourcePath::join($repositoryRoot, $relativePath));
    $absoluteRoot = realpath($repositoryRoot);

    if ($absolutePath === false || $absoluteRoot === false) {
        $errors[] = "Missing path: {$relativePath}";

        return null;
    }

    $normalizedRoot   = rtrim(str_replace('\\', '/', $absoluteRoot), '/') . '/';
    $normalizedPath   = str_replace('\\', '/', $absolutePath);
    $insideRepository = DIRECTORY_SEPARATOR === '\\'
        ? strncasecmp($normalizedPath, $normalizedRoot, strlen($normalizedRoot)) === 0
        : strpos($normalizedPath, $normalizedRoot) === 0;

    // Containment is case-insensitive on Windows and bounded by a separator to reject sibling paths.
    if (! $insideRepository) {
        $errors[] = "Path resolves outside the repository: {$relativePath}";

        return null;
    }

    return $absolutePath;
};

/**
 * @var Closure(array<int, array<string, mixed>>, string): void $checkFileEntries Checks manifest file paths
 *                                                              and SHA-256 values, recording unique paths and expected hashes for later inventory checks.
 */
$checkFileEntries = static function (array $entries, string $owner) use (&$trackedFiles, &$trackedHashes, &$errors, $resolveRepositoryPath): void {
    foreach ($entries as $entry) {
        if (! is_array($entry) || ! isset($entry['path'], $entry['sha256'])) {
            $errors[] = "Invalid file entry in {$owner}.";
            continue;
        }

        $relativePath = $entry['path'];
        $expectedHash = strtolower((string) $entry['sha256']);

        if (! is_string($relativePath) || ! preg_match('/^[a-f0-9]{64}$/', $expectedHash)) {
            $errors[] = "Invalid path or SHA-256 in {$owner}.";
            continue;
        }

        if (isset($trackedFiles[$relativePath])) {
            $errors[] = "File is listed more than once in the manifest: {$relativePath}";
            continue;
        }

        $trackedFiles[$relativePath]  = true;
        $trackedHashes[$relativePath] = $expectedHash;

        $absolutePath = $resolveRepositoryPath($relativePath);

        if ($absolutePath === null || ! is_file($absolutePath) || ! is_readable($absolutePath)) {
            $errors[] = "Tracked artifact is not a readable file: {$relativePath}";
            continue;
        }

        $actualHash = hash_file(HashAlgorithm::SHA_256, $absolutePath);

        if (! is_string($actualHash) || ! hash_equals($expectedHash, strtolower($actualHash))) {
            $errors[] = "SHA-256 mismatch for {$relativePath}: expected {$expectedHash}, got " . (is_string($actualHash) ? strtolower($actualHash) : 'unavailable');
        }
    }
};

foreach (['artifacts', 'toolchains'] as $section) {
    if (! isset($manifest[$section]) || ! is_array($manifest[$section])) {
        $errors[] = "Manifest section '{$section}' is missing or invalid.";
        continue;
    }

    $identifiers = [];

    foreach ($manifest[$section] as $entry) {
        if (! is_array($entry) || empty($entry['id']) || empty($entry['version']) || empty($entry['source']) || ! isset($entry['files']) || ! is_array($entry['files'])) {
            $errors[] = "Invalid entry in manifest section '{$section}'.";
            continue;
        }

        if (isset($identifiers[$entry['id']])) {
            $errors[] = "Duplicate identifier '{$entry['id']}' in manifest section '{$section}'.";
        }

        $identifiers[$entry['id']] = true;
        $checkFileEntries($entry['files'], $entry['id']);
    }
}

if (! isset($manifest['compiler']['archive_sha256']) || ! preg_match('/^[a-f0-9]{64}$/i', (string) $manifest['compiler']['archive_sha256'])) {
    $errors[] = 'Compiler archive SHA-256 is missing or invalid.';
}

$watchAdapters    = ['github-latest-release', 'peppol-release-notes', 'peppol-edec-codelists', 'sha256'];
$watchIdentifiers = [];

foreach (($manifest['upstream_watch'] ?? []) as $source) {
    if (! is_array($source) || empty($source['id']) || empty($source['adapter']) || empty($source['source']) || empty($source['endpoint']) || ! isset($source['current'])) {
        $errors[] = 'Invalid upstream release source in manifest.';
        continue;
    }

    if (isset($watchIdentifiers[$source['id']])) {
        $errors[] = "Duplicate upstream monitor identifier '{$source['id']}'.";
    }

    $watchIdentifiers[$source['id']] = true;

    if (! in_array($source['adapter'], $watchAdapters, true)) {
        $errors[] = "Unsupported upstream monitor adapter '{$source['adapter']}' for {$source['id']}.";
    }

    if (parse_url($source['source'], PHP_URL_SCHEME) !== 'https' || parse_url($source['endpoint'], PHP_URL_SCHEME) !== 'https') {
        $errors[] = "Upstream monitor {$source['id']} must use HTTPS URLs.";
    }

    if ($source['adapter'] === 'sha256' && ! preg_match('/^[a-f0-9]{64}$/i', (string) $source['current'])) {
        $errors[] = "Invalid expected SHA-256 for upstream monitor {$source['id']}.";
    }

    if ($source['adapter'] === 'sha256') {
        $artifactPath = $source['artifact_path'] ?? null;

        if (! is_string($artifactPath) || ! isset($trackedHashes[$artifactPath])) {
            $errors[] = "Upstream SHA-256 monitor {$source['id']} must reference a manifest artifact.";
        } elseif (! hash_equals($trackedHashes[$artifactPath], strtolower((string) $source['current']))) {
            $errors[] = "Expected upstream SHA-256 for {$source['id']} does not match {$artifactPath}.";
        }
    }
}

foreach (($manifest['version_assertions'] ?? []) as $assertion) {
    if (! is_array($assertion) || ! isset($assertion['path'], $assertion['constant'], $assertion['value'])) {
        $errors[] = 'Invalid PHP version assertion in manifest.';
        continue;
    }

    $absolutePath = $resolveRepositoryPath($assertion['path']);

    if ($absolutePath === null || ! is_file($absolutePath) || ! is_readable($absolutePath)) {
        $errors[] = "Could not read version source: {$assertion['path']}";
        continue;
    }

    $source  = file_get_contents($absolutePath);
    $pattern = '/\bconst\s+' . preg_quote($assertion['constant'], '/') . '\s*=\s*([\'"])(.*?)\1\s*;/s';

    if (! is_string($source) || ! preg_match($pattern, $source, $matches)) {
        $errors[] = "Could not find constant {$assertion['constant']} in {$assertion['path']}.";
        continue;
    }

    if ($matches[2] !== $assertion['value']) {
        $errors[] = "Version mismatch for {$assertion['path']}::{$assertion['constant']}: expected '{$assertion['value']}', got '{$matches[2]}'.";
    }
}

foreach (($manifest['upstream_watch'] ?? []) as $source) {
    if (! isset($source['version_assertion'])) {
        continue;
    }

    $versionAssertion = $source['version_assertion'];

    if (! is_array($versionAssertion) || empty($versionAssertion['path']) || empty($versionAssertion['constant'])) {
        $errors[] = "Invalid version assertion reference for upstream monitor {$source['id']}.";
        continue;
    }

    foreach (($manifest['version_assertions'] ?? []) as $assertion) {
        if ($assertion['path'] !== $versionAssertion['path'] || $assertion['constant'] !== $versionAssertion['constant']) {
            continue;
        }

        $expectedCurrent = (string) ($versionAssertion['prefix'] ?? '') . $assertion['value'];

        if ($source['current'] !== $expectedCurrent) {
            $errors[] = "Upstream monitor {$source['id']} does not match {$versionAssertion['path']}::{$versionAssertion['constant']}.";
        }

        // Stop after the matched path/constant pair; one monitor reference binds to one version assertion.
        continue 2;
    }

    $errors[] = "No matching version assertion for upstream monitor {$source['id']}.";
}

foreach (($manifest['json_assertions'] ?? []) as $assertion) {
    if (! is_array($assertion) || empty($assertion['path'])) {
        $errors[] = 'Invalid JSON assertion in manifest.';
        continue;
    }

    $absolutePath = $resolveRepositoryPath($assertion['path']);

    if ($absolutePath === null || ! is_file($absolutePath) || ! is_readable($absolutePath)) {
        $errors[] = "Could not read JSON resource: {$assertion['path']}";
        continue;
    }

    try {
        $data = json_decode(
            (string) file_get_contents($absolutePath),
            true,
            RepositoryToolDefaults::JSON_MAX_DEPTH,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException $exception) {
        $errors[] = "Invalid JSON resource {$assertion['path']}: " . $exception->getMessage();
        continue;
    }

    foreach (($assertion['fields'] ?? []) as $field => $expectedValue) {
        if (! is_array($data) || ! array_key_exists($field, $data) || $data[$field] !== $expectedValue) {
            $errors[] = "JSON metadata mismatch for {$assertion['path']} field '{$field}'.";
        }
    }
}

foreach (($manifest['implementations'] ?? []) as $implementation) {
    if (! is_array($implementation) || empty($implementation['id']) || empty($implementation['sources']) || empty($implementation['tests'])) {
        $errors[] = 'Each rule implementation must have an id, source files, and test references.';
        continue;
    }

    foreach (array_merge($implementation['sources'], $implementation['tests']) as $relativePath) {
        $absolutePath = is_string($relativePath) ? $resolveRepositoryPath($relativePath) : null;

        if ($absolutePath === null || ! is_file($absolutePath) || ! is_readable($absolutePath)) {
            $errors[] = "Missing source or test referenced by {$implementation['id']}: {$relativePath}";
        }
    }
}

foreach (($manifest['managed_directories'] ?? []) as $managedDirectory) {
    if (! is_array($managedDirectory) || empty($managedDirectory['path']) || empty($managedDirectory['extensions'])) {
        $errors[] = 'Invalid managed directory entry in manifest.';
        continue;
    }

    $directory = $resolveRepositoryPath($managedDirectory['path']);

    if ($directory === null || ! is_dir($directory)) {
        $errors[] = "Managed resource directory is missing: {$managedDirectory['path']}";
        continue;
    }

    // Compare the manifest's tracked set with files on disk to catch both missing and untracked managed resources.
    $directoryPrefix = rtrim(str_replace('\\', '/', $managedDirectory['path']), '/') . '/';
    $expectedFiles   = [];

    foreach (array_keys($trackedFiles) as $relativePath) {
        if (strpos($relativePath, $directoryPrefix) !== 0) {
            continue;
        }

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        if (in_array($extension, $managedDirectory['extensions'], true)) {
            $expectedFiles[$relativePath] = true;
        }
    }

    $actualFiles = [];
    $iterator    = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (! $fileInfo->isFile()) {
            continue;
        }

        $extension = strtolower($fileInfo->getExtension());

        if (! in_array($extension, $managedDirectory['extensions'], true)) {
            continue;
        }

        $relativePath               = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($repositoryRoot) + 1));
        $actualFiles[$relativePath] = true;
    }

    foreach (array_diff_key($expectedFiles, $actualFiles) as $relativePath => $_) {
        $errors[] = "Manifest entry is absent from managed directory {$managedDirectory['path']}: {$relativePath}";
    }

    foreach (array_diff_key($actualFiles, $expectedFiles) as $relativePath => $_) {
        $errors[] = "Untracked {$managedDirectory['path']} resource: {$relativePath}";
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Validation resource inventory check failed:\n");

    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}\n");
    }

    exit(1);
}

fwrite(STDOUT, 'Validation resource inventory is consistent: ' . count($trackedFiles) . ' files, '
    . count($manifest['implementations']) . " rule implementation groups.\n");
