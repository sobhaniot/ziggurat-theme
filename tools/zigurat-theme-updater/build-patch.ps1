param(
    [Parameter(Mandatory = $true)]
    [string[]]$Files,
    [string]$ThemeRoot = 'C:/xampp/htdocs/wordpress/wp-content/themes/zigurat',
    [string]$OutputPath = (Join-Path $PSScriptRoot ('dist/zigurat-patch-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.zip'))
)

$ErrorActionPreference = 'Stop'
$themeFullPath = (Resolve-Path -LiteralPath $ThemeRoot).Path
$outputFullPath = [System.IO.Path]::GetFullPath($OutputPath)
$outputDir = Split-Path -Parent $outputFullPath
$staging = Join-Path $PSScriptRoot ('.patch-staging-' + [guid]::NewGuid().ToString('N'))
$blockedSegments = @('.git', 'node_modules', 'tests', 'test', 'data', 'backups', 'vendor', '.secrets')
$allowedExtensions = @('.php', '.js', '.css', '.html', '.json', '.svg', '.png', '.jpg', '.jpeg', '.gif', '.webp', '.ico', '.woff', '.woff2', '.ttf', '.otf', '.map', '.txt')

if (-not $Files -or $Files.Count -gt 200) { throw 'Provide between 1 and 200 changed file paths.' }
$normalized = @()
foreach ($file in $Files) {
    if ([string]::IsNullOrWhiteSpace($file) -or $file.Contains('\') -or $file.StartsWith('/') -or $file -match '(^|/)..?(/|$)') {
        throw "Invalid relative path: $file"
    }
    $segments = $file.Split('/')
    if ($segments | Where-Object { $_.StartsWith('.') -or $_ -in $blockedSegments }) { throw "Blocked path: $file" }
    if ([System.IO.Path]::GetExtension($file).ToLowerInvariant() -notin $allowedExtensions) { throw "Unsupported extension: $file" }
    if ($normalized -contains $file) { throw "Duplicate path: $file" }
    $source = [System.IO.Path]::GetFullPath((Join-Path $themeFullPath ($file.Replace('/', [System.IO.Path]::DirectorySeparatorChar))))
    if (-not $source.StartsWith($themeFullPath.TrimEnd('\','/') + [System.IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw "Path leaves the theme root: $file" }
    if (-not (Test-Path -LiteralPath $source -PathType Leaf)) { throw "File does not exist: $file" }
    $cursor = $themeFullPath
    foreach ($segment in $segments) {
        $cursor = Join-Path $cursor $segment
        if ((Get-Item -LiteralPath $cursor -Force).Attributes -band [System.IO.FileAttributes]::ReparsePoint) { throw "Symbolic links are not allowed: $file" }
    }
    $normalized += $file
}

New-Item -ItemType Directory -Path $staging -Force | Out-Null
New-Item -ItemType Directory -Path $outputDir -Force | Out-Null
try {
    foreach ($file in $normalized) {
        $destination = Join-Path $staging ($file.Replace('/', [System.IO.Path]::DirectorySeparatorChar))
        $destinationDir = Split-Path -Parent $destination
        New-Item -ItemType Directory -Path $destinationDir -Force | Out-Null
        $source = Join-Path $themeFullPath ($file.Replace('/', [System.IO.Path]::DirectorySeparatorChar))
        Copy-Item -LiteralPath $source -Destination $destination
    }
    $manifest = [ordered]@{ theme = 'zigurat'; files = $normalized }
    $manifestPath = Join-Path $staging 'zigurat-theme-patch.json'
    $json = $manifest | ConvertTo-Json -Depth 4
    [System.IO.File]::WriteAllText($manifestPath, $json, [System.Text.UTF8Encoding]::new($false))
    if (Test-Path -LiteralPath $outputFullPath) { Remove-Item -LiteralPath $outputFullPath -Force }
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [System.IO.Compression.ZipFile]::CreateFromDirectory($staging, $outputFullPath, [System.IO.Compression.CompressionLevel]::Optimal, $false)
} finally {
    if (Test-Path -LiteralPath $staging) { Remove-Item -LiteralPath $staging -Recurse -Force }
}
Write-Output $outputFullPath
