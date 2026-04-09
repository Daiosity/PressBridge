param(
    [switch]$SkipBuild
)

$ErrorActionPreference = 'Stop'

function Assert-Condition {
    param(
        [bool]$Condition,
        [string]$Message
    )

    if (-not $Condition) {
        throw $Message
    }
}

$projectRoot = Split-Path -Parent $PSScriptRoot
$pluginBootstrap = Join-Path $projectRoot 'pressbridge.php'
$buildScript = Join-Path $projectRoot 'scripts\build-plugin.ps1'
$buildRoot = Join-Path $projectRoot 'build'
$stagingRoot = Join-Path $buildRoot '.package-check'

$pluginHeader = Get-Content -LiteralPath $pluginBootstrap -Raw
$versionMatch = [regex]::Match($pluginHeader, '(?m)^\s*\*\s*Version:\s*(?<version>[0-9A-Za-z.\-_]+)\s*$')
Assert-Condition $versionMatch.Success "Could not determine plugin version from $pluginBootstrap"

$pluginVersion = $versionMatch.Groups['version'].Value
$zipPath = Join-Path $buildRoot ("lenviqa-$pluginVersion.zip")

if (-not $SkipBuild) {
    & powershell -ExecutionPolicy Bypass -File $buildScript | Out-Null
}

Assert-Condition (Test-Path $zipPath) "Expected plugin zip at $zipPath"

if (Test-Path $stagingRoot) {
    Remove-Item -LiteralPath $stagingRoot -Recurse -Force
}

New-Item -ItemType Directory -Path $stagingRoot -Force | Out-Null

Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::ExtractToDirectory($zipPath, $stagingRoot)

$packageRoot = Join-Path $stagingRoot 'lenviqa'
Assert-Condition (Test-Path $packageRoot) "Extracted zip does not contain a top-level lenviqa folder."

$requiredItems = @(
    'pressbridge.php',
    'readme.txt',
    'uninstall.php',
    'assets',
    'includes',
    'templates'
)

foreach ($item in $requiredItems) {
    $target = Join-Path $packageRoot $item
    Assert-Condition (Test-Path $target) "Expected packaged item missing: $target"
}

$requiredComponentFiles = @(
    'includes\Core\Activator.php',
    'includes\Core\Deactivator.php',
    'includes\Admin\Settings_Page.php',
    'includes\Admin\Starter_Export.php',
    'templates\admin-settings-page.php',
    'assets\starter'
)

foreach ($item in $requiredComponentFiles) {
    $target = Join-Path $packageRoot $item
    Assert-Condition (Test-Path $target) "Expected packaged component missing: $target"
}

$packagedBootstrap = Get-Content -LiteralPath (Join-Path $packageRoot 'pressbridge.php') -Raw
$packagedVersionMatch = [regex]::Match($packagedBootstrap, '(?m)^\s*\*\s*Version:\s*(?<version>[0-9A-Za-z.\-_]+)\s*$')
Assert-Condition $packagedVersionMatch.Success 'Packaged plugin bootstrap is missing a Version header.'
Assert-Condition ($packagedVersionMatch.Groups['version'].Value -eq $pluginVersion) 'Packaged plugin version does not match source version.'
Assert-Condition ($packagedBootstrap -match 'register_activation_hook') 'Packaged plugin bootstrap is missing activation hook registration.'
Assert-Condition ($packagedBootstrap -match 'register_deactivation_hook') 'Packaged plugin bootstrap is missing deactivation hook registration.'
Assert-Condition ($packagedBootstrap -match "define\(\s*'WTR_VERSION'\s*,\s*'$([regex]::Escape($pluginVersion))'\s*\)") 'Packaged WTR_VERSION does not match the plugin header version.'

$packagedUninstall = Get-Content -LiteralPath (Join-Path $packageRoot 'uninstall.php') -Raw
Assert-Condition ($packagedUninstall -match "delete_option\(\s*'wtr_settings'\s*\)") 'Packaged uninstall routine is missing wtr_settings cleanup.'
Assert-Condition ($packagedUninstall -match "delete_site_option\(\s*'wtr_settings'\s*\)") 'Packaged uninstall routine is missing multisite wtr_settings cleanup.'

$packagedStarterExport = Get-Content -LiteralPath (Join-Path $packageRoot 'includes\Admin\Starter_Export.php') -Raw
Assert-Condition ($packagedStarterExport -match "src/config/wp-config\.json") 'Packaged starter export is missing runtime config injection.'
Assert-Condition ($packagedStarterExport -match "'apiBase'\s*=>\s*untrailingslashit") 'Packaged starter export is missing apiBase runtime config wiring.'

Write-Host ''
Write-Host 'Lenviqa package validation passed.' -ForegroundColor Green
Write-Host "Version:  $pluginVersion"
Write-Host "ZIP:      $zipPath"
Write-Host "Package:  $packageRoot"
