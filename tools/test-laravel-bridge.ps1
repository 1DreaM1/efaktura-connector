param(
    [ValidateSet('5.6', '13')]
    [string] $LaravelVersion = '13',
    [switch] $KeepTemp
)

$ErrorActionPreference = 'Stop'

$toolRoot = $PSScriptRoot
$repositoryRoot = (Resolve-Path (Join-Path $toolRoot '..')).Path
$composerProject = if ($LaravelVersion -eq '5.6') {
    Join-Path $toolRoot 'laravel-bridge/5.6'
} else {
    Join-Path $toolRoot 'laravel-bridge'
}
$bridgeTests = Get-ChildItem -LiteralPath (Join-Path $repositoryRoot 'tests/Laravel') -Filter '*Test.php' |
    Sort-Object Name |
    ForEach-Object { $_.FullName }
if (-not $bridgeTests) {
    throw 'No Laravel bridge tests were found in tests/Laravel.'
}
$composerManifest = Join-Path $composerProject 'composer.json'
$composerLock = Join-Path $composerProject 'composer.lock'

if (-not (Test-Path -LiteralPath $composerManifest) -or -not (Test-Path -LiteralPath $composerLock)) {
    throw 'The isolated Laravel bridge Composer manifest or lock file is missing.'
}

$phpCommand = Get-Command php -ErrorAction Stop
$phpVersion = (& $phpCommand.Source -r 'echo PHP_MAJOR_VERSION, chr(46), PHP_MINOR_VERSION;').Trim()
$minimumPhpVersion = if ($LaravelVersion -eq '5.6') { '7.4' } else { '8.4' }
if ($LASTEXITCODE -ne 0 -or [version]::Parse($phpVersion) -lt [version]::Parse($minimumPhpVersion)) {
    throw "PHP $minimumPhpVersion or newer is required for Laravel $LaravelVersion bridge tests; found PHP $phpVersion."
}

$tempParent = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath()).TrimEnd([char[]]@('\', '/'))
$isolatedRoot = Join-Path $tempParent ('efaktura-laravel-bridge-' + $LaravelVersion + '-' + [guid]::NewGuid().ToString('N'))
$isolatedComposerHome = Join-Path $isolatedRoot 'composer-home'
$isolatedCache = Join-Path $isolatedRoot 'composer-cache'
$isolatedVendor = Join-Path $isolatedRoot 'vendor'
$previousEnvironment = @{}
$environmentNames = @('COMPOSER', 'COMPOSER_HOME', 'COMPOSER_CACHE_DIR', 'COMPOSER_VENDOR_DIR', 'EFAKTURA_CONNECTOR_AUTOLOAD')
$locationPushed = $false

# Capture process-level settings only; the finally block restores them before the script exits.
foreach ($environmentName in $environmentNames) {
    $previousEnvironment[$environmentName] = [System.Environment]::GetEnvironmentVariable(
        $environmentName,
        [System.EnvironmentVariableTarget]::Process
    )
}

try {
    New-Item -ItemType Directory -Path $isolatedComposerHome -Force | Out-Null
    New-Item -ItemType Directory -Path $isolatedCache -Force | Out-Null

    [System.Environment]::SetEnvironmentVariable('COMPOSER_HOME', $isolatedComposerHome, 'Process')
    [System.Environment]::SetEnvironmentVariable('COMPOSER_CACHE_DIR', $isolatedCache, 'Process')
    [System.Environment]::SetEnvironmentVariable('COMPOSER_VENDOR_DIR', $isolatedVendor, 'Process')
    [System.Environment]::SetEnvironmentVariable('EFAKTURA_CONNECTOR_AUTOLOAD', (Join-Path $isolatedVendor 'autoload.php'), 'Process')
    [System.Environment]::SetEnvironmentVariable('COMPOSER', $null, 'Process')

    # Keep the Composer executable and its checksum in the isolated temp tree, then verify the download before running it.
    $composerPhar = Join-Path $isolatedRoot 'composer.phar'
    $composerChecksumPath = Join-Path $isolatedRoot 'composer.phar.sha256'
    Invoke-WebRequest -Uri 'https://getcomposer.org/download/latest-stable/composer.phar' -OutFile $composerPhar
    Invoke-WebRequest -Uri 'https://getcomposer.org/download/latest-stable/composer.phar.sha256' -OutFile $composerChecksumPath
    $expectedComposerHash = (Get-Content -LiteralPath $composerChecksumPath -Raw).Trim().Split(' ')[0]
    $actualComposerHash = (Get-FileHash -LiteralPath $composerPhar -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($expectedComposerHash -notmatch '\A[a-f0-9]{64}\z' -or $actualComposerHash -ne $expectedComposerHash) {
        throw 'The downloaded Composer PHAR failed its SHA-256 verification.'
    }

    Push-Location $composerProject
    $locationPushed = $true

    # Install the locked dependencies without executing Composer plugins or package scripts.
    & $phpCommand.Source $composerPhar install --no-interaction --no-progress --prefer-dist --no-plugins --no-scripts
    if ($LASTEXITCODE -ne 0) {
        throw 'Composer could not install the isolated Laravel bridge test dependencies.'
    }

    & $phpCommand.Source $composerPhar check-platform-reqs
    if ($LASTEXITCODE -ne 0) {
        throw "The isolated Laravel $LaravelVersion bridge dependencies do not match PHP $phpVersion."
    }

    $phpUnitRunner = Join-Path $isolatedVendor 'phpunit/phpunit/phpunit'
    $autoloadFile = Join-Path $isolatedVendor 'autoload.php'
    foreach ($bridgeTest in $bridgeTests) {
        $phpUnitArguments = @($phpUnitRunner, '--no-configuration', '--bootstrap', $autoloadFile)
        if ($LaravelVersion -eq '13') {
            $phpUnitArguments += '--do-not-record-test-run-history'
        }
        $phpUnitArguments += $bridgeTest
        & $phpCommand.Source @phpUnitArguments
        if ($LASTEXITCODE -ne 0) {
            throw "The Laravel bridge test failed: $bridgeTest"
        }
    }

    & $phpCommand.Source (Join-Path $toolRoot 'check-public-api.php')
    if ($LASTEXITCODE -ne 0) {
        throw 'The full public API stability check failed.'
    }
} finally {
    # Restore the caller's directory and Composer environment even when download, install or tests fail.
    if ($locationPushed) {
        Pop-Location
    }

    foreach ($environmentName in $environmentNames) {
        [System.Environment]::SetEnvironmentVariable(
            $environmentName,
            $previousEnvironment[$environmentName],
            [System.EnvironmentVariableTarget]::Process
        )
    }

    if ($KeepTemp) {
        Write-Output "Temporary Laravel test project kept at: $isolatedRoot"
    } elseif (Test-Path -LiteralPath $isolatedRoot) {
        $resolvedRoot = (Resolve-Path -LiteralPath $isolatedRoot).Path
        $requiredPrefix = $tempParent + [System.IO.Path]::DirectorySeparatorChar
        # Recheck the resolved target and unique directory name before recursive deletion.
        $rootIsInTemp = $resolvedRoot.StartsWith($requiredPrefix, [System.StringComparison]::OrdinalIgnoreCase)
        $rootHasExpectedName = (Split-Path -Leaf $resolvedRoot) -like ('efaktura-laravel-bridge-' + $LaravelVersion + '-*')
        if (-not $rootIsInTemp -or -not $rootHasExpectedName) {
            throw 'The isolated test directory is outside the expected temporary location.'
        }

        Remove-Item -LiteralPath $resolvedRoot -Recurse -Force
    }
}
