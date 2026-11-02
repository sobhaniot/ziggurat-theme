param(
    [string]$OutputPath = (Join-Path $PSScriptRoot 'dist/zigurat-theme-updater.zip')
)

$ErrorActionPreference = 'Stop'
$pluginName = 'zigurat-theme-updater'
$staging = Join-Path $PSScriptRoot ('.build-staging-' + [guid]::NewGuid().ToString('N'))
$pluginDir = Join-Path $staging $pluginName
$outputFullPath = [System.IO.Path]::GetFullPath($OutputPath)
$outputDir = Split-Path -Parent $outputFullPath

New-Item -ItemType Directory -Path $pluginDir -Force | Out-Null
New-Item -ItemType Directory -Path $outputDir -Force | Out-Null
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'zigurat-theme-updater.php') -Destination $pluginDir
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'README.md') -Destination $pluginDir

if (Test-Path -LiteralPath $outputFullPath) {
    Remove-Item -LiteralPath $outputFullPath -Force
}
Compress-Archive -LiteralPath (Join-Path $staging $pluginName) -DestinationPath $outputFullPath -CompressionLevel Optimal
Remove-Item -LiteralPath $staging -Recurse -Force
Write-Output $outputFullPath
