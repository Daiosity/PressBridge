param(
    [string]$WordPressBase = 'http://wp-to-react.local',
    [string]$FrontendBase = 'http://localhost:5173'
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

function Invoke-Json {
    param(
        [string]$Url
    )

    $response = Invoke-WebRequest -UseBasicParsing $Url
    Assert-Condition ($response.StatusCode -eq 200) "Expected 200 from $Url but got $($response.StatusCode)."

    return $response.Content | ConvertFrom-Json
}

function Assert-FileParity {
    param(
        [string]$Left,
        [string]$Right
    )

    $leftHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $Left).Hash
    $rightHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $Right).Hash

    Assert-Condition ($leftHash -eq $rightHash) "Starter parity mismatch:`n$Left`n$Right"
}

$projectRoot = Split-Path -Parent $PSScriptRoot
$apiBase = ($WordPressBase.TrimEnd('/')) + '/wp-json/pressbridge/v1'

Write-Host "Checking PressBridge core endpoints..." -ForegroundColor Cyan

$site = Invoke-Json "$apiBase/site"
Assert-Condition (-not [string]::IsNullOrWhiteSpace($site.name)) 'Site endpoint returned no site name.'

$pages = Invoke-Json "$apiBase/pages"
Assert-Condition ($null -ne $pages.items) 'Pages endpoint returned no items collection.'

$posts = Invoke-Json "$apiBase/posts"
Assert-Condition ($null -ne $posts.items) 'Posts endpoint returned no items collection.'

$resolvedHome = Invoke-Json "$apiBase/resolve?path=/"
Assert-Condition (-not [string]::IsNullOrWhiteSpace($resolvedHome.route_type)) 'Resolve endpoint returned no route_type for home.'

Write-Host "Checking starter parity..." -ForegroundColor Cyan

$parityPairs = @(
    @('frontend-app\src\App.jsx', 'assets\starter\src\App.jsx'),
    @('frontend-app\src\styles.css', 'assets\starter\src\styles.css'),
    @('frontend-app\src\lib\api.js', 'assets\starter\src\lib\api.js'),
    @('frontend-app\src\blocks\BlockRenderer.jsx', 'assets\starter\src\blocks\BlockRenderer.jsx'),
    @('frontend-app\src\blocks\renderers.jsx', 'assets\starter\src\blocks\renderers.jsx'),
    @('frontend-app\src\blocks\utils.js', 'assets\starter\src\blocks\utils.js'),
    @('frontend-app\index.html', 'assets\starter\index.html')
)

foreach ($pair in $parityPairs) {
    Assert-FileParity `
        -Left (Join-Path $projectRoot $pair[0]) `
        -Right (Join-Path $projectRoot $pair[1])
}

Write-Host "Checking frontend availability..." -ForegroundColor Cyan

$frontendResponse = Invoke-WebRequest -UseBasicParsing $FrontendBase
Assert-Condition ($frontendResponse.StatusCode -eq 200) "Expected 200 from $FrontendBase but got $($frontendResponse.StatusCode)."

Write-Host ''
Write-Host 'PressBridge core validation passed.' -ForegroundColor Green
Write-Host "WordPress base: $WordPressBase"
Write-Host "Frontend base:  $FrontendBase"
Write-Host "Pages found:    $($pages.items.Count)"
Write-Host "Posts found:    $($posts.items.Count)"
Write-Host "Home route:     $($resolvedHome.route_type)"
