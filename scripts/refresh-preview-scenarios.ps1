param(
    [string]$Domain = 'wp-to-react.local'
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
$seedScript = Join-Path $projectRoot 'scripts\seed-preview-scenarios.php'
$sitesFile = Join-Path $env:APPDATA 'Local\sites.json'
$tempIni = Join-Path $projectRoot '.tmp-local-php.ini'

Assert-Condition (Test-Path $seedScript) "Preview seed script not found at $seedScript"
Assert-Condition (Test-Path $sitesFile) "Could not find Local sites.json at $sitesFile"

$sites = Get-Content -LiteralPath $sitesFile -Raw | ConvertFrom-Json
Assert-Condition ($null -ne $sites) 'Local sites.json did not contain any sites.'

$siteConfig = $null

foreach ($siteEntry in $sites.PSObject.Properties) {
    $site = $siteEntry.Value

    if ($site.domain -eq $Domain) {
        $siteConfig = $site
        break
    }
}

Assert-Condition ($null -ne $siteConfig) "Could not find a Local site for domain $Domain."

$phpVersion = $siteConfig.services.php.version
Assert-Condition (-not [string]::IsNullOrWhiteSpace($phpVersion)) "Could not determine Local PHP version for $Domain."

$lightningRoot = Join-Path $env:APPDATA 'Local\lightning-services'
$phpServiceDir = Get-ChildItem -Path $lightningRoot -Directory -Filter "php-$phpVersion*" | Select-Object -First 1
Assert-Condition ($null -ne $phpServiceDir) "Could not find a Local PHP service directory for version $phpVersion."

$phpBin = Join-Path $phpServiceDir.FullName 'bin\win64\php.exe'
$extensionDir = Join-Path $phpServiceDir.FullName 'bin\win64\ext'
Assert-Condition (Test-Path $phpBin) "Could not find Local PHP binary at $phpBin"
Assert-Condition (Test-Path $extensionDir) "Could not find Local PHP extension directory at $extensionDir"

$iniLines = @(
    "extension_dir=""$extensionDir"""
    'extension=mysqli'
)

Set-Content -LiteralPath $tempIni -Value $iniLines -Encoding ASCII

try {
    $output = & $phpBin -c $tempIni $seedScript
    $output | Write-Output
}
finally {
    if (Test-Path $tempIni) {
        Remove-Item -LiteralPath $tempIni -Force
    }
}
