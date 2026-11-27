$ErrorActionPreference = 'Stop'

$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$tempBase       = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath()).TrimEnd([System.IO.Path]::DirectorySeparatorChar, [System.IO.Path]::AltDirectorySeparatorChar)
$tempRoot       = Join-Path $tempBase ('efaktura-composer-smoke-' + [guid]::NewGuid().ToString('N'))
$resolvedTemp   = [System.IO.Path]::GetFullPath($tempRoot)
$tempPrefix     = $tempBase + [System.IO.Path]::DirectorySeparatorChar
$previousCache  = $env:COMPOSER_CACHE_DIR

if (-not $resolvedTemp.StartsWith($tempPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Refusing to use a temporary path outside the system temp directory: $resolvedTemp"
}

$packageRoot     = Join-Path $resolvedTemp 'package'
$artifactRoot    = Join-Path $resolvedTemp 'artifact'
$applicationRoot = Join-Path $resolvedTemp 'consumer'

try {
    New-Item -ItemType Directory -Path $packageRoot, $artifactRoot, $applicationRoot -Force | Out-Null
    $env:COMPOSER_CACHE_DIR = Join-Path $resolvedTemp 'composer-cache'

    # Check what `git archive` (the source of the Composer dist) ships, independent of the hand-copied inputs below.
    # It archives HEAD, so commit changes first; --worktree-attributes also applies a not yet committed .gitattributes.
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    # Keep it outside $artifactRoot: the consumer reads every ZIP there as an installable package.
    $distArchivePath = Join-Path $resolvedTemp 'git-archive.zip'
    & git -C $repositoryRoot archive --worktree-attributes --format=zip "--output=$distArchivePath" HEAD
    if ($LASTEXITCODE -ne 0) {
        throw "git archive failed (exit $LASTEXITCODE)."
    }

    $distZip = [System.IO.Compression.ZipFile]::OpenRead($distArchivePath)
    try {
        $distTopLevel = @($distZip.Entries | ForEach-Object { ($_.FullName -split '/')[0] } | Sort-Object -Unique)
    } finally {
        $distZip.Dispose()
    }

    $requiredInDist = @(
        'composer.json', 'LICENSE', 'README.md', 'README.en.md', 'CHANGELOG.md', 'SECURITY.md',
        'src', 'integrations', 'config', 'resources', 'docs'
    )
    $forbiddenInDist = @(
        '.github', '.codex', '.gitleaks.toml', '.editorconfig', '.gitignore', '.php-cs-fixer.dist.php', 'AGENTS.md', 'infection.json5',
        'composer.lock', 'phpcs.xml.dist', 'phpstan.neon.dist', 'phpstan-baseline.neon', 'phpunit.xml.dist',
        'tests', 'tools', 'web', 'vendor'
    )

    $missingFromDist = @($requiredInDist | Where-Object { $distTopLevel -notcontains $_ })
    if ($missingFromDist.Count -gt 0) {
        throw "git archive is missing distribution entries: $($missingFromDist -join ', ')."
    }

    $unexpectedInDist = @($distTopLevel | Where-Object { $forbiddenInDist -contains $_ })
    if ($unexpectedInDist.Count -gt 0) {
        throw "git archive ships development-only entries (add them to .gitattributes export-ignore): $($unexpectedInDist -join ', ')."
    }

    $unlistedInDist = @($distTopLevel | Where-Object {
        ($requiredInDist -notcontains $_) -and ($_ -ne '.gitattributes')
    })
    if ($unlistedInDist.Count -gt 0) {
        throw "git archive ships entries that are neither required nor known development files: $($unlistedInDist -join ', ')."
    }

    # Copy maintained distribution inputs only; never copy the checkout's vendor or test tree.
    foreach ($entry in @('src', 'integrations', 'config', 'resources', 'docs')) {
        Copy-Item -LiteralPath (Join-Path $repositoryRoot $entry) -Destination $packageRoot -Recurse -Force
    }
    foreach ($entry in @('.gitattributes', 'CHANGELOG.md', 'composer.json', 'LICENSE', 'README.md')) {
        Copy-Item -LiteralPath (Join-Path $repositoryRoot $entry) -Destination $packageRoot -Force
    }

    # Package the copied distribution inputs in a ZIP that the clean consumer installs as an artifact.
    # Composer releases derive this version from the tag; the isolated artifact needs an explicit test version.
    $packageManifestPath = Join-Path $packageRoot 'composer.json'
    $packageManifest = Get-Content -LiteralPath $packageManifestPath -Raw | ConvertFrom-Json
    $packageManifest | Add-Member -MemberType NoteProperty -Name version -Value '1.0.0'
    $packageManifestJson = $packageManifest | ConvertTo-Json -Depth 100
    [System.IO.File]::WriteAllText(
        $packageManifestPath,
        $packageManifestJson,
        [System.Text.UTF8Encoding]::new($false)
    )

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $archivePath = Join-Path $artifactRoot 'dream1-efaktura-connector-1.0.0.zip'
    [System.IO.Compression.ZipFile]::CreateFromDirectory(
        $packageRoot,
        $archivePath,
        [System.IO.Compression.CompressionLevel]::Optimal,
        $false
    )

    $consumerManifest = [ordered]@{
        name        = 'dream1/efaktura-connector-consumer-smoke'
        type        = 'project'
        repositories = @(
            [ordered]@{ 'packagist.org' = $false },
            [ordered]@{
                type = 'artifact'
                url  = $artifactRoot
            }
        )
        require     = [ordered]@{ 'dream1/efaktura-connector' = '1.0.0' }
        config      = [ordered]@{ 'allow-plugins' = [ordered]@{} }
    }

    $manifestPath = Join-Path $applicationRoot 'composer.json'
    $manifestJson = $consumerManifest | ConvertTo-Json -Depth 10
    [System.IO.File]::WriteAllText($manifestPath, $manifestJson, [System.Text.UTF8Encoding]::new($false))

    Push-Location $applicationRoot
    try {
        & composer update --no-interaction --no-progress --no-scripts --prefer-dist
        if ($LASTEXITCODE -ne 0) {
            throw "Composer could not install the connector into the clean consumer application (exit $LASTEXITCODE)."
        }

        & composer check-platform-reqs --no-dev
        if ($LASTEXITCODE -ne 0) {
            throw "The clean consumer application does not satisfy the connector's runtime requirements (exit $LASTEXITCODE)."
        }

        $smokeScript = @'
<?php
require __DIR__ . '/vendor/autoload.php';

use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfiguration;
use DreaM1\EfakturaConnector\Laravel\Configuration\LaravelServiceConfiguration;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationResources;

if (!class_exists(SapiClientConfiguration::class)) {
    fwrite(STDERR, "Core classes are not autoloadable from the installed package.\n");
    exit(1);
}

if (!class_exists(LaravelServiceConfiguration::class)) {
    fwrite(STDERR, "Optional Laravel bridge classes are not autoloadable from the installed package.\n");
    exit(1);
}

$packageManifest = json_decode(file_get_contents(__DIR__ . '/vendor/dream1/efaktura-connector/composer.json'), true);
$provider = 'DreaM1\\EfakturaConnector\\Laravel\\EFakturaConnectorServiceProvider';
if (!is_array($packageManifest) || !in_array($provider, $packageManifest['extra']['laravel']['providers'] ?? [], true)) {
    fwrite(STDERR, "Laravel package discovery metadata is missing from the installed package.\n");
    exit(1);
}

$requiredResources = array_merge(
    [ValidationResources::path('ubl-2.1/xsd/maindoc/UBL-Invoice-2.1.xsd')],
    ValidationResources::schematronStylesheets()
);

foreach ($requiredResources as $resource) {
    if (!is_file($resource) || !is_readable($resource)) {
        fwrite(STDERR, "A bundled validation resource is missing from the installed package: {$resource}\n");
        exit(1);
    }
}

echo "Clean Composer consumer can autoload Core and Laravel bridge classes, read bundled resources, and discover the Laravel provider.\n";
'@
        $smokePath = Join-Path $applicationRoot 'smoke.php'
        [System.IO.File]::WriteAllText($smokePath, $smokeScript, [System.Text.UTF8Encoding]::new($false))
        & php $smokePath
        if ($LASTEXITCODE -ne 0) {
            throw "The clean consumer smoke check failed (exit $LASTEXITCODE)."
        }
    } finally {
        Pop-Location
    }
} finally {
    if ($null -eq $previousCache) {
        Remove-Item Env:COMPOSER_CACHE_DIR -ErrorAction SilentlyContinue
    } else {
        $env:COMPOSER_CACHE_DIR = $previousCache
    }

    $resolvedTemp = [System.IO.Path]::GetFullPath($tempRoot)
    if ((Test-Path -LiteralPath $resolvedTemp) -and
        $resolvedTemp.StartsWith($tempPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
        Remove-Item -LiteralPath $resolvedTemp -Recurse -Force
    }
}
