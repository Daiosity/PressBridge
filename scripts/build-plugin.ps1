$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$pluginBootstrap = Join-Path $projectRoot 'pressbridge.php'
$buildRoot = Join-Path $projectRoot 'build'
$packageRoot = Join-Path $buildRoot 'pressbridge'

if (-not (Test-Path $pluginBootstrap)) {
    throw "Could not find plugin bootstrap file at $pluginBootstrap"
}

$pluginHeader = Get-Content -LiteralPath $pluginBootstrap -Raw
$versionMatch = [regex]::Match($pluginHeader, '(?m)^\s*\*\s*Version:\s*(?<version>[0-9A-Za-z.\-_]+)\s*$')

if (-not $versionMatch.Success) {
    throw "Could not determine plugin version from $pluginBootstrap"
}

$pluginVersion = $versionMatch.Groups['version'].Value
$zipPath = Join-Path $buildRoot ("pressbridge-$pluginVersion.zip")

$itemsToCopy = @(
    'pressbridge.php',
    'readme.txt',
    'uninstall.php',
    'assets',
    'includes',
    'templates'
)

if (Test-Path $packageRoot) {
    Remove-Item -LiteralPath $packageRoot -Recurse -Force
}

if (Test-Path $zipPath) {
    Remove-Item -LiteralPath $zipPath -Force
}

New-Item -ItemType Directory -Path $packageRoot -Force | Out-Null

foreach ($item in $itemsToCopy) {
    Copy-Item -LiteralPath (Join-Path $projectRoot $item) -Destination $packageRoot -Recurse -Force
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zipArchive = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)

try {
    $files = Get-ChildItem -Path $packageRoot -Recurse -File

    foreach ($file in $files) {
        $relativePath = $file.FullName.Substring($buildRoot.Length + 1).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $zipArchive,
            $file.FullName,
            $relativePath,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
}
finally {
    if ($null -ne $zipArchive) {
        $zipArchive.Dispose()
    }
}

Get-Item $zipPath | Select-Object FullName, Length, LastWriteTime
