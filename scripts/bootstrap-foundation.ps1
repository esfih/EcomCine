$ErrorActionPreference = 'Stop'

$CoreRemoteName = if ($env:CORE_REMOTE_NAME) { $env:CORE_REMOTE_NAME } else { 'master-core' }
$WpRemoteName = if ($env:WP_REMOTE_NAME) { $env:WP_REMOTE_NAME } else { 'wp-overlay' }
$CoreRemoteUrl = if ($env:CORE_REMOTE_URL) { $env:CORE_REMOTE_URL } else { 'https://github.com/esfih/master-core.git' }
$WpRemoteUrl = if ($env:WP_REMOTE_URL) { $env:WP_REMOTE_URL } else { 'https://github.com/esfih/wp-overlay.git' }
$Branch = if ($env:FOUNDATION_BRANCH) { $env:FOUNDATION_BRANCH } else { 'main' }

function Set-RemoteIfProvided {
    param(
        [string]$Name,
        [string]$Url
    )

    if ([string]::IsNullOrWhiteSpace($Url)) {
        return
    }

    git remote get-url $Name *> $null
    if ($LASTEXITCODE -eq 0) {
        git remote set-url $Name $Url
    } else {
        git remote add $Name $Url
    }
}

function Add-SubtreeIfMissing {
    param(
        [string]$Prefix,
        [string]$Remote,
        [string]$TargetBranch
    )

    if (Test-Path $Prefix) {
        return
    }

    git fetch $Remote $TargetBranch
    git subtree add --prefix $Prefix $Remote $TargetBranch --squash
}

Set-RemoteIfProvided -Name $CoreRemoteName -Url $CoreRemoteUrl
Set-RemoteIfProvided -Name $WpRemoteName -Url $WpRemoteUrl

Add-SubtreeIfMissing -Prefix 'foundation/core' -Remote $CoreRemoteName -TargetBranch $Branch
Add-SubtreeIfMissing -Prefix 'foundation/wp' -Remote $WpRemoteName -TargetBranch $Branch

Write-Host 'Foundation bootstrap complete.'
Write-Host ''
Write-Host 'Next steps:'
Write-Host '  1. docker compose up -d'
Write-Host '  2. ./scripts/wp.sh wp db import db/seed.sql'
Write-Host "  3. ./scripts/wp.sh wp search-replace 'https://castingagency.co' 'http://localhost:8180' --all-tables"
Write-Host '  4. ./scripts/wp.sh wp user update 1 --user_pass=admin'
Write-Host "  5. ./scripts/wp.sh wp rewrite structure '/%postname%/' --hard"
Write-Host '  6. ./scripts/setup-deps.sh'
Write-Host '  7. ./scripts/check-local-wp.sh'
Write-Host ''
Write-Host 'See README-FIRST.md for the full setup guide.'
