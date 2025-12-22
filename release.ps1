Param(
    [Parameter(Mandatory=$false, Position=0)]
    [string]$Version = "v1.2.0"
)

Write-Host "Preparing release for version: $Version"

# Build frontend assets
Write-Host "Building frontend assets..."
$npmBuildResult = & npm run build 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: npm build failed!" -ForegroundColor Red
    Write-Host $npmBuildResult
    exit 1
}
Write-Host "Frontend assets built successfully!" -ForegroundColor Green

# Derive short SHA if git is available; otherwise use timestamp
$ShortSha = $null
try {
    $ShortSha = (& git rev-parse --short HEAD) 2>$null
} catch {}
if (-not $ShortSha) {
    $ShortSha = (Get-Date -Format 'yyyyMMddHHmmss')
}
$ShortSha = $ShortSha.Trim()

$Name = "jobcardonline-$Version-$ShortSha"
$DistDir = Join-Path -Path $PSScriptRoot -ChildPath "dist"
$Staging = Join-Path -Path $DistDir -ChildPath $Name
$ZipPath = Join-Path -Path $DistDir -ChildPath "$Name.zip"

New-Item -ItemType Directory -Force -Path $DistDir | Out-Null
if (Test-Path $Staging) { Remove-Item $Staging -Recurse -Force }
New-Item -ItemType Directory -Force -Path $Staging | Out-Null

# Copy project to staging excluding heavy/dev directories
# robocopy exit codes 1-7 mean success with different conditions
robocopy $PSScriptRoot $Staging /E /NFL /NDL /NJH /NJS /NC /NS /XD ".git" "dist" ".idea" ".vscode" "tests" "node_modules" "storage\logs" "storage\framework\cache" "storage\framework\sessions" "storage\framework\views" | Out-Null

# Force copy vendor directory if it exists (for production releases)
if (Test-Path "vendor") {
    Write-Host "Including vendor directory in release..."
    robocopy "vendor" "$Staging\vendor" /E /NFL /NDL /NJH /NJS /NC /NS | Out-Null
}

# Create zip
if (Test-Path $ZipPath) { Remove-Item $ZipPath -Force }
Remove-Item "$Staging\.env" -Force
Compress-Archive -Path (Join-Path $Staging '*') -DestinationPath $ZipPath -Force

# Cleanup staging
Remove-Item $Staging -Recurse -Force

Write-Host "Created release artifact: $ZipPath"

