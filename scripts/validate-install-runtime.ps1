param(
    [string]$Domain = 'wp-to-react.local',
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
$sitesFile = Join-Path $env:APPDATA 'Local\sites.json'
$tempIni = Join-Path $projectRoot '.tmp-local-php.ini'
$runtimeScript = Join-Path $projectRoot 'scripts\validate-install-runtime.php'
$packageValidator = Join-Path $projectRoot 'scripts\validate-package.ps1'
$buildRoot = Join-Path $projectRoot 'build'

Assert-Condition (Test-Path $sitesFile) "Could not find Local sites.json at $sitesFile"
Assert-Condition (Test-Path $runtimeScript) "Install runtime validator not found at $runtimeScript"
Assert-Condition (Test-Path $packageValidator) "Package validator not found at $packageValidator"

$sites = Get-Content -LiteralPath $sitesFile -Raw | ConvertFrom-Json
$siteConfig = $null

foreach ($siteEntry in $sites.PSObject.Properties) {
    $site = $siteEntry.Value
    if ($site.domain -eq $Domain) {
        $siteConfig = $site
        break
    }
}

Assert-Condition ($null -ne $siteConfig) "Could not find a Local site for domain $Domain."

$siteRoot = Join-Path $siteConfig.path 'app\public'
$pluginsRoot = Join-Path $siteRoot 'wp-content\plugins'
$installedPluginDir = Join-Path $pluginsRoot 'pressbridge'
$wpConfigPath = Join-Path $siteRoot 'wp-config.php'

Assert-Condition (Test-Path $siteRoot) "Could not find Local WordPress root at $siteRoot"
Assert-Condition (Test-Path $pluginsRoot) "Could not find WordPress plugins directory at $pluginsRoot"
Assert-Condition (Test-Path $wpConfigPath) "Could not find wp-config.php at $wpConfigPath"

$phpVersion = $siteConfig.services.php.version
$mysqlPort = [int]$siteConfig.services.mysql.ports.MYSQL[0]
$lightningRoot = Join-Path $env:APPDATA 'Local\lightning-services'
$phpServiceDir = Get-ChildItem -Path $lightningRoot -Directory -Filter "php-$phpVersion*" | Select-Object -First 1
Assert-Condition ($null -ne $phpServiceDir) "Could not find a Local PHP service directory for version $phpVersion."

$phpBin = Join-Path $phpServiceDir.FullName 'bin\win64\php.exe'
$extensionDir = Join-Path $phpServiceDir.FullName 'bin\win64\ext'
Assert-Condition (Test-Path $phpBin) "Could not find Local PHP binary at $phpBin"
Assert-Condition (Test-Path $extensionDir) "Could not find Local PHP extension directory at $extensionDir"

$pluginHeader = Get-Content -LiteralPath (Join-Path $projectRoot 'pressbridge.php') -Raw
$versionMatch = [regex]::Match($pluginHeader, '(?m)^\s*\*\s*Version:\s*(?<version>[0-9A-Za-z.\-_]+)\s*$')
Assert-Condition $versionMatch.Success 'Could not determine plugin version from pressbridge.php'
$zipPath = Join-Path $buildRoot ("pressbridge-{0}.zip" -f $versionMatch.Groups['version'].Value)

if (-not $SkipBuild) {
    & powershell -ExecutionPolicy Bypass -File $packageValidator | Out-Null
} else {
    Assert-Condition (Test-Path $zipPath) "Expected built ZIP at $zipPath"
}

$installStage = Join-Path $buildRoot '.install-check'
$installExtract = Join-Path $installStage 'extract'

if (Test-Path $installStage) {
    Remove-Item -LiteralPath $installStage -Recurse -Force
}

New-Item -ItemType Directory -Path $installExtract -Force | Out-Null
Expand-Archive -LiteralPath $zipPath -DestinationPath $installExtract -Force

$extractedPluginDir = Join-Path $installExtract 'pressbridge'
Assert-Condition (Test-Path $extractedPluginDir) "Extracted install package missing pressbridge directory at $extractedPluginDir"

if (Test-Path $installedPluginDir) {
    Remove-Item -LiteralPath $installedPluginDir -Recurse -Force
}

Copy-Item -LiteralPath $extractedPluginDir -Destination $installedPluginDir -Recurse -Force

$iniLines = @(
    "extension_dir=""$extensionDir"""
    'extension=mysqli'
    'extension=mbstring'
    'extension=openssl'
    'extension=fileinfo'
    'extension=gd'
    'extension=intl'
    'extension=zip'
    'extension=exif'
    'extension=sodium'
)

Set-Content -LiteralPath $tempIni -Value $iniLines -Encoding ASCII

$originalWpConfig = Get-Content -LiteralPath $wpConfigPath -Raw
$cliDbHost = "127.0.0.1:$mysqlPort"
$patchedWpConfig = [regex]::Replace(
    $originalWpConfig,
    "(?m)^define\(\s*'DB_HOST'\s*,\s*'[^']*'\s*\);\s*$",
    "define( 'DB_HOST', '$cliDbHost' );"
)

Assert-Condition ($patchedWpConfig -ne $originalWpConfig) 'Could not patch DB_HOST in wp-config.php for Local CLI validation.'

try {
    Set-Content -LiteralPath $wpConfigPath -Value $patchedWpConfig -Encoding ASCII
    $result = & $phpBin -c $tempIni $runtimeScript $siteRoot 'pressbridge/pressbridge.php'
    $result | Write-Output
}
finally {
    Set-Content -LiteralPath $wpConfigPath -Value $originalWpConfig -Encoding ASCII

    if (Test-Path $tempIni) {
        Remove-Item -LiteralPath $tempIni -Force
    }
}
