$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$buildRoot = Join-Path $projectRoot 'build'
$packageRoot = Join-Path $buildRoot 'pressbridge'
$zipPath = Join-Path $buildRoot 'pressbridge-0.2.0.zip'
$legacyPackageRoot = Join-Path $buildRoot 'wp-to-react'
$legacyZipPath = Join-Path $buildRoot 'wp-to-react-0.2.0.zip'

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

if (Test-Path $legacyPackageRoot) {
    Remove-Item -LiteralPath $legacyPackageRoot -Recurse -Force
}

if (Test-Path $legacyZipPath) {
    Remove-Item -LiteralPath $legacyZipPath -Force
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
